# Tareas de implementación — Explorador de Eventos

## Implementación

- [x] 1. **Resuelto:** `'events'` → `Event` vía `Singulars()`/
      `Camelize()`, sin caso especial — confirmado contra
      `admin_base_trait.php` real.
- [x] 2. Agregado `protected array $_readOnlyModels = [];` y el
      método `_guardReadOnly()` a `AdminBaseTrait`.
- [x] 3. `$this->_guardReadOnly();` agregado a `_create_reg()`,
      `_update_reg()`, `_delete_reg()`, y también a
      `_add_reg()`/`_edit_reg()` (decisión del agente: no tiene
      sentido servir un formulario de algo que no se puede guardar).
- [x] 4. `AdminController`: `$this->_readOnlyModels = ['event'];`
      (en constructor, no como propiedad de clase — PHP rechaza
      redeclarar con default distinto al del trait) y `'events'`
      agregado a `$this->_actions`.
- [x] 5. Verificado con `curl` real: `POST`/`PUT`/`DELETE` a
      `/admin/events` responden `HTTP_405` con mensaje "Este recurso
      es de solo lectura"; tabla `events` confirmada sin filas nuevas.
- [x] 6. Listado verificado — `admin/event_list.phtml` creado
      (confirmado que no existía antes de crearlo), con las columnas
      requeridas y paginación.
- [x] 6b. **Implementado.** `Paginate()`→`Find()`→driver usan
       `isset()` sobre `'sort'`, no `!empty()` — una key `'sort'`
       presente con string vacío dispara `ORDER BY` sin nada detrás
       (SQL inválido, confirmado con error 1064 real). Corregido
       incluyendo la key `'sort'` en `$paginateParams` solo cuando
       `$sort` no está vacío. `project`/`group` verificados sin
       regresión — solo el warning preexistente de vista faltante,
       no relacionado. **Pendiente:** conteo limpio y completo de
       `test-result.xml` (el reportado, "7 aserciones", no cuadra con
       las ~195 de corridas anteriores y viene de una zona del
       reporte con texto corrompido — no se da por bueno sin
       confirmación).

## Cierre

- [x] 7. Confirmado — "Eventos" es un `<a href="/admin/events">` real
      en el sidebar, ya no `disabled`.
- [ ] 8. `dumboTest all` — cero regresión, usando el conteo de
      `test-result.xml`, no el resumen de consola. Poner atención
      especial a los tests existentes de `AdminController`
      (`projects`/`groups`) dado que se modificó un trait compartido