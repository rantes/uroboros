# Diseño técnico — Runner: pasos atascados

- `config/host.php`: constantes `STEP_TIMEOUT_SECONDS`, (`STEP_OUTPUT_MAX_BYTES` y
  `STEP_OUTPUT_HEAD_BYTES` se eliminaron en `salida-pasos-en-archivos`: ahora
  `truncateStepOutput()` recibe el máximo y la cabecera por parámetro),
  `STEP_TIMEOUT_KILL_AFTER_SECONDS`, `STEP_STUCK_GRACE_SECONDS`,
  `STEP_RUNNER_LOG`, `STEP_RUNNER_LOCK_FILE`.
- `app/helpers/StepOutput_Helper.php`: `truncateStepOutput()` y
  `sanitizeUtf8()` (PCRE sin `/u`, sin mbstring ni iconv).
- `FailStepCommand` + `FailStepCommandHandler`: único camino para cerrar
  un paso como `failed` fuera del flujo normal (catch de Handle y
  detector). Idempotente: solo actúa sobre pasos `pending`/`running`.
- `RunStepCommandHandler::Handle()`: envuelve `_execute()` en
  try/catch(Throwable); el guardado final reintenta una vez con salida
  mínima; comando externo vía `timeout --kill-after`.
- `WorkflowRunnerController::processpendingAction()`: flock, log
  explícito, detector de atascados, try/catch por paso, lista de ids ya
  intentados (garantiza progreso del `while`).

## Decisiones
- Timestamp de inicio del paso: `started_at` (se asigna en el mismo
  `Save()` que pone `status='running'`); fallback `updated_at` si fuera
  NULL.
- (Reemplazado por el Ajuste 1) el hijo ya no hereda el lock.

## Ajuste 1 — lock no heredable
- PHP abre el archivo del lock sin close-on-exec: `exec()` y `proc_open()`
  pasan el fd al hijo (verificado: fd 5/7 visible en `/proc/<pid>/fd` del
  hijo). Un hijo huérfano tras `kill -9` retenía el flock.
- Descartado `N>&-` en el shell: `sh` es dash y solo admite fd de un dígito.
- Elegido: `proc_open` con descriptor de reemplazo `N => /dev/null` para cada
  fd propio que apunte a `STEP_RUNNER_LOCK_FILE` (descubierto vía
  `/proc/self/fd`, solo Linux; sin `/proc` no hay override). Funciona con
  cualquier número de fd, sin `pcntl`/`posix`. stdin del hijo pasa a
  `/dev/null`. Otros fds heredados (p. ej. el socket de BD) no se tocan.
- `RunStepCommandHandler::_runCommand()` / `_lockDescriptors()`.

## Ajuste 2 — ejecuciones huérfanas
- `FailWorkflowCommand` gana `?string $reason`, que viaja en el payload de
  `WorkflowFailed` (sin cambio de esquema).
- `WorkflowRunnerController::_failOrphanExecutions()`, tras `_failStuckSteps()`.
- Criterio: `status='running'`, sin pasos `pending`/`running`, y última
  actividad = mayor(`updated_at` de la ejecución, `completed_at` del último
  paso) < ahora − `STEP_STUCK_GRACE_SECONDS` (300 s). No se añadió constante:
  la referencia es la última actividad (no el inicio), así que la gracia
  basta; usar timeout+gracia retrasaría 35 min el cierre sin ganar
  seguridad.
- "Entre pasos" vs "huérfana": el siguiente paso se encola en el mismo
  proceso y en ms (StepCompleted → reacción → QueueStep); mientras una
  ejecución avanza siempre existe un paso `pending`/`running`. Pasos futuros
  aún no creados no cuentan como "activos", pero esa ventana dura ms y el
  runner único (flock) no puede observarla.
- Rollback no es una fase aparte: es una ejecución normal de otro Workflow
  (pasos `type='rollback'`), y se rige por la misma regla.
- Consulta en dos pasos (SQL portable, sin GREATEST) para SQLite y MySQL.
