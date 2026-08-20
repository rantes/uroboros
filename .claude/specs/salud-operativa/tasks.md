# Tareas de implementación — Salud Operativa

## Verificación previa (no asumida)

- [x] 1. **Confirmado.** `$controller->WorkflowExecution` sí resuelve
      el lazy-load real desde una función externa a la clase
      (`__get()` no depende del scope del caller para propiedades no
      declaradas) — pero esto quedó **superado por el refactor**: los
      cálculos se movieron al modelo, ya no hace falta pasar el
      controlador como parámetro en absoluto.
- [x] 2. **Confirmado contra MySQL real.** `SUM(CASE WHEN...)` y
      `AVG(a - b)` dentro de `fields` funcionan igual que
      `COUNT(id) AS total`.

## Implementación

- [x] 3. `DeploymentSuccessRate()`/`LeadTime()` como métodos de
      `WorkflowExecution` (no un helper) — corrección aplicada tras
      revisión de código, single-return en ambos.
- [x] 4. `AdminController::healthmetricsAction()` implementado.
- [x] 5. Widget "Operational Health" en `admin/index.phtml`.
- [x] 6. Estado vacío específico ("Sin datos en este período").

## Verificación

- [x] 7. Datos reales + cálculo a mano — coincide exacto (75% / "1s"),
      confirmado dos veces (implementación original y post-refactor).
- [x] 8. Cambio de ventana confirmado como AJAX real (una sola
      petición `GET`, sin recargar página).
- [x] 9. Estado vacío real confirmado (tabla truncada → mensaje
      correcto en ambas métricas).
- [x] 10. **`dumboTest all`:** 50 tests, 182 assertions, 0 fallos —
       sin regresión, mismo conteo antes y después del refactor.

## Cierre

- [x] 11. Confirmado — resto del dashboard sin afectación.

## Hallazgo lateral, ya corregido durante implementación

Componente `dmb-health-widget` con `static template =
'<div transclude></div>'` perdía las `<option>` del `<dmb-select>`
anidado — dos `DumboDirective` anidados con `connectedCallback()`
async es una condición de carrera real (mismo patrón que
`dumbochromedriver.md` hallazgo 2). Resuelto sin template propio,
mismo patrón que `dmb-content`/`dmb-footer`.