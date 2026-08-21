# Tareas de implementación — Archivos de Configuración de Proyecto

## Verificación previa (no asumida) — ✅ completada

- [x] 1. AES-256-GCM confirmado disponible, tag = 16 bytes.
- [x] 2. PECL `yaml` ya estaba instalada.
- [x] 3. **Corregido:** el mecanismo real es
      `(new DumboPHP\Secrets())->get($key)`, no `$this->_secrets->get()`
      como proponía `design.md` (esa propiedad no existe fuera de
      `Connection`).
- [x] 4. **Corrección importante:** un `before_save` aborta poblando
      `$this->_error->add([...])`, no lanzando una excepción — el
      snippet de `design.md` habría producido un `500` genérico en
      vez de un `422` con el campo marcado. Corregido siguiendo el
      patrón real de `Project::validateType()`.
- [x] 5. `unique` compuesto no soportado — `validateUniqueFilename()`
      manual implementada.

## Configuración — ✅ completada

- [x] 6. Clave generada y confirmada por el usuario. **Bug real
      encontrado y corregido en el proceso:** la clave terminaba en
      `=` (padding de base64) y sin comillas rompía
      `parse_ini_file()` de *todo* `.env.secrets`, incluyendo
      `DB_USERNAME`/`DB_PASSWORD` — envuelta en comillas dobles,
      verificado que decodifica a exactamente 32 bytes.

## Migración, modelo, implementación, verificación — ✅ completadas

Migración, modelo (hooks en el orden correcto:
`sanitizeFilename → validateFormatType → validateUniqueFilename →
validateFormat → encryptContent`), vistas, componente
`dmb-reveal-secret` (fetch + `DmbDialogService.info()`, sin recargar
página), link "Ver archivos" desde `project_list.phtml` (corrección
de navegación aplicada, no repetida como hueco).

`syncconfigfilesAction()` implementada con `_isSafeRelativePath()` —
**mejora real sobre `design.md`**: validación por segmentos de ruta,
más robusta que el `realpath()`+`str_starts_with()` ilustrativo
original, que tenía una falla real de "prefijo de directorio
hermano" (aceptaría erróneamente algo como `/var/www/proyecto1-evil`
por ser prefijo de string de `/var/www/proyecto1`, sin ser
realmente un subdirectorio).

Verificación con `DumboChromeDriver` completa:
- Formatos válidos/inválidos confirmados (navegador + tests PHP).
- `content` confirmado como blob base64 opaco en BD real, sin rastro
  de texto plano.
- Secreto enmascarado + "Mostrar" funcionando con contenido real
  descifrado.
- Sync real escribiendo 3 archivos, incluyendo ruta anidada, con
  subdirectorio creado automáticamente.
- **4 variantes de path traversal** probadas contra el servidor real
  (relativa, absoluta, híbrida, estilo Windows) — todas rechazadas
  individualmente, cero contaminación del filesystem.
- Proyecto sin `working_directory` → `422` limpio.

## Regresión — ✅ completada

**`dumboTest all`:** 72 tests, 231 assertions, 0 fallos — sin
regresión sobre la línea base anterior (53 tests / 194 assertions).

## Pendiente — tu decisión, no del agente

Quedaron archivos de prueba en `/tmp/uroboros_e2e_workdir/` propiedad
de `www-data` (creados por el servidor real durante la verificación)
que el agente no puede borrar sin `sudo` — contenido no sensible
(datos de prueba `localhost`/`8080`), en `/tmp`. El agente
deliberadamente no usó `sudo` sin tu confirmación explícita, mismo
criterio que ya establecimos para el crontab.