# Tareas de implementación — Credenciales de Proyecto

## ✅ Todo resuelto y verificado

- [x] 1-2. Cifrado reutilizado tal cual de `ProjectConfigFile`, orden
      credenciales/`cd` confirmado sin importar funcionalmente.
- [x] 3-6. Migración y modelo — **bug real encontrado y corregido**:
      `presence_of` en `value` no funcionaba porque `encryptValue()`
      corre antes de la validación (un vacío cifrado no es "vacío"
      para el validador) — `validateValuePresence()` manual agregado
      antes del cifrado, capturado por test propio.
- [x] 7-11. CRUD, link de navegación, `_substituteCredentials()`/
      `_maskCredentials()` en `RunStepCommandHandler`.
- [x] 12. **Verificado con interceptor de `window.fetch` real** — el
      body de la respuesta de red nunca contiene el secreto en texto
      plano, en ningún endpoint (creación ni listado).
- [x] 13. Confirmado en BD: blob cifrado opaco, round-trip correcto.
- [x] 14. Verificado sin imprimir el secreto en ningún punto de la
      prueba misma (comparación por hash MD5, no por valor directo).
- [x] 15. Camino de fallo confirmado — placeholder sin credencial
      correspondiente nunca ejecuta el comando literal.
- [x] 16. **La más importante — confirmada.** Búsqueda SQL explícita
      del secreto real sobre *todas* las filas de
      `step_executions.output`: cero coincidencias.
- [x] 17. **`dumboTest all`:** 86 tests, 261 assertions — sin
      regresión (línea base: 79/247).

Limpieza confirmada: 0 filas en las 7 tablas involucradas, incluida
`project_credentials` — sin ningún secreto de prueba olvidado.