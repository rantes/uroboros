# Núcleo OEM (Command / Event / Reaction)

## Introducción

Uroboros no es un runner de pipelines: es un Operational Event Mesh (OEM).
Todo lo que el sistema hace debe fluir por el mismo ciclo:

```text
Command → Command Handler → Domain Aggregate → Event → Event Bus → Reaction → Command(s)
```

Antes de construir cualquier dominio de negocio (Proyectos, Entornos,
Workflows...) necesitamos la infraestructura mínima que permita:

1. Declarar y despachar **Commands** (intenciones).
2. Persistir **Events** (hechos) de forma inmutable en un Event Store.
3. Distribuir esos Events a **Reactions** suscritas, de forma desacoplada.
4. Permitir que una Reaction, al interpretar un hecho, despache una o
   varias nuevas **Commands** — nunca genere Events directamente.

Este spec cubre únicamente el núcleo genérico y reutilizable. No incluye
ningún dominio de negocio real (eso se spec-ea después, empezando por
"Gestión de Proyectos", que consumirá este núcleo).

> Revisado en la sesión de arquitectura del 2024 (ver
> `oem-architecture-review.md` como referencia histórica). Decisiones
> finales adoptadas en este documento:
> - El Event Bus v1 es una **clase concreta**, sin interfaz. Se
>   extraerá una interfaz solo si algún día se justifica un segundo
>   transporte *interno* (no aplica a brokers externos, ver siguiente
>   punto). No antes.
> - **No se contemplan brokers de mensajería externos** (Redis, Kafka,
>   RabbitMQ, SQS) como evolución del Event Bus, ni ahora ni como
>   "camino natural a futuro". Chocan con "sin dependencias Composer en
>   runtime" y duplican algo que el sistema operativo ya resuelve
>   (`cron`). Si se necesita ejecución asíncrona, la respuesta por
>   defecto es una cola nativa en BD + controlador de background vía
>   `dumbo run`/cron — mismo patrón que el Event Store, sin conceptos
>   nuevos.
> - La abstracción `Message` (base común de Command/Event con
>   correlation id, causation id, metadata) **se rechaza, no se
>   pospone**. Entra en conflicto directo con dos convenciones ya
>   establecidas (Event extiende `ActiveRecord`; PHP no tiene herencia
>   múltiple) — no es una preferencia de estilo, es una
>   incompatibilidad real. Regla de gobierno aplicada: un patrón
>   importado que choca con lo ya definido no se archiva "para
>   después", se descarta. Si en el futuro aparece un requisito real de
>   trazabilidad cruzada, se resuelve desde cero con una solución
>   propia del proyecto (ver `CLAUDE.md`, "Principios no negociables").
> - No se introducen `app/domain/`, `app/aggregates/` ni
>   `app/valueobjects/`. El modelo ActiveRecord sigue siendo el
>   aggregate root, como en el resto del proyecto.

Para probar que el ciclo completo funciona de punta a punta —
incluyendo el encadenamiento Reaction → Command de la Decisión 1 —
este spec incluye un arnés de verificación desechable de dos pasos:
`PingCommand` → evento `PingStarted` → Reaction que despacha
`CompletePingCommand` → evento `PingCompleted`. No es un dominio de
negocio, es una prueba de la tubería.

## Requisitos

### Requisito 1 — Event Store

**Historia de usuario:** Como plataforma, quiero persistir cada hecho
ocurrido como un Event inmutable, para tener una historia auditable y
reproducible del sistema.

#### Criterios de aceptación

1. DADO que un Command Handler termina su lógica de dominio, CUANDO
   genera un hecho, ENTONCES ese hecho se guarda como un registro en la
   tabla `events` con `aggregate_type`, `aggregate_id`, `event_type` y
   `payload` (JSON).
2. DADO un Event ya guardado, CUANDO cualquier componente intenta
   modificarlo, ENTONCES el modelo no debe exponer ningún método de
   actualización con sentido de negocio (los Events solo se crean, nunca
   se editan ni se eliminan salvo limpieza técnica).
3. DADO un `aggregate_type` y `aggregate_id`, CUANDO se consulta el
   Event Store, ENTONCES debe ser posible recuperar la secuencia completa
   de Events de ese agregado ordenada cronológicamente (replayability).

### Requisito 2 — Command Bus

**Historia de usuario:** Como desarrollador del proyecto, quiero
despachar un Command sin acoplarme a la clase concreta del handler, para
mantener Commands y Handlers desacoplados de quien los invoca.

#### Criterios de aceptación

1. DADO un objeto Command válido, CUANDO se despacha vía
   `(new CommandBus())->Dispatch($command)`, ENTONCES el bus resuelve el
   Handler correspondiente por convención de nombre y ejecuta su lógica.
2. DADO un Command cuyo Handler no existe, CUANDO se despacha, ENTONCES
   se lanza una excepción controlada (no un error fatal de PHP).
