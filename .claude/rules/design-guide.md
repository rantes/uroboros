---
paths:
  - "ui-components/**/*.scss"
  - "app/views/**/*.phtml"
  - "ui-components/libs/main.css"
---

# Uroboros — Guía de Diseño (v1.0)

> Fuente: guía visual de referencia (imagen), transcrita para uso
> real del equipo/agente. Los valores exactos (hex, px) son la fuente
> de verdad — si `main.css`/`css-conventions.md` difieren de esta
> guía en algún token, se reconcilian, no coexisten dos versiones.

## 01 — Filosofía

Uroboros no es una herramienta de CI/CD. Es una plataforma de
orquestación operacional basada en eventos.

- **Operational First** — la información importante siempre visible y
  accionable.
- **Signal over Noise** — interfaz limpia, enfocada en lo que
  realmente importa.
- **System Thinking** — representamos relaciones, no listas. Todo
  está conectado.
- **Human-Centric DevOps** — diseñado para personas técnicas. Claro,
  consistente y eficiente.

Lema: *"Eventos que fluyen. Sistemas que evolucionan."*

## 02 — Logo

**Concepto**: el logo representa el ciclo infinito de eventos y
transiciones dentro de un sistema vivo — un anillo de nodos
conectados (círculo con puntos/nodos distribuidos alrededor,
coherente con el nombre "Uroboros").

## 03 — Paleta de colores

### Colores primarios

| Token | Hex | Uso |
| --- | --- | --- |
| Electric Cyan | `#00D8FF` | Color primario de marca/acción |
| Dark Surface | `#0D1117` | Fondo base |

### Colores de superficie

| Token | Hex | Uso |
| --- | --- | --- |
| Secondary Surface | `#161B22` | Superficie secundaria (paneles, tarjetas) |
| Elevated Surface | `#21262D` | Superficie elevada (modales, dropdowns) |
| Border / Outline | `#30363D` | Bordes y separadores |

### Colores de estado

| Estado | Hex | Semántica |
| --- | --- | --- |
| Success | `#26CC71` | La operación se completó correctamente |
| Warning | `#F1C40F` | Algo requiere atención, pero no es crítico |
| Failed | `#E74C3C` | La operación falló y requiere acción |
| Running | `#00D8FF` | La operación está en progreso |
| Pending | `#667681` | La operación está en espera |
| Recovered | `#27AE60` | La operación se recuperó automáticamente |

> **Verificar antes de aplicar**: cruzar estos valores contra
> `ui-components/libs/main.css` real — ya se encontraron variables
> referenciadas en el código (`--primary-contrast`, `--secondary`,
> `--information`, `--surface-2`, `--border-subtle`) que no estaban
> definidas en ningún lado. Esta guía es una buena oportunidad para
> cerrar ese hueco con valores reales, no adivinados.

## 04 — Tipografía

**Tipografía principal**: Inter
Pesos: 400 (Regular), 500 (Medium), 600 (SemiBold), 700 (Bold)

**Tipografía monoespaciada**: JetBrains Mono
Uso: logs, código, comandos, variables, salida de consola — nunca
para texto de interfaz general.

## 05 — Espaciado

Sistema basado en múltiplos de 8px, expresado en la guía original en
`px` crudos:

```
4 · 8 · 16 · 24 · 32 · 48 · 64 · 96
```

> **Decisión confirmada:** el proyecto usa `em`/`rem` consistentemente
> (`css-conventions.md`), no `px` crudos, para
> `width`/`height`/`margin`/`padding`/`gap`. La escala de arriba se
> **convierte a equivalentes `em`/`rem`**, no se migra el proyecto a
> `px` — mantiene la convención ya establecida.
>
> **Raíz real confirmada** (`html, body` en `ui-components/styles.scss`
> y `--font-size-base` en `main.css`): `font-size: clamp(13px, 1.2vw, 15px)`
> — **no es un valor fijo**, es fluido. Se resuelve a `15px` en
> cualquier viewport ≥1250px (la inmensa mayoría de uso real de un
> dashboard admin en escritorio: 1366/1440/1536/1920px), baja
> linealmente entre 1083px y 1250px, y toca el piso de `13px` por
> debajo de 1083px. La tabla de abajo usa `15px` como ancla
> representativa (el valor al que converge en el uso real de este
> producto) — en viewports angostos, 1rem será hasta ~13% más pequeño
> que estos valores, por diseño (no es un bug, es tipografía/espaciado
> fluido intencional).
>
> | px (guía) | rem (raíz = 15px) |
> | --- | --- |
> | 4 | `0.267rem` |
> | 8 | `0.533rem` |
> | 16 | `1.067rem` |
> | 24 | `1.6rem` |
> | 32 | `2.133rem` |
> | 48 | `3.2rem` |
> | 64 | `4.267rem` |
> | 96 | `6.4rem` |

## 06 — Bordes y radios

| Radio | Uso |
| --- | --- |
| `12px` | Componentes principales (tarjetas, paneles) |
| `8px` | Inputs y controles |
| `999px` | Píldoras y badges (completamente redondeado) |

## 07 — Iconografía

