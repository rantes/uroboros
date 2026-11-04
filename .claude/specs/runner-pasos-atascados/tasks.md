# Tareas de implementación

- [x] 0. Fase 0 — reproducida en dev (MySQL estricto): el paso 81 queda `running`, `Save()` lanza excepción `SQLSTATE[22001]` (no devuelve false). En dev el error sí llega a stderr; al log solo llega por la redirección `>> ... 2>&1` del cron.
- [x] 1. Constantes en `config/host.php`
- [x] 2. Helper `StepOutput_Helper.php` (truncado + UTF-8)
- [x] 3. `FailStepCommand` + `FailStepCommandHandler`
- [x] 4. `RunStepCommandHandler`: try/catch Throwable, reintento mínimo, límite de salida, timeout
- [x] 5. `WorkflowRunnerController`: log explícito, flock, detector, try/catch por paso
- [x] 6. Tests
- [x] 7. Verificación en dev 1–7
- [x] 8. Limpieza de datos de prueba

## Ajustes posteriores

- [x] 9. Ajuste 1: investigar herencia del fd del lock (inherited por exec y proc_open; fd 5/7)
- [x] 10. Ajuste 1: `_runCommand()` con `proc_open` y override del fd del lock a `/dev/null`
- [x] 11. Ajuste 1: verificación `kill -9` con huérfano vivo + nueva pasada; dos runners; escapado; timeout
- [x] 12. Ajuste 2: reproducir (ejecución `running` tras fallo posterior al guardado del paso)
- [x] 13. Ajuste 2: `FailWorkflowCommand.reason` + `_failOrphanExecutions()`
- [x] 14. Tests (lock, escapado, huérfana vieja/reciente/actividad reciente/paso activo/rollback) y `dumboTest all`
- [x] 15. Limpieza de datos de prueba
