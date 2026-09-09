# Tareas de implementación — Dependency Graph

## Verificación previa (no asumida)

- [ ] 1. Confirmar la sintaxis real de `Find()` para "no nulo" —
      ¿`'IS NOT'` funciona como operador, o hace falta resolverlo en
      PHP tras traer todos los registros? No asumido.
- [ ] 2. Confirmar si excluir auto-referencias de un Proyecto a sí
      mismo (`$childProject->id !== $parentProject->id`) es
      correcto, o si debería representarse de otra forma en el grafo
      (ej. un nodo con una flecha curva hacia sí mismo) — tu criterio,
      repórtalo antes de decidir.

## Implementación

- [ ] 3. `_buildProjectDependencyGraph()` en `IndexController`, según
      `design.md`, ajustado a lo confirmado en el Paso 1.
- [ ] 4. `_detectCycle()`/`_dfsDetectCycle()` — verificar con un caso
      real de ciclo armado a propósito (A→B→A) que la detección
      funciona y no entra en bucle infinito.
- [ ] 5. `_computeLayout()` — niveles simples, protegido contra nodos
      en ciclo (no deben bloquear el cómputo del resto).
- [ ] 6. Generador de SVG compartido (compacto/completo), usando los
      colores reales de `design-guide.md`.
- [ ] 7. Widget en `index/index.phtml` — estado vacío honesto si no
      hay dependencias configuradas.
- [ ] 8. `IndexController::dependencygraphAction()` — vista de
      detalle completa.
- [ ] 9. Link "Ver detalles" desde el widget — mismo criterio de
      navegación por clicks reales ya establecido, no una URL que
      haya que adivinar.

## Verificación con DumboChromeDriver

- [ ] 10. Sin ninguna dependencia configurada — confirma el estado
       vacío honesto en el widget.
- [ ] 11. Configura 2-3 relaciones reales entre Proyectos reales (vía
       el campo ya existente en `WorkflowDefinition`) — confirma que
       el widget y el detalle muestran el grafo correcto, con los
       nombres reales de los Proyectos.
- [ ] 12. Configura un ciclo real (A depende de B, B depende de A) —
       confirma que se detecta, se muestra igual (no se bloquea), y
       la advertencia es clara tanto en el widget como en el detalle.
- [ ] 13. Confirma navegación por clicks reales desde el widget hacia
       el detalle, sin URL adivinada.

## Regresión

- [ ] 14. `dumboTest all` — conteo del nodo raíz de `test-result.xml`,
       cero regresión sobre la línea base actual.