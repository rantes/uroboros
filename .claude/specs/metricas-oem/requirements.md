# Métricas del Núcleo OEM

## Introducción

Adición al núcleo OEM (ya cerrado — ver `.claude/specs/nucleo-oem/`):
instrumentación mínima para exponer actividad agregada del sistema
(Commands despachados/fallidos, Reactions ejecutadas/fallidas) sin
violar el principio de que los Commands nunca se persisten. Nace como
requisito de datos para `dashboard-shell` (footer de métricas), pero
es un spec de backend independiente — el shell solo lo consume.

## Principio rector

Se cuenta, no se registra. Ninguna fila individual de Command o
Reaction se guarda — solo un contador agregado que sube. Esto
preserva la Decisión de diseño original ("Not persistent history"
para Commands) mientras da visibilidad operativa real.

## Requisitos

### Requisito 1 — Contador agregado por hora

**Historia de usuario:** Como administrador, quiero saber cuánta
actividad tuvo el sistema en las últimas horas, para confirmar que
está vivo y detectar fallos silenciosos, sin que eso implique guardar
el detalle de cada Command.

#### Criterios de aceptación

1. DADO que `CommandBus::Dispatch()` despacha un Command, CUANDO
   termina (éxito o fallo), ENTONCES incrementa el contador de la
   hora actual para `command_dispatched`, y adicionalmente
   `command_failed` si el Handler lanzó una excepción.
2. DADO que `EventBus::Dispatch()` ejecuta una Reaction, CUANDO
   termina (éxito o fallo — el `try/catch` ya existente del
   Requisito 3.4 del núcleo), ENTONCES incrementa
   `reaction_executed`, y adicionalmente `reaction_failed` si cayó en
   el `catch`.
3. DADO un rango de horas (ej. últimas 6), CUANDO se consulta,
   ENTONCES se puede sumar el conteo de cada `metric_type` en ese
   rango con una sola consulta agregada.
4. DADO que el contador no persiste el contenido de cada Command,
   CUANDO se audita, ENTONCES no hay forma de saber *qué* Command
   corrió a partir de esta tabla — coherente con "Not persistent
   history". Para saber *qué* pasó, la fuente sigue siendo `events`
   (Requisito 1 del núcleo OEM).

### Requisito 2 — No debe afectar el comportamiento existente

**Historia de usuario:** Como desarrollador, quiero que instrumentar
métricas no cambie el comportamiento del núcleo, para no reabrir
riesgo en código ya verificado y cerrado.

#### Criterios de aceptación

1. DADO que el incremento del contador falla (ej. problema de BD),
   CUANDO ocurre, ENTONCES no debe interrumpir el flujo real de
   `CommandBus`/`EventBus` — es instrumentación, no lógica de negocio.
   Se envuelve en su propio `try/catch` interno, silencioso (mismo
   criterio que ya se usó para el log de Reactions fallidas).
2. DADO que existen los tests `testNucleoOemPingFlow.php` y
   `testEventBusReactionFailure.php` (ya en verde), CUANDO se agrega
   esta instrumentación, ENTONCES ambos deben seguir pasando sin
   modificarse en su lógica de aserciones existentes — solo se
   permite agregar nuevas aserciones sobre el contador, no cambiar las
   que ya validan Command→Event→Reaction.

## Fuera de alcance

- Cualquier UI — eso es `dashboard-shell`.
- Desglose por tipo de Command/Reaction específico (ej. "cuántos
  `PingCommand`") — el contador es agregado global, no por clase. Si
  se necesita ese detalle más adelante, es una extensión, no algo que
  este spec resuelva.
- Retención/purga de la tabla de contadores — crece mucho más lento
  que `events` (una fila por hora por `metric_type`, no una fila por
  hecho), así que el problema de crecimiento indefinido que discutimos
  para `events` no aplica aquí en la misma escala. Si algún día importa,
  se resuelve con el mismo patrón ya descartado-por-ahora de
  particionamiento, no antes.