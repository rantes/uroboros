# Tareas — Salida de pasos en archivos

- [x] 1. Reconocimiento: buscar todos los usos de `step_executions.output` / `->output` (handler, controladores, vistas, Cockpit, tests, fixtures, spec previos) y reportar la lista antes de cambiar nada.
- [x] 2. Constantes en `config/host.php`; `.gitignore` para `storage/step_logs/`.
- [x] 3. Migración de `step_executions` (quitar `output`; añadir `output_path`, `output_size`, `output_checksum`, `output_tail`) con `down()`; actualizar el modelo.
- [x] 4. `StepLogStore`: `ensureBase`, `write` (truncado, UTF-8, tmp + rename, sha256, tamaño, tail, permisos), `resolve`, `verify`, `readForView`.
- [x] 5. Integrar en `RunStepCommandHandler` (enmascarar → escribir → metadatos). Fallo de escritura nunca deja el paso en running.
- [x] 6. Actualizar todos los lectores de `output` a `output_tail` (lista, Cockpit, tarjetas, vista de ejecución).
- [x] 7. Controlador y vistas de log: ver (escapado, aviso de checksum, tope de vista) y descargar; enlace "Ver log completo" en la vista de ejecución; mensaje "Archivo no disponible".
- [x] 8. Retención: `purge` por lote y `sweepOrphans` en el runner, tras el lock y los detectores.
- [x] 9. Tests: escritura y metadatos; tope de 5 MB y UTF-8 en el corte; secreto enmascarado en el archivo y en el tail; fallo de escritura (ruta no escribible) cierra el paso con tail explicativo; resolve rechaza `../` y symlinks fuera de la base; checksum correcto y detección de alteración; archivo ausente no rompe la vista; purga por antigüedad conserva tail/size/checksum; barrido de huérfanos; paso corto y paso de 100 KB.
- [x] 10. Verificación en dev con MySQL real (ver prompt) y `dumboTest all` en verde.
- [x] 11. Actualizar el spec `runner-pasos-atascados` donde mencionaba el límite de 60.000 bytes en la BD.