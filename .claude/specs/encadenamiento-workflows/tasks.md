# Tareas de implementación — Encadenamiento de Workflows

## Verificación previa (no asumida)

- [ ] 1. Confirmar la sintaxis real de `belongs_to` auto-referenciada
      con alias — no asumir el snippet de `design.md`, verificar
      contra el framework real o resolver con query manual si la
      declarativa no aplica limpio a este caso.
- [ ] 2. Confirmar si el payload de `WorkflowCompleted` (creado por
      `CompleteWorkflowCommandHandler`, en `ejecucion-workflows`) ya
      incluye `workflow_definition_id` — si no, agregarlo ahí
      primero, antes de escribir la Reaction que depende de eso.

## Esquema

- [ ] 3. `workflow_definitions`: agregar columna
      `workflow_definition_id` (INTEGER, NULL) —
      `dumbo migration reset workflow_definitions` según convención
      del proyecto para columnas nuevas en tablas existentes.
- [ ] 4. `WorkflowExecution`: agregar `'cascade'` a la lista cerrada
      de `trigger_type` en la validación del modelo.

## Implementación

- [ ] 5. `app/models/workflow_definition.php`: propiedad y relación
      nueva, ajustada a lo confirmado en el Paso 1.
- [ ] 6. `OnWorkflowCompletedReaction` según `design.md`, ajustada a
      lo confirmado en el Paso 2.
- [ ] 7. Registrar en `config/reactions_map.php`.
- [ ] 8. `dmb-select` nuevo en
      `admin/workflow_definition_addedit.phtml` — poblado con todos
      los Workflows existentes (nombre + proyecto), opción "Ninguno"
      por defecto.
- [ ] 9. Prevenir que un Workflow se seleccione a sí mismo como
      origen (validación simple, ciclo trivial de un nodo) — la
      única prevención de ciclos que sí entra en v1, según
      `design.md`.

## Verificación con DumboChromeDriver

- [ ] 10. Configurar Workflow B para dispararse cuando Workflow A
       termine — con clicks reales en el selector.
- [ ] 11. Disparar A manualmente, esperar a que complete
       (`processpendingAction()`), confirmar que B se dispara solo,
       sin intervención manual — verificar en `workflow_executions`
       que la ejecución de B tiene `trigger_type = 'cascade'`.
- [ ] 12. Configurar un Workflow C que falle a propósito, con un
       Workflow D encadenado a C — confirmar que D **nunca** se
       dispara (Requisito 2.2, solo `completed` cuenta).
- [ ] 13. Confirmar en la UI que intentar seleccionar un Workflow
       como origen de sí mismo se previene (Paso 9).

## Regresión

- [ ] 14. `dumboTest all` — conteo del nodo raíz de `test-result.xml`,
       cero regresión sobre la línea base actual.

## ✅ Todo resuelto y verificado — usando el mecanismo nativo del framework

Las tareas 1-14 quedan completas usando `has_many_and_belongs_to`
nativo (no la query manual de rondas anteriores) — el bug de
framework que lo bloqueaba fue diagnosticado, corregido por el
usuario en DumboPHP, y reverificado empíricamente con el mismo
escenario exacto que expuso el bug originalmente (conteos 1/1
correctos, antes devolvía 3/3 sin filtrar).

Evidencia final: ciclo completo A→B disparado solo vía
`$definition->workflow_definition('down')`
(`workflow_executions.trigger_type='cascade'` confirmado), aserción
negativa con Workflow que falla confirmada (`COUNT(*) = 0`),
prevención de auto-selección confirmada en el DOM real.

**`dumboTest all`:** 79 tests, 247 assertions — idéntico a la línea
base (cambio de mecanismo interno, no de comportamiento observable).

- [x] 15. **Pendiente de decisión del usuario, no resuelto aquí:**
      el hallazgo de `salud-operativa`/`rollback` (Workflows de tipo
      `rollback` contando como éxito en Deployment Success Rate)
      sigue anotado, sin resolver.