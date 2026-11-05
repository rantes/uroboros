# Diseño — Salida de pasos en archivos

## Visión general

```
exec comando → salida en memoria
   → enmascarar credenciales
   → StepLogStore->write(): truncar a 5 MB, UTF-8 válido, escribir tmp + rename, sha256, tamaño
   → StepExecution: output_path, output_size, output_checksum, output_tail (últimos 2 KB)
lectura: StepExecution → StepLogStore->resolve() (realpath dentro de la base) → verificar checksum → mostrar/descargar
retención: runner (tras lock + detectores) → StepLogStore->purge() por lotes + barrido de huérfanos
```

## Constantes (`config/host.php`)

| Constante | Valor | Uso |
|---|---|---|
| `STEP_LOG_BASE_PATH` | `INST_PATH . 'storage/step_logs'` (sobrescribible, absoluta) | Carpeta base |
| `STEP_LOG_FILE_MAX_BYTES` | 5242880 | Tope del archivo |
| `STEP_OUTPUT_TAIL_BYTES` | 2048 | Extracto en BD |
| `STEP_LOG_VIEW_MAX_BYTES` | 262144 | Máximo mostrado en la vista |
| `STEP_LOG_RETENTION_DAYS` | 60 | Retención |
| `STEP_LOG_PURGE_BATCH` | 200 | Lote de purga por pasada |

`STEP_OUTPUT_MAX_BYTES` (60.000) deja de ser el límite de la BD. Se elimina o se reutiliza solo si algún consumidor lo sigue necesitando; el agente lo decide tras la búsqueda de usos y lo reporta.

## Componentes

### `StepLogStore` (clase de servicio con instancia, sin métodos estáticos)

Vive en `app/helpers/` junto a `StepOutput_Helper` (seguir el estilo existente). Responsabilidades:

- `write($executionId, $stepId, $maskedOutput)` → devuelve `['path','size','checksum','tail','error']`. Nunca lanza hacia el llamador: captura y devuelve `error`.
- `resolve($relativePath)` → ruta absoluta validada con `realpath` dentro de la base, o `null`.
- `verify($relativePath, $checksum)` → bool.
- `readForView($relativePath)` → contenido recortado a `STEP_LOG_VIEW_MAX_BYTES`.
- `ensureBase()` → crea la base si falta (modo 2775) y comprueba escritura. Mismo criterio que la verificación del directorio de trabajo de proyectos: crear si no existe; si no hay permisos, exponer el error.
- `purge($limit)` y `sweepOrphans($limit, $existsCallback)`.

Reutiliza `truncateStepOutput()` y `sanitizeUtf8()` de `StepOutput_Helper`, parametrizando el máximo y la longitud de cabecera. El extracto `output_tail` se corta por bytes desde el final y se sanea (el corte puede caer a mitad de un carácter multibyte).

### Esquema (migración)

`step_executions`: quitar `output`; añadir `output_path VARCHAR(255) NULL`, `output_size INT UNSIGNED NOT NULL DEFAULT 0`, `output_checksum CHAR(64) NULL`, `output_tail TEXT NULL`. Seguir `migrations.md`; el `down()` restaura `output`. Todo lector de `output` (handler, controladores, vistas, Cockpit, tests, fixtures) se actualiza. Como la BD se vacía antes de desplegar, no hay migración de datos.

### Handler (`RunStepCommandHandler`)

Tras ejecutar el comando y enmascarar: llamar a `StepLogStore->write()`. Si devuelve `error`, `output_tail` lleva la nota ("No se pudo guardar el log completo: …") + los últimos bytes de la salida en memoria, y el error va al log del runner. Todo dentro del try/catch existente: el paso siempre se cierra. El reintento con salida mínima del spec anterior se conserva para fallos de `Save()`.

### Controlador de logs

Acciones nuevas (con try/catch, retorno único, sesión requerida, sin excepciones de `exceptsBeforeFilter`): `view` (log escapado, con aviso de checksum) y `download`. Los ids llegan como enteros validados; la ruta sale solo de la BD. Seguir `dumbophp-controllers.md` y `views.md`; la UI según `design-guide.md` y los componentes `dmb-*`.

### Retención

`workflow_runner_controller` llama a `purge` y `sweepOrphans` después de los detectores. Ambos acotados por lote y con try/catch por elemento. La fecha de referencia es la de fin del paso (`completed_at`; si es NULL, `updated_at`).

## Permisos

`umask` local alrededor de la creación o `chmod` explícito: archivos 0664, directorios 2775. En producción el cron corre como `www-data`, así que usuario y servidor web coinciden. En dev hay dos usuarios: la carpeta base debe quedar `usuario:www-data` con 2775, igual que el directorio de pruebas de proyectos.

## Seguridad

- El contenido del log es no confiable: siempre escapado en HTML; en descarga, `text/plain` + `nosniff`.
- Path traversal: ruta solo desde ids propios + `realpath` con prefijo de la base; rechazar si no coincide.
- El checksum detecta alteración o corrupción accidental, no es una firma: quien pueda escribir el archivo también podría editar la fila. Documentarlo.
- Credenciales: enmascaradas antes de escribir; el archivo no es una fuente de secretos.

## Pruebas

SQLite en memoria para la lógica y una carpeta temporal por test para archivos. El límite de columnas MySQL ya no aplica. Verificación de punta a punta en dev con MySQL real.

## Notas de implementación (desviaciones respecto al diseño)

- `StepLogStore` es `App\Helpers\StepLogStore` (`app/helpers/step_log_store.php`), autocargable; no un `*_Helper.php` de funciones. La base se inyecta en el constructor (tests).
- La parte de BD de la purga (seleccionar pasos vencidos, vaciar `output_path`) vive en `WorkflowRunnerController`; el almacén expone `delete()` y `sweepOrphans()` y no conoce modelos.
- Tipos de migración: `output_checksum` es `VARCHAR(64)` y `output_size` `INTEGER` (el generador de migraciones no soporta `CHAR` ni `UNSIGNED`).
- `STEP_OUTPUT_MAX_BYTES` y `STEP_OUTPUT_HEAD_BYTES` se eliminaron. `truncateStepOutput()`/`sanitizeUtf8()` se reutilizan con límites por parámetro; `sanitizeUtf8()` ahora tiene camino rápido (`preg_match('//u')`) y sanea por trozos para soportar archivos de MB.
- `ActiveRecord::Save()` ignora propiedades `null`: "sin archivo" tras la purga se escribe como `''`; las consultas usan `output_path <> ''`.
- En tests `STEP_LOG_BASE_PATH` apunta a `/tmp/uroboros_step_logs_test` (`APP_ENV==='test'`); el framework solo renderiza una página con layout por proceso (`include_once`), así que los tests de HTML renderizan la plantilla con `Closure::call`.
- `output_size` de un paso cerrado por `FailStepCommand` es 0 (no hay archivo, solo el motivo en `output_tail`).
- El barrido también retira carpetas VACÍAS de ejecuciones vivas (p. ej. archivo borrado a mano).
