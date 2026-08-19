# Diseño técnico — Gestión de Proyectos

## Alcance de este documento

**Corregido tras hallazgo de `explorador-eventos`:** el mecanismo
genérico del usuario (`AdminBaseTrait`) resuelve Controlador y Vistas
por convención — **no el Modelo**. `Project`/`Group` sí necesitan su
archivo de modelo ActiveRecord estándar (ver
`.claude/specs/gestion-proyectos/tasks.md`, tareas 7-9, para la
restauración). Este documento cubre diseño de datos (migraciones,
relación muchos-a-muchos) **y** los modelos ActiveRecord — ya no
excluye el modelo como se pensaba originalmente. Sigue sin cubrir
controlador ni vistas, eso sí lo resuelve el mecanismo genérico.

## Migraciones

### `create_projects.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary |
| `name` | VARCHAR(255) | NOT NULL |
| `description` | TEXT | NULL |
| `repository_url` | VARCHAR(255) | NULL |
| `type` | VARCHAR(50) | NOT NULL — lista cerrada de valores válidos (ej. `backend`, `frontend`, `library`, `mobile`); el enforcement de esa lista es responsabilidad de la capa de modelo genérica, no de la migración |
| `status` | INTEGER | NOT NULL, `limit=1`, `default=0` |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índices:
- `Add_Single_Index('name')` — soporta la validación de unicidad
  (Requisito 2.3) y búsquedas por nombre.

### `create_groups.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary |
| `name` | VARCHAR(255) | NOT NULL |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índices:
- `Add_Single_Index('name')`.

### `create_project_groups.php` (tabla pivote)

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary — igual que el resto de tablas del proyecto, no se usa PK compuesta `(project_id, group_id)` |
| `project_id` | INTEGER | NOT NULL |
| `group_id` | INTEGER | NOT NULL |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índices:
- `Add_Index(['project_id', 'group_id'])` — compuesto, para consultar
  en ambas direcciones (proyectos de un grupo, grupos de un proyecto).
- **Confirmado contra el framework real (verificado durante la
  implementación de `metricas-oem`, mismo hallazgo aplicado aquí):**
  `Migrations::Add_Index()` no soporta `UNIQUE` en ningún driver
  (`mysql.php`/`sqlite.php` generan `ADD INDEX`/`CREATE INDEX` sin
  variante única; no existe `AddUniqueIndex`). La unicidad del par
  `(project_id, group_id)` **no se puede garantizar a nivel de BD**
  con el mecanismo de migraciones actual — debe verificarse en la
  capa de aplicación (el mecanismo genérico de CRUD del usuario) antes
  de insertar, comprobando que no exista ya esa combinación. A
  diferencia de `oem_metrics` (donde una fila duplicada solo afecta un
  invariante interno sin corromper el dato agregado), aquí una
  duplicación sí es un problema visible — un proyecto aparecería dos
  veces en el listado del mismo grupo. Vale la pena que el mecanismo
  genérico valide esto explícitamente, no asumirlo resuelto por el
  índice.

Sin claves foráneas (`FOREIGN KEY`) declaradas explícitamente —
`migrations.md` no documenta esa opción en el array de campos, y
ninguna tabla existente del proyecto (incluido `events` del núcleo
OEM) las usa. Consistente con el resto del proyecto, no una excepción
de este spec.

## Relación muchos-a-muchos — diseño físico

```text
projects (1) ──┐
                ├── project_groups (N) ──┐
groups (1) ─────┘                        │
                                    (project_id, group_id)
```

Un proyecto puede estar en cero o más grupos; un grupo puede tener
cero o más proyectos. Eliminar un grupo (Requisito 5.3) implica
eliminar únicamente las filas de `project_groups` que lo referencian
— ni `projects` ni `groups` se ven afectadas directamente por esa
operación.

## Fuera de alcance de este documento

- Controlador(es) y vistas — resueltos por el mecanismo genérico.
- Enforcement de la lista cerrada de valores válidos para `type`.
- Todo lo ya excluido en `requirements.md` (integración con el núcleo
  OEM, jerarquía de grupos, dashboard, ACL/multiusuario).

## Modelos restaurados — versión final implementada

> Actualizado tras implementación: se agregó sanitización
> `htmlentities()` en `before_save` (convención no negociable de
> `code-conventions.md`, no estaba en el snippet original de este
> documento pero tampoco estaba excluida) y las relaciones
> `has_many`/`belongs_to` hacia `project_groups` (necesarias por el
> hallazgo de `has_many_and_belongs_to` — ver nota más abajo).

### `app/models/project.php`

```php
<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class Project extends ActiveRecord {

    public ?string $name           = null;
    public ?string $description    = null;
    public ?string $repository_url = null;
    public ?string $type           = null;
    public ?int    $status         = null;

    public function _init_(): void {
        $this->has_many = ['project_groups'];
        $this->validate = [
            'presence_of' => [
                ['field' => 'name', 'message' => 'El nombre es obligatorio'],
            ],
            'unique' => [
                ['field' => 'name', 'message' => 'Ya existe un proyecto con ese nombre'],
            ],
        ];
        $this->before_save = ['sanitizeName', 'sanitizeDescription', 'sanitizeRepositoryUrl', 'validateType'];
    }

    public function sanitizeName(): void {
        $this->name = htmlentities(trim((string) $this->name), ENT_QUOTES, 'UTF-8', false);
    }

    public function sanitizeDescription(): void {
        empty($this->description) or ($this->description = htmlentities(trim($this->description), ENT_QUOTES, 'UTF-8', false));
    }

    public function sanitizeRepositoryUrl(): void {
        empty($this->repository_url) or ($this->repository_url = htmlentities(trim($this->repository_url), ENT_QUOTES, 'UTF-8', false));
    }

    public function validateType(): void {
        $validTypes = ['backend', 'frontend', 'library', 'mobile'];
        in_array($this->type, $validTypes, true)
            or $this->_error->add(['field' => 'type', 'message' => 'El tipo debe ser uno de: ' . implode(', ', $validTypes)]);
    }
}
```