**Estilo**: Outline · Geométrico · Simple · Consistente
**Inspiración/set de referencia**: Lucide · Tabler Icons

Iconos base usados en la guía: home, cubo/paquete, usuarios/despliegue,
gráfico, ajustes, campana, usuario, búsqueda, agregar (+), check,
cerrar (x), info, advertencia, reloj, código (`</>`), nube, base de
datos.

## 08 — Dashboard principal

**Objetivo**: responder en 5 segundos *"¿Está sano mi ecosistema?"*

Estructura de referencia:
- Sidebar: Overview, Projects, Executions, Dependencies, Alerts,
  Settings.
- Widget "Operational Health" — donut de porcentaje (ej. 94% Healthy).
- Widgets de métricas rápidas: Deployments (24h), Failed Deployments,
  Active Executions, MTTR, Risk Score — cada uno con valor actual y
  comparación contra el período anterior (ej. "+20% vs ayer").
- "Recent Activity" — lista de eventos recientes con estado.
- "Health Trend (7d)" — gráfico de tendencia.

> Nota de alcance: varios de estos widgets (MTTR, Risk Score,
> Dependency Graph) corresponden a dominios que la visión original
> contempla pero que Uroboros no ha implementado todavía — ver
> `Vision_y_arquitectura_fundacional.md` y los specs de
> `salud-operativa`/`ejecucion-workflows` para el estado real de cada
> uno. Esta guía documenta la aspiración visual, no confirma que el
> dato ya existe.

## 09 — Vista de ejecución

**Objetivo**: entender qué pasó, por qué, y cómo actuar.

Estructura de referencia:
- Encabezado: ID de ejecución, descripción, estado, origen (proyecto/
  rama/commit), duración, botón "Rerun".
- Tabs: Summary, Timeline, Tasks, Logs, Artifacts.
- Timeline agrupado por fase (ej. Build → Deploy), cada paso con su
  duración y estado individual.
- "Failure Summary" — cuando algo falla, resumen claro de la causa
  (ej. "Migration failed — Foreign key constraint violation").
- "Suggested Actions" — acciones contextuales según el fallo (Ver
  Logs, Reintentar, Rollback).
- "Related" — enlaces a commit, workflow, y ambiente involucrados.

## 10 — Componentes UI

- Botón Primario / Botón Secundario / Botón con ícono.
- Píldoras/Badges de estado (Success, Failed, Running, Pending — cada
  una con su color de estado correspondiente de la sección 03).
- Tabs.
- Inputs: texto, select, checkbox, toggle.

## 11 — Formularios

**Principios**:
- Una columna — nunca multi-columna para formularios de datos.
- Labels siempre visibles (nunca solo placeholder).
- Validación en tiempo real.
- Mensajes de error claros y accionables.

## 12 — Estados

Todo estado se comunica con **Color + Ícono + Texto** simultáneamente
— nunca solo color (accesibilidad, y consistencia con "Signal over
Noise").

| Estado | Ícono | Color | Significado |
| --- | --- | --- | --- |
| Success | ✓ | `#26CC71` | La operación se completó correctamente |
| Warning | ⚠ | `#F1C40F` | Algo requiere atención, pero no es crítico |
| Failed | ✕ | `#E74C3C` | La operación falló y requiere acción |
| Running | ○ (animado) | `#00D8FF` | La operación está en progreso |
| Pending | ⧗ | `#667681` | La operación está en espera |
| Recovered | ✓ | `#27AE60` | La operación se recuperó automáticamente |

## 13 — Dependency Graph

*"Visualizamos relaciones, no listas."*

Nodos representando componentes (ej. librería compartida, APIs,
servicios, frontend), conectados por flechas que indican dependencia
— quién depende de quién, para responder "si cambio esto, ¿qué se ve
afectado?".

## 14 — Workflow Builder

*"Dibuja tu workflow visualmente."*

Editor visual de nodos conectados (ej. Build → Test → Deploy →
Notify), arrastrables, con conexiones que representan el orden de
ejecución.

## 15 — Logs

Diseño tipo terminal moderno — filtros por nivel (All/Info/Warning/
Error), líneas con timestamp, fuente monoespaciada (JetBrains Mono,
sección 04).

## 16 — Principios UX

- **Awareness First** — mantener al usuario siempre informado.
- **Acción Rápida** — las acciones importantes deben ser fáciles y
  rápidas de ejecutar.
- **Prevención** — prevenir errores antes de que ocurran, no solo
  reportarlos después.
- **Confianza** — el sistema debe ser predecible y transparente.
- **Eficiencia** — menos clicks, más productividad.

---

## Nota de implementación

Este documento es la guía visual de referencia — no reemplaza
`css-conventions.md` (que documenta las variables CSS reales del
proyecto y las convenciones de organización de archivos SCSS), lo
complementa. Cuando haya conflicto entre un valor de esta guía y lo
que ya está implementado en `main.css`, se resuelve actualizando
`main.css` a los valores de esta guía (fuente de verdad de diseño),
documentando el cambio — nunca al revés, y nunca dejando que
coexistan dos paletas distintas.