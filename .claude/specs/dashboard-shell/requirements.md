# Shell del Dashboard (Operational Cockpit)

## Introducción

Estructura visual persistente del panel operativo — sidebar, barra
superior, área de contenido en grid, footer de métricas. Basado en la
dirección visual acordada (mockup), pero **no** implementa los
dominios que aún no existen (Workflow Execution, Health Management,
Dependency Management) — esos widgets quedan en estado "próximamente"
hasta que tengan su propio spec.

## Principio rector

El shell se construye una sola vez, desacoplado de qué dominio existe
hoy. Cada widget del grid es independiente — agregar un dominio nuevo
significa agregar un widget, nunca modificar el shell.

## Requisitos

### Requisito 1 — Layout persistente

**Historia de usuario:** Como administrador, quiero que sidebar,
barra superior y footer se mantengan iguales en toda la sección
operativa, para orientarme sin perder contexto al navegar.

#### Criterios de aceptación

1. DADO cualquier página dentro de la sección operativa, CUANDO se
   navega entre ellas, ENTONCES sidebar/topbar/footer no se
   recargan visualmente de forma abrupta (layout persistente estándar
   del proyecto).
2. **Confirmado:** se extiende `app/views/layout.phtml` existente, no
   se crea un layout nuevo — es MVC estándar del proyecto, toda vista
   operativa parte de ahí.

### Requisito 2 — Sidebar con estado real de los dominios

**Historia de usuario:** Como administrador, quiero ver qué secciones
existen de verdad y cuáles vienen después, para no hacer clic en algo
que no funciona.

#### Criterios de aceptación

1. DADO el sidebar, CUANDO se renderiza, ENTONCES **Proyectos** es un
   link funcional (dominio con spec propio).
2. DADO el resto de secciones de la maqueta (Operaciones, Salud,
   Riesgos, Métricas, Recomendaciones, Trazabilidad), CUANDO se
   renderizan, ENTONCES aparecen visualmente pero marcadas como
   "Próximamente" — sin comportamiento de click, o con un tooltip
   explicando que el dominio no existe aún. Nunca simulan
   funcionalidad con datos falsos. **"Eventos" se excluye de esta
   lista — promovido a link real, ver
   `.claude/specs/explorador-eventos/`.**
3. DADO el selector "Workspace / All Workspaces" y el switcher de
   equipo de la maqueta, CUANDO se construye el shell, ENTONCES **no
   se incluyen** — implican multi-tenant/ACL, explícitamente
   descartado (ver conversación: "sin ACL, máximo 1-2 devops"). Si
   más adelante se revierte esa decisión, es un spec nuevo, no una
   reactivación silenciosa de este componente.

### Requisito 3 — Footer de métricas OEM

**Historia de usuario:** Como administrador, quiero ver de un vistazo
cuánta actividad operativa tiene el sistema, para confirmar que está
vivo sin entrar a cada sección.

#### Criterios de aceptación

1. DADO el footer, CUANDO se renderiza, ENTONCES muestra
   `Events (Nh)` con el conteo real de la tabla `events` en la
   ventana de tiempo configurada — este número sí es calculable hoy.
2. **Decisión tomada:** en vez de replicar `Commands (Nh)` /
   `Reactions (Nh)` literal de la maqueta, el footer muestra métricas
   agregadas más significativas: Commands despachados vs. fallidos, y
   Reactions ejecutadas vs. fallidas, en la ventana configurada. La
   fuente de esos números es un contador agregado por hora,
   instrumentado dentro de `CommandBus`/`EventBus` — **spec aparte**
   (`.claude/specs/metricas-oem/`), porque toca archivos del núcleo
   OEM ya cerrado, no es responsabilidad de este spec de UI. Este
   documento solo consume esos datos, no los diseña.
3. DADO `OEM Status`, CUANDO se renderiza, ENTONCES refleja un check
   simple (ej. ¿la última escritura a `events` fue reciente?) — no un
   health check real de infraestructura, eso es Health Management.

### Requisito 4 — Command Palette (⌘K)

**Historia de usuario:** Como administrador, quiero un acceso rápido
por teclado, para moverme sin usar el mouse.

#### Criterios de aceptación

1. DADO el topbar, CUANDO se renderiza, ENTONCES el campo de command
   palette es **visual únicamente en v1** — sin lógica de búsqueda
   funcional. Es un componente DumboJS nuevo que no existe hoy
   (`dmb-command-palette`), y su alcance real (¿busca proyectos?
   ¿ejecuta acciones tipo "deploy production"?) no está definido —
   fuera de alcance de este spec.

### Requisito 5 — Grid de widgets desacoplado

**Historia de usuario:** Como desarrollador, quiero que agregar un
widget nuevo no requiera tocar el shell, para escalar el dashboard a
la par de los dominios que se van construyendo.

#### Criterios de aceptación

1. DADO un widget nuevo (ej. cuando exista Health Management), CUANDO
   se agrega al grid, ENTONCES no requiere cambios en sidebar, topbar
   ni footer.
2. DADO cualquier widget, CUANDO no tiene datos que mostrar (dominio
   no implementado, o implementado pero sin datos aún), ENTONCES
   renderiza un estado vacío explícito ("Próximamente — requiere
   Workflow Execution", o "Sin datos todavía"), nunca datos de
   ejemplo/simulados.

## Fuera de alcance de este spec

- Cualquier widget de contenido real más allá del footer de métricas
  (Operational Health, Active Operations, Dependency Graph, Risk
  Indicators, Recomendaciones) — cada uno es su propio spec, atado al
  dominio correspondiente.
- Notificaciones (icono de campana) — sin dominio que las genere
  todavía.
- Multi-tenant/Workspaces — descartado, ver Requisito 2.3.
- Funcionalidad real del Command Palette — ver Requisito 4.
- La página/listado de Proyectos en sí — la resuelve el mecanismo
  genérico de CRUD del usuario, no este spec. Este spec solo aporta el
  link del sidebar y el espacio en el grid donde ese contenido vive.