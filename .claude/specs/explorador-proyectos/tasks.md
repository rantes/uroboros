# Tareas de implementación — Explorador de Eventos

## Implementación

- [x] 1. **Resuelto:** `'events'` → `Event` vía `Singulars()`/
      `Camelize()`, sin caso especial — confirmado contra
      `admin_base_trait.php` real.
- [ ] 2. Agregar `protected array $_readOnlyModels = [];` y el método
      `_guardReadOnly()` a `AdminBaseTrait` (ver `design.md`)
- [ ] 3. Agregar `$this->_guardReadOnly();` como primera línea de
      `_create_reg()`, `_update_reg()`, `_delete_reg()` — y
      opcionalmente `_add_reg()`/`_edit_reg()`
- [ ] 4. En `AdminController`: `protected array $_readOnlyModels = ['event'];`
      y agregar `'events'` a `$this->_actions`
- [ ] 5. Verificar con tests reales (no solo lectura de código) que:
      - `POST`/`PUT`/`DELETE` a `/admin/events` responde `HTTP_405`,
        sin escribir nada en la tabla `events`
      - `POST`/`PUT`/`DELETE` a `/admin/projects` y `/admin/groups`
        **siguen funcionando igual que antes** — el guard no debe
        afectar entidades fuera de `$_readOnlyModels`
- [ ] 6. Verificar que el listado (`GET /admin/events`) renderiza
      `aggregate_type`, `aggregate_id`, `event_type`, `created_at`,
      ordenado por más reciente primero, con paginación

## Cierre

- [ ] 7. Confirmar visualmente que el link "Eventos" del sidebar
      (`dashboard-shell`) apunta a la ruta real y queda activo (ya no
      `disabled`)
- [ ] 8. `dumboTest all` — cero regresión, usando el conteo de
      `test-result.xml`, no el resumen de consola. Poner atención
      especial a los tests existentes de `AdminController`
      (`projects`/`groups`) dado que se modificó un trait compartido