> `validateType()` es a mano porque, confirmado contra
> `ActiveRecord::_ValidateOnSave()` real, el framework solo tiene
> cuatro claves de validación: `email`, `numeric`, `unique`,
> `presence_of` — no existe "inclusión en lista".

### `app/models/group.php`

Mismo patrón: propiedad `name`, `has_many = ['project_groups']`,
`presence_of`/`unique` en `name`, `sanitizeName()` en `before_save`.

### `app/models/project_group.php` (pivote) — confirmado necesario

```php
<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class ProjectGroup extends ActiveRecord {

    public ?int $project_id = null;
    public ?int $group_id   = null;

    public function _init_(): void {
        $this->belongs_to = ['project', 'group'];
    }
}
```

**`has_many_and_belongs_to` confirmado no funcional para este caso**
— bug real en `Core_General_Class::__call()`: su condición de
comparación de clase (`$classFromCall == get_class($this)`) solo
puede cumplirse en relaciones auto-referenciadas (el caso original
que motivó la corrección del nombre de esta relación, al principio de
este spec). Para `Project`↔`Group` (clases distintas) nunca filtra
nada. La navegación real es `$project->project_groups()` (vía
`has_many`) y desde ahí `->project()`/`->group()` (vía `belongs_to`
en la pivote) — por eso `ProjectGroup` sí necesita existir como
archivo real, contradiciendo la nota original de `tasks.md`.

## Asignación de Grupos — acción dedicada, no el CRUD genérico

**Decisión (corrige el Requisito 6 original de `requirements.md`):**
el mecanismo genérico (`_create_reg()`/`_update_reg()` de
`AdminBaseTrait`) solo sabe guardar el modelo principal — no
sincronizar una tabla pivote a partir de una selección múltiple. En
vez de tocar el trait compartido (alto impacto, afecta
`groups`/`events`/`workflow_*` también), se usa una acción dedicada,
mismo patrón ya usado para el disparo manual de `ejecucion-workflows`
(`executeworkflowAction()`, fuera del CRUD mágico):

```php
public function saveprojectAction(): void {
    $data = $_POST['project'] ?? [];
    $groupIds = $data['groups'] ?? [];
    unset($data['groups']); // no es una columna de projects

    $project = empty($data['id'])
        ? $this->Project->Niu($data)
        : $this->Project->Find((int) $data['id']);

    if (!empty($data['id'])):
        foreach ($data as $field => $value):
            $project->$field = $value;
        endforeach;
    endif;

    $project->Save()
        or throw new ControllerException((string) $project->_error, HTTP_422);

    // Sync simple: borrar todas las filas pivote existentes de este
    // proyecto y recrearlas desde la selección actual — más simple
    // que diffear altas/bajas, y la escala (pocos grupos por
    // proyecto) no justifica la complejidad de un diff real.
    $existing = $this->ProjectGroup->Find(['conditions' => [['project_id', $project->id]]]);
    foreach ($existing as $pg):
        $pg->Delete();
    endforeach;

    foreach ($groupIds as $groupId):
        $this->ProjectGroup->Niu([
            'project_id' => $project->id,
            'group_id'   => (int) $groupId,
        ])->Save();
    endforeach;

    $this->_response['message'] = 'Proyecto guardado satisfactoriamente';
    $this->setResponseCode(HTTP_200);
    $this->respondToAJAX(json_encode($this->_response));
}
```

> **Corregido tras verificación contra el código real:**
> `_create_reg()`/`_update_reg()` de `AdminBaseTrait` no usan
> `Find()`+asignación campo a campo como proponía el snippet
> original — ambos llaman `Niu($array)` con el array completo
> (incluyendo `id` cuando existe), dejando que `Save()` detecte
> INSERT vs. UPDATE por la presencia de `id`. Esto simplifica
> `saveprojectAction()`: no hace falta la rama
> `empty($data['id']) ? Niu() : Find()` — `Niu($data)` sola resuelve
> ambos casos, igual que el resto del CRUD genérico del proyecto.

El formulario (`admin/project_addedit.phtml`) apunta su `dmb-form` a
esta acción (`action="/admin/saveproject"`), no a la ruta genérica del
CRUD. `'saveproject'` **no** se agrega a `$this->_actions` — así
`_prepare_data()` nunca la intercepta, corre como una acción normal de
controlador, con el `before_filter()` estándar (sesión + CSRF reales,
a diferencia del webhook público de `ejecucion-workflows`).

**Listar y eliminar Proyectos siguen usando el mecanismo genérico sin
cambios** — solo crear/editar necesita el bypass. Para que eliminar un
proyecto limpie su pivote automáticamente, confirmar si
`$this->dependents = 'destroy';` en el modelo `Project` (con
`has_many = ['project_groups']` ya declarado) cascada la eliminación
de forma nativa — `dumbophp-models.md` documenta esa opción, no
confirmado que aplique aquí sin probarlo.