3. DADO un Command, CUANDO su Handler lo procesa, ENTONCES el Handler es
   el único responsable de tocar los modelos de dominio y de crear
   Events — el Command en sí nunca se persiste.
4. DADO cualquier invocación del Command Bus, CUANDO se ejecuta,
   ENTONCES no depende de ningún mecanismo de carga "mágica" (helper
   loader, reflection, autodiscovery) — es una instancia PHP ordinaria
   (`new CommandBus()`) con un método de instancia.

### Requisito 3 — Event Bus y Reactions

**Historia de usuario:** Como plataforma, quiero que cada Event
persistido dispare automáticamente a todas las Reactions suscritas a su
tipo, sin que el Handler que generó el Event conozca cuáles son, para
mantener el sistema extensible sin modificar código existente.

#### Criterios de aceptación

1. DADO un Event recién guardado, CUANDO se despacha vía
   `(new EventBus())->Dispatch($event)`, ENTONCES se ejecutan en orden
   todas las Reactions registradas para ese `event_type`.
2. DADO un `event_type` sin Reactions registradas, CUANDO se despacha su
   Event, ENTONCES el Event Bus no falla — simplemente no hay nada que
   ejecutar.
3. DADO que se necesita agregar una nueva Reaction a un Event existente,
   CUANDO se agrega, ENTONCES no debe requerirse modificar el Handler
   que originó el Event ni ninguna otra Reaction (extensibilidad,
   instalación sin tocar código existente).
4. DADO que una Reaction falla (excepción), CUANDO el Event Bus la
   ejecuta, ENTONCES el fallo se registra pero no debe impedir que las
   demás Reactions suscritas al mismo Event se ejecuten.
5. DADO que una Reaction necesita provocar el siguiente paso del ciclo
   operativo, CUANDO reacciona a un Event, ENTONCES debe despachar una o
   varias Commands — nunca debe crear ni guardar un Event directamente.

### Requisito 4 — Verificación de punta a punta (encadenamiento)

**Historia de usuario:** Como desarrollador, quiero un ejemplo mínimo
pero encadenado (`Ping` → `Complete`), para confirmar que Command →
Handler → Event → Event Bus → Reaction → Command (Requisito 3.5)
funciona antes de construir dominios reales sobre él.

#### Criterios de aceptación

1. DADO un `PingCommand` despachado, CUANDO el flujo se ejecuta,
   ENTONCES queda un registro en `events` con `event_type = 'PingStarted'`.
2. DADO ese evento `PingStarted`, CUANDO se dispara su Reaction,
   ENTONCES esa Reaction despacha `CompletePingCommand` (no crea un
   Event directamente).
3. DADO `CompletePingCommand`, CUANDO su Handler lo procesa, ENTONCES
   queda un segundo registro en `events` con
   `event_type = 'PingCompleted'` y el mismo `aggregate_id` que
   `PingStarted` (mismo agregado lógico).
4. DADO el requisito 3.4 (una Reaction que falla no bloquea a las
   demás), CUANDO se registran dos Reactions para un mismo `event_type`
   de prueba y la primera lanza una excepción, ENTONCES la segunda debe
   ejecutarse igualmente (esto se verifica con un par de Reactions de
   prueba aisladas, no como parte de la cadena Ping/Complete).

## Fuera de alcance de este spec

- Cualquier dominio de negocio real (Proyectos, Entornos, Workflows,
  Dependencias, Salud, Ejecuciones).
- Colas de mensajería externas / brokers (Redis, RabbitMQ, Kafka, SQS).
  **No es una omisión temporal de la v1 — es una exclusión permanente
  por defecto.** Choca con el principio ya establecido "sin
  dependencias Composer en runtime" (`CLAUDE.md`), y además duplica
  algo que el sistema operativo ya resuelve: scheduling y ejecución
  diferida vía `cron` + `dumbo run` (mismo patrón que ya usan los
  controladores de background). Si en el futuro aparece un requisito
  real de ejecución asíncrona (un Command que tarda demasiado para un
  request HTTP), la respuesta por defecto es una tabla de trabajos
  pendientes en BD (mismo patrón que el Event Store: migración +
  modelo ActiveRecord) procesada por un controlador de background
  disparado por cron — no un broker externo. Adoptar un broker
  requeriría revisar explícitamente el principio "sin dependencias
  Composer en runtime" como excepción justificada, no como evolución
  esperada.
- La abstracción `Message` (correlation id, causation id, metadata
  compartida entre Command y Event). **Rechazada, no diferida** — choca
  con convenciones ya establecidas (ver introducción de este documento).
- Capas `app/domain/`, `app/aggregates/`, `app/valueobjects/`. El
  modelo ActiveRecord es el aggregate root en este proyecto.
- Reintentos automáticos de Reactions fallidas.
- Versionado de esquema de payload de Events.
