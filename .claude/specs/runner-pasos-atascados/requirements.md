# Runner: pasos que quedan "Running" para siempre

## Introducción

Un paso cuyo comando produce más de 65.535 bytes de salida hace que
`StepExecution::Save()` lance `SQLSTATE[22001] Data too long` (MySQL
estricto, `output` es `TEXT`). `RunStepCommandHandler` no lo captura: el
paso queda `running`, la ejecución queda `running`, y el error se pierde.

## Requisitos

### 1. Ningún fallo deja un paso en `running`
DADO un fallo en cualquier punto de `RunStepCommandHandler::Handle()`
CUANDO el handler termina ENTONCES el paso queda `failed` (vía
`FailStepCommand` → evento `StepFailed` → `FailWorkflowCommand`).

### 2. Límite de salida
> **Actualizado por `salida-pasos-en-archivos`:** el límite de 60.000 bytes en la BD
> ya no existe (`STEP_OUTPUT_MAX_BYTES` se eliminó). La salida completa va a un
> archivo (tope `STEP_LOG_FILE_MAX_BYTES`, 5 MB) y la BD guarda solo un extracto
> (`output_tail`, `STEP_OUTPUT_TAIL_BYTES`). El requisito de abajo se conserva
> como historia; el criterio vigente es el de ese spec.

DADO una salida > `STEP_OUTPUT_MAX_BYTES` CUANDO se persiste ENTONCES se
conserva inicio + final con marcador intermedio, UTF-8 válido, y las
credenciales se enmascaran ANTES de truncar.

### 3. Timeout por paso
DADO un comando que excede `STEP_TIMEOUT_SECONDS` CUANDO corre ENTONCES
el SO lo mata (`timeout --kill-after`), el paso falla con
"Tiempo agotado tras N s" y no quedan procesos huérfanos.

### 4. Un solo runner y detector de atascados
DADO que ya hay un runner activo CUANDO arranca otro ENTONCES sale en
silencio (flock). DADO un paso `running` por más de
`STEP_TIMEOUT_SECONDS + STEP_STUCK_GRACE_SECONDS` CUANDO inicia una pasada
ENTONCES pasa a `failed` por el camino normal de comando/evento.

### 5. El runner registra sus propios errores
Excepciones del runner van a `tmp/logs/workflow_runner.log` sin depender
de `php.ini`.

## Fuera de alcance
Salida incremental, timeout configurable por paso, cambio de tipo de
columna, limpieza de datos de producción, OOM del droplet.

## Ajustes posteriores

### 6. El comando del paso no hereda el lock del runner
DADO que el runner muere con `kill -9` mientras un comando de paso sigue
vivo CUANDO arranca otra pasada ENTONCES adquiere el lock y corre los
detectores aunque el huérfano siga vivo (el hijo no tiene el fd del lock).

### 7. Ejecuciones `running` sin pasos activos
DADO una `WorkflowExecution` `running` sin pasos `pending`/`running` y con
última actividad más vieja que `STEP_STUCK_GRACE_SECONDS` CUANDO inicia una
pasada ENTONCES pasa a `failed` ("Interrumpido: la ejecución quedó sin
pasos activos") por `FailWorkflowCommand`. Ejecuciones recientes, con pasos
activos (incluidas las de rollback) o con un paso terminado recientemente
no se tocan.
