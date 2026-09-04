# Vista de Detalle de Ejecución

## Introducción

Nace de un hallazgo real de uso: cuando una ejecución falla, hoy solo
se ve el estado `failed` en el listado — la razón real (capturada en
`StepExecution.output`, ya existente) requiere ir a buscarla por
cuenta propia. La visión y la guía de diseño ya contemplaban esto
(sección `09`, "Vista de Ejecución") — este spec lo construye.

**No es un dominio nuevo** — reutiliza `WorkflowExecution`/
`StepExecution` tal como existen, es una vista de lectura enriquecida
sobre datos que ya se capturan.

## Decisiones de alcance confirmadas

- **"Timeline" y "Tasks" son el mismo contenido, presentado
  distinto**: Timeline agrupa los pasos por `type` (el campo más
  cercano a "fase" que ya existe); Tasks los muestra como checklist
  plano en orden de ejecución (`step_order`). Misma fuente de datos,
  dos presentaciones.
- **`trigger_type` gana un valor nuevo: `'retry'`** — para poder
  distinguir en el historial qué ejecuciones fueron reintentos de una
  fallida, no disparos manuales nuevos. El "Reintentar" de este spec
  dispara un `WorkflowExecution` completamente nuevo (nuestra
  arquitectura no soporta retomar desde el paso que falló) — el valor
  de `trigger_type` es lo único que lo distingue de un disparo manual
  normal.
- **El tab "Artifacts" queda visible pero vacío** — mismo criterio ya
  establecido en `dashboard-shell` para widgets sin dominio real
  ("Próximamente"), honesto sobre que el dato no existe todavía, no
  inventado.
- **"Related" de la guía (Commit/Workflow/Environment) se reduce a lo
  que sí tenemos**: enlaces a `WorkflowDefinition` y `Project`. No
  hay campo de `commit` en ningún modelo, y "Environment" no es una
  entidad separada en Uroboros (ya resuelto en `configuracion-proyecto`
  — un ambiente es otro `Project`) — ambos se omiten, no se fingen.

## Requisitos

### Requisito 1 — Ver el detalle completo de una ejecución

**Historia de usuario:** Como administrador, quiero ver toda la
información de una ejecución en un solo lugar, para entender qué pasó
sin tener que cruzar manualmente varias vistas.

#### Criterios de aceptación

1. DADO un `WorkflowExecution`, CUANDO se accede a su detalle (link
   desde el listado de `workflow_executions`, no una URL que haya que
   adivinar), ENTONCES se muestra una vista con tabs: Summary,
   Timeline, Tasks, Logs, Artifacts.
2. **Summary**: nombre del Workflow, Proyecto, `trigger_type`,
   estado, inicio, fin, duración, conteo de pasos completados/fallidos.
3. **Timeline**: pasos agrupados por `type`, con su estado individual.
4. **Tasks**: mismos pasos, checklist plano en orden de `step_order`.
5. **Logs**: salida real (`output`) de cada paso, concatenada con
   encabezado claro de a qué paso pertenece cada bloque, estilo
   terminal (fuente monoespaciada, fondo oscuro — sección `15` de
   `design-guide.md`).
6. **Artifacts**: placeholder "Próximamente", sin datos falsos.

### Requisito 2 — Resumen de fallo, cuando aplica

**Historia de usuario:** Como administrador, quiero ver de inmediato
por qué falló una ejecución, sin tener que buscarlo en los logs
completos.

#### Criterios de aceptación

1. DADO un `WorkflowExecution` con `status = 'failed'`, CUANDO se ve
   su detalle, ENTONCES se muestra un resumen de fallo prominente:
   qué paso falló, y un extracto de su `output` real.
2. DADO un `WorkflowExecution` con `status` distinto a `failed`,
   CUANDO se ve su detalle, ENTONCES no se muestra ningún resumen de
   fallo — solo aplica a ejecuciones fallidas.

### Requisito 3 — Acciones sugeridas ante un fallo

**Historia de usuario:** Como administrador, quiero poder actuar
directamente desde la vista de fallo, sin navegar a otro lado para
reintentar o iniciar un rollback.

#### Criterios de aceptación

1. DADO un `WorkflowExecution` fallido, CUANDO se ve su detalle,
   ENTONCES aparece "Ver Logs" (salta al tab Logs, sin recargar la
   página) y "Reintentar" (dispara un `WorkflowExecution` nuevo del
   mismo `WorkflowDefinition`, con `trigger_type = 'retry'`).
2. DADO que el Proyecto de la ejecución fallida tiene al menos un
   `WorkflowDefinition` de `type = 'rollback'`, CUANDO se ve el
   detalle, ENTONCES aparece también "Rollback", que dispara ese
   Workflow — si no existe ninguno, esta acción no aparece (nunca se
   inventa ni se fuerza).

## Fuera de alcance

- Cualquier dato de "Artifacts" real — depende de un dominio que no
  existe todavía.
- Parsear/clasificar semánticamente el motivo del fallo (ej. detectar
  automáticamente "es un error de SQL" vs. "es un error de permisos")
  — el resumen de fallo muestra el `output` real tal cual, sin
  interpretación.
- Reintentar desde el paso específico que falló — la arquitectura
  actual no lo soporta, "Reintentar" siempre corre desde cero.
- Edición de cualquier dato desde esta vista — sigue siendo de solo
  lectura, salvo las dos acciones explícitas (Reintentar, Rollback),
  que disparan Commands, no editan registros directamente.