# Dependency Graph

## Introducción

Del dominio "Dependency Management" de la visión original — pero, a
diferencia de lo que sugiere ese nombre grande, **no necesita ningún
dato nuevo**. `encadenamiento-workflows` ya construyó exactamente la
relación que un grafo de dependencias necesita:
`WorkflowDefinition.workflow_definition_id` (auto-referencia — "este
Workflow depende de que aquel otro termine bien"). Cada
`WorkflowDefinition` pertenece a un `Project`, así que esa cadena
Workflow→Workflow ya es, de hecho, una cadena Proyecto→Proyecto. Este
spec es visualización sobre datos que ya existen, no un dominio
nuevo.

## Decisiones de alcance confirmadas

- **Se construye en `IndexController`** (el Cockpit, ya migrado ahí)
  — no en `AdminController`.
- **Ciclos**: si se detecta uno (riesgo ya aceptado y nunca prevenido
  en `encadenamiento-workflows`), el grafo se muestra igual, con una
  advertencia visual clara señalando qué Proyectos están involucrados
  — nunca se bloquea la visualización completa por esto.
- **Renderizado**: SVG generado por el propio backend (mismo criterio
  ya usado en `dmb-donut-chart` — sin librerías de gráficos externas,
  coherente con "sin dependencias en runtime").

## Requisitos

### Requisito 1 — Derivar el grafo de dependencias de Proyectos

**Historia de usuario:** Como administrador, quiero ver qué Proyectos
dependen de cuáles, para entender el impacto de un cambio antes de
hacerlo.

#### Criterios de aceptación

1. DADO el conjunto de `WorkflowDefinition` con
   `workflow_definition_id` no nulo, CUANDO se construye el grafo,
   ENTONCES cada uno se traduce en una relación Proyecto→Proyecto (el
   Proyecto dueño del Workflow referenciado es el origen; el Proyecto
   dueño del Workflow que depende es el destino).
2. DADO que dos o más `WorkflowDefinition` distintos generan la misma
   relación Proyecto→Proyecto, CUANDO se construye el grafo, ENTONCES
   esa relación aparece una sola vez (sin aristas duplicadas).
3. DADO que existe un ciclo real entre Proyectos (A depende de B, B
   depende de A, directa o indirectamente), CUANDO se construye el
   grafo, ENTONCES se detecta sin caer en un bucle infinito de
   cómputo, y se muestra con una advertencia clara indicando qué
   Proyectos están involucrados en el ciclo.

### Requisito 2 — Widget en el Cockpit

**Historia de usuario:** Como administrador, quiero ver un resumen
visual de las dependencias directamente en el dashboard, sin tener
que entrar a una vista aparte para lo básico.

#### Criterios de aceptación

1. DADO el widget "Dependency Graph" del Cockpit, CUANDO hay
   Proyectos con dependencias reales, ENTONCES muestra una vista
   compacta del grafo (nodos y flechas), con un link a la vista de
   detalle completa.
2. DADO que no existe ninguna relación de dependencia entre Proyectos
   todavía, CUANDO se muestra el widget, ENTONCES aparece un estado
   vacío honesto ("Sin dependencias configuradas todavía") — nunca un
   grafo vacío sin explicación, ni el mensaje genérico de
   "Próximamente" (el dominio ya existe, solo no hay datos aún).

### Requisito 3 — Vista de detalle completa

**Historia de usuario:** Como administrador, quiero ver el grafo
completo en una vista dedicada, con más espacio y detalle que el
widget del dashboard.

#### Criterios de aceptación

1. DADO el link "Ver detalles" del widget, CUANDO se accede (con
   clicks reales, no una URL adivinada), ENTONCES se muestra el grafo
   completo, con el nombre real de cada Proyecto en su nodo.
2. DADO un ciclo detectado, CUANDO se ve el detalle, ENTONCES la
   advertencia del Requisito 1.3 es visible aquí también, no solo
   silenciada en la vista compacta del widget.

## Fuera de alcance

- Cualquier análisis de impacto más allá de mostrar las relaciones
  ("si cambio X, se ven afectados Y y Z" como texto explícito) — v1
  es solo la visualización, no un motor de análisis.
- Edición del grafo desde esta vista — las relaciones se configuran
  donde ya existían (el formulario de `WorkflowDefinition`), esta
  vista es de solo lectura.
- Zoom/pan interactivo, arrastrar nodos, o cualquier interactividad
  más allá de ver el grafo estático y hacer click en "Ver detalles".
- Prevención de ciclos — sigue siendo el mismo riesgo aceptado de
  `encadenamiento-workflows`, esto solo lo hace visible, no lo evita.