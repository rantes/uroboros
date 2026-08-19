# Tareas de implementación — Ejecución de Workflows

## Verificación previa (no asumida)

- [x] 1. **Confirmado.** Ninguna columna propuesta es palabra
      reservada de MySQL 8.0 — verificado contra documentación oficial
      y de forma empírica (las 4 migraciones corrieron sin error
      contra MySQL real). `trigger_type`/`exit_code` no chocan con
      `TRIGGER`/`EXIT` (identificadores compuestos distintos).

## Migraciones y modelos

- [x] 2. `workflow_definitions` — migración + modelo, escritos a mano
      (evitando los bugs ya conocidos del generador CLI).
- [x] 3. `workflow_step_definitions` — ídem, `validateType()` a mano.
- [x] 4. `workflow_executions` — ídem, `validateStatus()`/
      `validateTriggerType()` a mano.
- [x] 5. `step_executions` — ídem, índice compuesto
      `(workflow_execution_id, status)` para la futura consulta del
      controlador de background.
- [x] 6. Las 4 migraciones ejecutadas contra MySQL real.
- [x] 7. Las 4 tablas agregadas a `tests/testTables.php`.

**`dumboTest all`:** 22 tests, 99 assertions (nodo raíz, confiable),
0 fallos.

## ⚠️ Corrección pendiente sobre lo ya implementado en esta parte

- [ ] 7b. El índice de `step_executions` se implementó como
      `(workflow_execution_id, status)` — el orden correcto es
      `(status, workflow_execution_id)`, porque el controlador de
      background consulta *solo* por `status='pending'`, sin filtrar
      por `workflow_execution_id`. Corregir la migración (requiere
      `dumbo migration reset step_executions`, según la convención
      del proyecto para cambios de esquema en tablas existentes) antes
      de la Parte 3.
- [ ] 7c. Agregar `'custom'` a la lista cerrada de `type` en
      `WorkflowStepDefinition::validateType()`.

## Commands, Handlers, Reactions

- [x] 8. `ExecuteWorkflowCommand` + Handler.
- [x] 9. `QueueStepCommand` + Handler.
- [x] 10. `RunStepCommand` + Handler — implementado completo
       (incluye `status='running'`+`started_at` antes de ejecutar, no
       explícito en `design.md` original pero necesario para que esos
       campos del esquema tengan sentido), probado de forma aislada,
       sin conectar a ningún flujo automático todavía.
- [x] 11. `CompleteWorkflowCommand` + Handler.
- [x] 12. `FailWorkflowCommand` + Handler.
- [x] 13. `OnWorkflowStartedReaction`.
- [x] 14. `OnStepCompletedReaction` — usa `$nextStep->counter() === 0`
       para el chequeo de ausencia, no `empty()` (mismo patrón ya
       confirmado con `OemMetric::Increment()` — `Find()` nunca
       devuelve `null`/`false`).
- [x] 15. `OnStepFailedReaction`.
- [x] 16. Registradas en `config/reactions_map.php`, con comentario
       explicando por qué `StepQueued`/`WorkflowCompleted`/
       `WorkflowFailed` no tienen entrada todavía.

**`dumboTest all`:** 26 tests, 126 assertions, 0 fallos (incluye 3
tests adicionales de `RunStepCommandHandler` en aislamiento, agregados
por cobertura de ramas — justificado contra la regla del 98% de
`CLAUDE.md`, no gratuito).

## ✅ Corrección aplicada sobre lo ya implementado en esta parte

- [x] 7b. Índice corregido a `(status, workflow_execution_id)`.
      Encontrado de paso: `Remove_All_indexes()` tiene un bug real —
      itera una vez por *columna* del índice compuesto en vez de una
      vez por índice, e intenta un segundo `DROP INDEX` sobre algo que
      el primero ya eliminó. Resuelto corriendo solo `up()` (la tabla
      estaba vacía, sin riesgo de datos). **Pendiente evaluar si vale
      la pena reportar este bug a `DumboPHP` como se hizo con los
      otros — no urgente, hay workaround conocido.**
