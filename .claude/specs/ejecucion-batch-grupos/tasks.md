
## Notas de implementación (2026-10-04)

- Tarea 2: `ExecuteWorkflowCommand` acepta cualquier string, pero
  `WorkflowExecution::validateTriggerType()` rechazaba `'batch'` — se agregó a la
  lista cerrada.
- `saveprojectAction()` borra y recrea el pivote en cada edición: sin cambio
  habría perdido la asignación batch. Ahora la preserva y valida (antes de
  guardar nada) que el workflow batch sea del propio Proyecto.
- `dmb-button-action` (`behavior="ajax"`) hacía `appModel.url = url` (pisaba el
  método) → el botón "Ejecutar" recibía HTML. Corregido a `appModel.url(url)`.
- `dumbo migration reset project_groups` falla en MySQL (Remove_All_indexes
  intenta borrar dos veces el índice compuesto); se completó con `down` + `up`.
- Tarea 10 verificada por UI real contra la BD de dev; tests en
  `tests/testExecuteGroup.php`.
