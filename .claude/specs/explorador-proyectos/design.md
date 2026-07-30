# Diseño técnico — Explorador de Eventos

## Alcance

Configuración sobre infraestructura ya existente — no hay modelo,
migración ni controlador nuevo desde cero. Se integra `events` al
mismo mecanismo genérico que ya expone `projects`/`groups` en
`AdminController`/`AdminBaseTrait`.

## Cambio principal

Agregar `'events'` al array de acciones ya existente en
`AdminController`:

```php
$this->_actions = [
    'projects',
    'groups',
    'events',
];
```

Confirmado contra `admin_base_trait.php` real: `_prepare_data()`
resuelve `Singulars('events')` → `'event'` → `Camelize()` → `'Event'`
— mapea limpio a `App\Models\Event`, sin caso especial (el inglés ya
singulariza `events`→`event` de forma regular, igual que
`projects`→`project`).

## Hallazgo crítico — el supuesto original de "solo lectura por omisión" era falso

Los seis métodos de CRUD (`_list_regs`, `_edit_reg`, `_add_reg`,
`_delete_reg`, `_update_reg`, `_create_reg`) están definidos **una
sola vez en el trait**, compartidos por todo el controlador que lo
usa — no existe una versión "por entidad" que se pueda omitir. Actúan
genéricamente sobre `$this->_model_camelized` en tiempo de ejecución.
Agregar `'events'` a `$this->_actions` sin ningún cambio adicional
**expondría escritura completa sobre `Event`** vía
`POST`/`PUT`/`DELETE` a `/admin/events` — rompiendo el Requisito 1.2
del núcleo OEM ("el modelo no debe exponer ningún método de
actualización con sentido de negocio").

## Corrección — lista de modelos de solo lectura en el trait

Modificación centralizada y mínima a `AdminBaseTrait` — nueva
propiedad protegida, vacía por defecto (cero efecto sobre
`Project`/`Group`), chequeada al inicio de los tres métodos que
mutan datos:

```php
protected array $_readOnlyModels = [];

private function _guardReadOnly(): void {
    in_array($this->_model, $this->_readOnlyModels)
        and throw new ControllerException('Este recurso es de solo lectura', HTTP_405);
}
```

Se agrega `$this->_guardReadOnly();` como primera línea de
`_create_reg()`, `_update_reg()` y `_delete_reg()` (los tres que
llaman `->Save()`/`->Delete()`). Opcionalmente también en
`_add_reg()`/`_edit_reg()` — no mutan datos por sí solos (solo
renderizan el formulario), pero no tiene sentido mostrar un
formulario de algo que nunca se va a poder guardar.

En `AdminController`:

```php
protected array $_readOnlyModels = ['event'];
```

> **Por qué modificar el trait compartido en vez de un controlador
> aparte para Events:** un controlador separado evitaría tocar
> `AdminBaseTrait`, pero duplicaría la lógica de paginación/búsqueda/
> render de `_list_regs()` que sí queremos reutilizar. El guard es
> quirúrgico (una propiedad + 3 líneas), no cambia el comportamiento
> de `Project`/`Group` (array vacío = sin efecto), y deja la puerta
> abierta a que cualquier futuro modelo de solo lectura (ej. algo de
> Health Management más adelante) lo reutilice sin duplicar nada.
> **Verificar con el agente:** que agregar esta propiedad/método al
> trait no rompe ningún test existente de `AdminController` — no
> asumido, confirmar con `dumboTest all` después del cambio.

## Vista

Sin vista nueva si el mecanismo genérico renderiza el listado por
introspección de campos del modelo (mismo comportamiento ya usado
para `Project`/`Group`, según lo establecido en `gestion-proyectos`).
Si `Event` necesita una vista específica por tener un campo `payload`
JSON que no se auto-formatea bien, es una extensión menor, no un
bloqueo — ver "Fuera de alcance" en `requirements.md`.

## Sidebar

Ya actualizado en `.claude/specs/dashboard-shell/design.md` — el link
apunta a `/admin/events` de forma ilustrativa; ajustar si la ruta real
que resuelve el mecanismo genérico para esta acción es distinta.