- [x] 7c. `'custom'` agregado a la lista cerrada de `type`.

## Disparo manual y webhook

- [x] 19. Acción de disparo manual (`AdminController::executeworkflowAction()`)
      — responde `202` con mensaje honesto sobre la latencia real.
- [x] 20. `WebhookController::triggerAction()` — `HTTP_401` con token
      inválido, `202` con token válido. **Verificado con `curl` real,
      no solo `_runAction()`** — ver hallazgo crítico abajo.
- [x] 21. Botón de disparo manual agregado, junto con un CRUD mínimo
      de `WorkflowDefinition` (necesario para tener dónde ponerlo —
      no existía ninguna vista de esa entidad todavía).

## Controlador de background y cron

- [x] 17. `WorkflowRunnerController::processpendingAction()` con el
      bucle `while` — verificado procesando una cadena completa de 3
      pasos y dos `WorkflowExecution` en paralelo (encoladas
      simultáneamente, procesadas secuencial) en una sola invocación.
- [x] 18. Cron cada minuto, confirmado:
      `* * * * * cd .../uroboros && dumbo run workflow_runner/processpending >> tmp/logs/workflow_runner.log 2>&1`

## ⚠️ Hallazgo crítico — no específico de este spec

`http_response_code($this->_code)` (el patrón documentado en
`code-conventions.md`) se pisa después por el pipeline real del
framework (`parseContent()` usa `$this->_http_response_code`, propiedad
privada que solo `setResponseCode()` actualiza). El código HTTP real
emitido al cliente no es el que el patrón documentado produce.
**`_runAction()` no detecta esto** — lee `$result->_code` (pública)
directamente, no el HTTP real. Confirmado con `curl` real: antes del
fix, tanto token válido como inválido devolvían `200`; después,
`401`/`202` correctamente. Ver prompt de auditoría aparte — esto
puede afectar cualquier controlador existente que siga el patrón
documentado, no solo los de este spec.

**`dumboTest all`:** 34 tests, 148 assertions, 0 fallos.

## Gestión de pasos (CRUD plano — decisión confirmada)

- [x] 22. `workflow_step_definitions` en `AdminController` — mismo
       controlador que el resto, sin razón para separarlo. Filtrado
       por `workflow_definition_id` vía `$this->_listConditions`
       (mecanismo ya existente en `AdminBaseTrait`, no nuevo).
- [x] 23. Vista de gestión de pasos, filtrada por parámetro — nunca
       lista global mezclando steps de distintos workflows.

## Solo lectura de ejecuciones (Requisito 5)

- [x] 24. `WorkflowExecution`/`StepExecution` agregadas a
       `$_readOnlyModels` — confirmado sin cambios al guard
       (`in_array()` ya soportaba N elementos desde su
       implementación original en `explorador-eventos`).
- [x] 25. Vistas de listado, solo lectura, verificadas con `curl` real
       contra MySQL (200, sin warnings, relaciones `belongs_to`
       incluidas).

## Verificación end-to-end

- [x] 26. Test de éxito — ya existía desde la Parte 2/3, se le agregó
       la aserción de orden que faltaba (cada `StepExecution`
       individualmente `completed`, orden de ejecución coincide con
       `step_order`).
- [x] 27. Test de fallo — ya existía con la aserción negativa fuerte
       exacta pedida (el paso posterior nunca se crea).
- [x] 28. Test de webhook — ya existía, 4 tests, verificado por query
       real a la tabla.
- [x] 29. **`dumboTest all`:** 43 tests, 165 assertions, 0 fallos
       (antes de esta parte: 34/148 — la diferencia son los 9 tests
       nuevos del guard + las aserciones agregadas al test de éxito).

## Cierre

- [ ] 30. Confirmar con el usuario si "Active Operations"/"Event
       Timeline" del dashboard ya pueden conectarse a estas tablas de
       proyección, o si eso es un spec de UI aparte