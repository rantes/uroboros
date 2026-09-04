# Tareas de implementación — Vista de Detalle de Ejecución

## ✅ Todo resuelto y verificado

**Nota de nombres:** el spec vive como `.claude/specs/vista-ejecucion/`
en el repo real, no `vista-ejecucion-detalle/` como se nombró
originalmente aquí — el agente lo detectó y lo dejó explícito en vez
de asumir en silencio.

- [x] 1. `id ASC` coincide con `step_order` real en el caso normal,
      pero **no se dependió de esa coincidencia** — implementado con
      el `step_order` real vía join, verificado con un test que
      fuerza el caso contrario (inserción en orden 3,2,1).
- [x] 2. Confirmado que no existía ningún componente de tabs — creado
      con el generador (`dmb-tabs`).
- [x] 3-8. Esquema y CRUD/vista implementados. **Corrección de un
      error real en `design.md`**: `WorkflowDefinition` no tiene
      columna `type` (solo `WorkflowStepDefinition` la tiene) —
      resuelto identificando el Workflow de rollback por tener al
      menos un paso de ese tipo, sin inventar esquema nuevo.
      **Bug de infraestructura real encontrado**: `admin.js` nunca se
      carga en ninguna página (solo `app.js` es global) — explica por
      qué `dmb-status-badge` de la ronda anterior "funcionaba" solo
      por ser CSS puro; `dmb-tabs` sí necesitaba su JS real, reubicado
      a `app.js`.
- [x] 9-14. **Verificado con evidencia real completa**, incluida la
      tarea 13 (falsificación activa de `trigger_type=webhook`
      reescribiendo la URL del botón — bloqueada, cayó a `manual`),
      ahora también como test permanente, no solo verificación de
      navegador.
- [x] 15. **`dumboTest all`:** 94 tests, 274 assertions — sin
      regresión (línea base: 89/266).

Limpieza confirmada: todo dato de prueba eliminado/revertido,
incluido el `working_directory` temporal que se le había puesto a
`test-navegacion` para la prueba.