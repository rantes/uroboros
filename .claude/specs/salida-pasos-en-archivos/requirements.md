# Requisitos — Salida de pasos en archivos

## Introducción

Hoy la salida de cada paso (incluida la cabecera verbose `[Uroboros]`) se guarda en `step_executions.output` (`TEXT`, máx. 65.535 bytes). Eso obliga a truncar a 60.000 bytes, hace crecer la BD y complica la retención. Este spec mueve la salida completa a archivos en disco y deja en la BD solo el puntero, los metadatos, un checksum y un extracto corto.

Decisiones ya tomadas por el usuario:

- La BD guarda un **checksum** del archivo.
- **No se migra nada**: la BD de dev y la de producción se vacían antes de desplegar. No hay lectura dual ni exportación de salidas viejas.
- Retención por antigüedad: **60 días**.
- Ruta de los logs **configurable** en `config/host.php`.

## Requisitos

### 1. Almacenamiento en archivo

1.1 Al terminar un paso (completed o failed), la salida se escribe en un archivo: `{STEP_LOG_BASE_PATH}/{workflow_execution_id}/{step_execution_id}.log`.
1.2 La ruta se construye solo a partir de ids enteros del sistema. Nunca de texto del usuario.
1.3 La BD guarda la ruta **relativa** a `STEP_LOG_BASE_PATH` (`{execution_id}/{step_id}.log`), para poder mover la carpeta base sin tocar datos.
1.4 La escritura es atómica: archivo temporal en el mismo directorio y `rename()`.
1.5 Las credenciales se enmascaran (`***`) ANTES de escribir al disco. El archivo nunca contiene un secreto.
1.6 El archivo se limita a `STEP_LOG_FILE_MAX_BYTES` (5 MB). Si se excede, se conserva cabecera y final con marcador de bytes omitidos (reutilizar la lógica de `truncateStepOutput`, parametrizada). El contenido guardado es UTF-8 válido.

### 2. Metadatos en la BD

2.1 `step_executions` reemplaza `output` por: `output_path` (nullable), `output_size` (bytes del archivo escrito), `output_checksum` (SHA-256 en hexadecimal, 64 caracteres, nullable) y `output_tail` (extracto del final, máx. `STEP_OUTPUT_TAIL_BYTES` = 2048 bytes, ya enmascarado y UTF-8 válido).
2.2 El checksum se calcula sobre los bytes exactos del archivo escrito.
2.3 `output_tail` se llena siempre, incluso cuando el archivo no se pudo escribir (en ese caso incluye una nota de por qué).
2.4 La lista de ejecuciones, el Cockpit y las tarjetas usan `output_tail`; no abren archivos.

### 3. Robustez

3.1 Un fallo al escribir el archivo (directorio inexistente, sin permisos, disco lleno) NO deja el paso en `running`. El paso se cierra con su estado real, `output_path` queda `NULL`, `output_tail` explica el fallo, y el error va al log del runner.
3.2 Se mantiene todo lo del spec `runner-pasos-atascados` (try/catch en el handler, timeout, lock, detectores).
3.3 Al arrancar, el runner verifica que `STEP_LOG_BASE_PATH` exista y sea escribible; si no existe, intenta crearlo; si no puede, lo registra en el log del runner con un mensaje claro y los pasos siguen cerrándose según 3.1.

### 4. Lectura

4.1 La vista de ejecución muestra `output_tail` en línea y un enlace "Ver log completo" cuando hay archivo.
4.2 "Ver log completo" requiere sesión iniciada. La acción carga el `StepExecution` por id, construye la ruta desde `output_path`, resuelve `realpath` y verifica que quede dentro de `STEP_LOG_BASE_PATH` (sin path traversal ni symlinks fuera).
4.3 La vista de log muestra como máximo `STEP_LOG_VIEW_MAX_BYTES` (256 KB: inicio y final con marcador). El contenido se escapa para HTML (es texto no confiable).
4.4 Hay una acción de descarga del archivo completo: `Content-Type: text/plain; charset=utf-8`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`.
4.5 Integridad: al servir el archivo se recalcula el SHA-256 y se compara con `output_checksum`. Si no coincide, se muestra el contenido con una advertencia visible ("El archivo no coincide con su checksum") y se registra en el log del runner/aplicación. No se oculta el contenido.
4.6 Si el archivo no existe (purgado o perdido), la vista muestra `output_tail` y el mensaje "Archivo no disponible", sin error 500.

### 5. Retención

5.1 `STEP_LOG_RETENTION_DAYS = 60` en `config/host.php`.
5.2 El runner, tras adquirir el lock y correr los detectores, purga un lote acotado por pasada (`STEP_LOG_PURGE_BATCH` = 200 filas): pasos con `output_path` no nulo y fecha de fin anterior al umbral. Borra el archivo, pone `output_path = NULL` y conserva `output_size`, `output_checksum` y `output_tail`. Borra la carpeta de la ejecución si queda vacía.
5.3 Barrido de huérfanos: también por lotes, elimina carpetas de `STEP_LOG_BASE_PATH` cuyo `workflow_execution_id` ya no existe en la BD (por ejemplo tras borrar un proyecto en cascada). El borrado en cascada de la BD NO toca archivos (evita desincronizarse si la transacción hace rollback).
5.4 Un fallo de purga en un elemento no detiene la pasada ni el runner.

### 6. Permisos

6.1 Archivos con modo 0664 y directorios 2775 (setgid), de modo que el runner (cron) y el servidor web (`www-data`) puedan leer y escribir aunque sean usuarios distintos en dev.
6.2 La carpeta base vive fuera del webroot. Nunca se sirve de forma estática.
6.3 Por defecto, `STEP_LOG_BASE_PATH` apunta a `storage/step_logs` dentro del proyecto (ignorado por git) y puede sobrescribirse con una ruta absoluta.

## Fuera de alcance

Salida incremental/streaming mientras el paso corre, rotación de `workflow_runner.log` (se resuelve con `logrotate`), respaldo de la carpeta de logs, búsqueda dentro de logs, compresión.