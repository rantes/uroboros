# Salud Operativa (Health Management)

## Introducción

Primer consumo real del widget "Operational Health" del dashboard,
hoy en "Próximamente". A diferencia de `ejecucion-workflows`, este
dominio **no pasa por el núcleo OEM** — no hay Commands/Events nuevos,
es una capa de lectura/agregación sobre datos que ya existen
(`WorkflowExecution`, `StepExecution`). Mismo criterio que
`gestion-proyectos`: dato de referencia/cálculo, no intención ni
hecho operativo nuevo.

**Decisiones de alcance ya confirmadas:**
- v1 cubre solo **Deployment Success Rate** y **Lead Time** — las dos
  métricas de la visión con fórmula obvia. **Deliberadamente fuera de
  alcance:** MTTR, Risk Score, Project Stability — sin fórmula
  definida, no se inventa una para no comprometer un cálculo que
  después haya que deshacer.
- Cálculo por **queries de agregación en vivo** sobre
  `WorkflowExecution`/`StepExecution` — sin tablas nuevas, sin
  contador incremental (a diferencia de `OemMetric`). Los datos ya
  existen completos (`status`, `created_at`, `started_at`,
  `completed_at`), así que un `SUM`/`AVG`/`COUNT` directo es
  suficiente y siempre exacto — no hay razón para mantener un
  duplicado sincronizado.

## Definiciones — confirmar antes de implementar

### Deployment Success Rate

```
COUNT(status = 'completed') / COUNT(status IN ('completed', 'failed')) × 100
```

Dentro de una ventana de tiempo (ver Requisito 3). **`pending`/
`running` se excluyen del denominador** — no han concluido, incluirlos
distorsionaría la tasa hacia abajo artificialmente.

### Lead Time

**Confirmado:** se mide desde `created_at` (cuándo se disparó —
incluye el tiempo en cola esperando el `cron`), no desde `started_at`.

```
AVG(completed_at - created_at)  -- para status = 'completed', ventana de tiempo
```

## Requisitos

### Requisito 1 — Calcular Deployment Success Rate

**Historia de usuario:** Como administrador, quiero ver qué porcentaje
de mis despliegues termina bien, para entender la salud operativa real
del sistema.

#### Criterios de aceptación

1. DADO un rango de tiempo (ventana configurable, ver Requisito 3),
   CUANDO se calcula, ENTONCES el resultado es el porcentaje de
   `WorkflowExecution` con `status = 'completed'` sobre el total de
   ejecuciones concluidas (`completed` + `failed`) en esa ventana.
2. DADO que no hay ninguna ejecución concluida en la ventana, CUANDO
   se calcula, ENTONCES el widget muestra un estado vacío explícito
   ("Sin datos en este período") — nunca `0%` ni una división por
   cero.

### Requisito 2 — Calcular Lead Time

**Historia de usuario:** Como administrador, quiero saber cuánto tarda
en promedio un despliegue exitoso de principio a fin, para detectar
si el sistema se está volviendo más lento.

#### Criterios de aceptación

1. DADO un rango de tiempo, CUANDO se calcula, ENTONCES el resultado
   es el promedio de tiempo (en la definición confirmada — ver
   "Definiciones" arriba) de las `WorkflowExecution` con
   `status = 'completed'` en esa ventana.
2. DADO que no hay ejecuciones completadas en la ventana, CUANDO se
   calcula, ENTONCES estado vacío explícito, mismo criterio que
   Requisito 1.2.
3. El resultado se muestra en una unidad legible (segundos/minutos
   según la magnitud), no como un número crudo de segundos sin
   contexto.

### Requisito 3 — Ventana de tiempo

**Historia de usuario:** Como administrador, quiero elegir el período
sobre el que se calculan las métricas, para comparar salud reciente
vs. tendencia más larga.

#### Criterios de aceptación

1. DADO que las métricas de esta spec son de tendencia (no el pulso
   operativo de 6h del footer de `metricas-oem`), CUANDO se define la
   ventana por defecto, ENTONCES es de **7 días** — rango más
   apropiado para observar una tasa de éxito o un tiempo promedio con
   volumen de datos suficiente.
2. **Confirmado:** la ventana es seleccionable en la UI mediante un
   `dmb-select` con tres opciones: **7 días / 30 días / 90 días**.
   Cambiar la selección recalcula ambas métricas para el rango
   elegido.

### Requisito 4 — Widget en el dashboard

**Historia de usuario:** Como administrador, quiero ver estas dos
métricas en el dashboard principal, para tener visibilidad sin entrar
a una vista separada.

#### Criterios de aceptación

1. DADO el widget "Operational Health" del dashboard (hoy
   "Próximamente"), CUANDO se implementa este spec, ENTONCES muestra
   Deployment Success Rate y Lead Time reales, con el mismo criterio
   de estado vacío honesto ya establecido en `dashboard-shell`
   (nunca "Próximamente" para un dominio que ya existe, mensaje
   específico de "sin datos" cuando aplica).

## Fuera de alcance

- MTTR, Risk Score, Project Stability — sin fórmula definida, ver
  "Decisiones de alcance".
- Desglose por Proyecto individual — v1 es agregado global del
  sistema completo. Por Proyecto es una extensión natural futura
  (agrupar por `project_id` vía `WorkflowDefinition`), no bloqueante.
- Ventanas fuera de 7d/30d/90d (ej. personalizada por el usuario) —
  ver Requisito 3.2.
- Cualquier alerta o notificación basada en umbrales de estas
  métricas — dominio aparte.
- Gráficas de tendencia histórica (series de tiempo) — v1 es solo el
  valor agregado actual, no un gráfico.
