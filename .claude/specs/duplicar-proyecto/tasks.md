# Tareas de implementación — Duplicar Proyecto

## Verificación previa

- [x] 1. Confirmar nombres exactos de campos reales de
      `ProjectConfigFile` contra el modelo real.
- [x] 2. Confirmar qué mecanismo real ya existe en el proyecto para
      pedir un solo valor (nombre nuevo) antes de disparar una
      acción — no construir un diálogo nuevo si ya hay un patrón
      establecido.

## Implementación

- [x] 3. `AdminController::duplicateprojectAction()` según
      `design.md`.
- [x] 4. `_duplicateCredentials()` — descifrar/re-cifrar, nunca copiar
      el blob tal cual.
- [x] 5. `_duplicateConfigFiles()` — mismo criterio.
- [x] 6. `_duplicateWorkflows()`/`_duplicateSteps()` — dos pasadas,
      remapeo de `workflow_definition_id` vía el mapa de IDs. Si
      aparece el caso de una referencia a un Workflow de **otro**
      Proyecto (no asumido en `design.md`), repórtalo antes de
      decidir qué hacer.
- [x] 7. Botón "Duplicar" en `project_list.phtml`.

## Verificación con DumboChromeDriver

- [x] 8. Duplicar un Proyecto real con credenciales, archivos de
      configuración, y al menos un Workflow con pasos — confirma en
      BD que las credenciales/archivos del nuevo Proyecto descifran
      al mismo valor real que el origen (no solo que existen filas).
- [x] 9. Confirma que `working_directory` del Proyecto nuevo queda
      vacío, y que `repository_url` se copió tal cual.
- [x] 10. Duplicar un Proyecto con dos Workflows encadenados entre sí
       (`encadenamiento-workflows`) — confirma que la copia del
       Workflow dependiente apunta al Workflow nuevo correspondiente,
       no al del Proyecto original.
- [x] 11. Confirma que los nombres de los Workflows duplicados siguen
       el patrón `"{original} (copia)"`, y que se pueden editar
       después por el flujo normal.

## Regresión

- [x] 12. `dumboTest all` — conteo del nodo raíz de `test-result.xml`,
       cero regresión sobre la línea base actual.
## Notas de implementación (2026-10-04)

- Tarea 6: cadena hacia un Workflow de **otro** Proyecto → decidido con el
  usuario: la copia queda sin encadenar; el mensaje de respuesta lista cuáles.
- Hallazgos no previstos en design.md: `WorkflowDefinition.webhook_token` es
  obligatorio (se genera uno nuevo por copia) y `name` es único global (segunda
  duplicación → "(copia 2)", "(copia 3)"…). Fallo a mitad → se descarta la copia
  parcial para no dejar el nombre del Proyecto ocupado.
- Tareas 8-11 verificadas por UI real (Chrome headless) contra la BD de dev;
  tests en `tests/testDuplicateProject.php`.
