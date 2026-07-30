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