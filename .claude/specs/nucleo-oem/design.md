# Diseño técnico — Núcleo OEM (Command / Event / Reaction)

## Decisiones de arquitectura (finales, post-revisión)

1. **Reactions despachan Commands, nunca crean Events.** Único
   componente autorizado a crear Events: el Command Handler. Preserva
   "Everything begins with an intention" de forma literal.
2. **Event Bus v1 es una clase concreta**, síncrona, in-process. Sin
   interfaz, sin múltiples implementaciones. Es la primera vez que se
   evaluó introducir una interfaz en este proyecto (ningún doc de
   convenciones usa `interface` ni clases `abstract`); se decidió no
   hacerlo todavía — se extrae una interfaz solo si algún día se
   justifica un segundo transporte *interno* real, no antes (YAGNI).
   Esto **no** apunta hacia brokers externos — ver Decisión 7.
7. **Sin brokers de mensajería externos, ni como plan a futuro.**
   Redis Streams, Kafka, RabbitMQ, SQS quedan descartados de forma
   permanente por dos razones, no una: (a) chocan con el principio ya
   establecido "sin dependencias Composer en runtime" — ninguno existe
   en PHP nativo, todos requieren extensión o paquete Composer; (b) el
   sistema operativo ya resuelve scheduling y ejecución diferida vía
   `cron`, y el proyecto ya tiene el patrón para consumirlo
   (controladores de background + `dumbo run`). Introducir un broker
   sería abstraer algo que el entorno ya sabe hacer — el mismo error
   categoría que traer i18n de terceros cuando el SO ya lo resuelve
   (regla "Aprovechamiento de lo existente", ver `CLAUDE.md`). Si
   aparece un requisito real de ejecución asíncrona, la solución por
   defecto es una tabla de trabajos pendientes en BD (mismo patrón que
   `events`: migración + modelo ActiveRecord) procesada por un
   controlador de background disparado por `cron`.
3. **`Message` como abstracción compartida — rechazada, no diferida.**
   Event ya necesita extender `DumboPHP\ActiveRecord` (para `Save()`,
   `Find()`, validaciones, integración con `testTables.php`); Command es
   un DTO no persistido. PHP no permite herencia múltiple, así que una
   clase base `Message` de la que ambos hereden es técnicamente
   inviable sin romper una de las dos convenciones ya establecidas del
   proyecto. Esto no es una preferencia de estilo que se pueda
   "resolver más adelante con una interfaz" — es un patrón importado
   que choca con lo ya definido, y la regla de gobierno del proyecto es
   que eso se descarta, no se archiva. Si en el futuro hay un requisito
   real de trazabilidad cruzada entre Commands, se diseña una solución
   propia desde cero en ese momento, evaluada contra las convenciones
   vigentes en ese momento — no se retoma esta propuesta como punto de
   partida.
4. **Sin `app/domain/`, `app/aggregates/`, `app/valueobjects/`.**
   `dumbophp-models.md` ya establece que el modelo ActiveRecord es el
   aggregate root del proyecto. No se introduce una capa paralela sin
   un requisito que la exija.
5. **Sin mecanismo de carga "mágica" para los Buses.** `CommandBus` y
   `EventBus` no son modelos ActiveRecord (no necesitan lazy-load con
   caché) ni helpers de dominio documentados (`Sessions`, `Menu`,
   `Tools`). En vez de forzarlos por el loader de helpers de
   `MainController`/`Controller` (mecanismo no documentado para esta
   clase de objetos), se instancian directamente donde se necesitan:
   `new CommandBus()`, `new EventBus()`. Son objetos PHP ordinarios con
   métodos de instancia — ya cumplían "nunca estático, siempre
   instancia" desde el diseño original; lo único que cambia es que no
   pasan por ningún loader.
6. **Registro de Reactions en archivo de configuración declarativo**
   (`config/reactions_map.php`, en la carpeta de configuración que ya
   existe en la raíz del proyecto — junto a `config/host.php` y
   `config/db_settings.php` — no en una carpeta nueva `app/config/`).
   Se ubica ahí y no dentro de `app/` porque el autoloader del
   proyecto resuelve namespaces por convención de carpeta dentro de
   `app/`, y este archivo no declara ningún namespace ni clase — es
   configuración plana, igual que los otros dos archivos que ya viven
   en `config/`. Meterlo en `app/config/` generaría una colisión real
   (el autoloader esperaría `App\Config\...`). Caso aplicado de la
   regla "Aprovechamiento de lo existente": ya había un lugar
   establecido para este tipo de archivo, no hacía falta crear uno
   nuevo. Sin cambios respecto a la propuesta original en cuanto al
   contenido del archivo — explícito, determinista, sin reflection.

## Nuevas tablas / cambios en BD

### Tabla `events`

| Campo | Tipo | Null | Notas |
| --- | --- | --- | --- |
| `id` | INTEGER | NOT NULL | autoincrement, primary |
| `aggregate_type` | VARCHAR(100) | NOT NULL | ej. `Project`, `Ping` |
| `aggregate_id` | INTEGER | NOT NULL | id del agregado afectado |
| `event_type` | VARCHAR(150) | NOT NULL | ej. `ProjectCreated`, `PingStarted` |
| `payload` | TEXT | NULL | JSON serializado con los datos del hecho |
| `created_at` | INTEGER | NOT NULL | automático |
| `updated_at` | INTEGER | NOT NULL | automático (no debería mutar en la práctica) |

Sin columnas de `correlation_id` / `causation_id` en esta versión (ver
decisión 3). Si se necesitan más adelante, se agregan modificando esta
migración y ejecutando `dumbo migration reset events`, según la
convención del proyecto para campos nuevos en tablas existentes.

Índices:
- Índice compuesto `['aggregate_type', 'aggregate_id']` — soporta
  replay de un agregado.
- Índice simple en `event_type` — soporta consultas de auditoría /
  dashboards por tipo de evento.

## Modelos

### `Event` (`App\Models\Event`, tabla `events`)

Vive en `app/models/event.php`, igual que cualquier otro modelo del
proyecto — no en una carpeta `app/events/` separada, precisamente
porque no hay una capa de dominio paralela (decisión 4).

```php
public ?string $aggregate_type = null;
public ?int    $aggregate_id   = null;
public ?string $event_type     = null;
public ?string $payload        = null; // JSON string
```

- `_init_()`:
  ```php
  $this->validate = [
      'presence_of' => [
          ['field' => 'aggregate_type', 'message' => 'El aggregate_type es obligatorio'],
          ['field' => 'event_type',     'message' => 'El event_type es obligatorio'],
      ],
  ];
  ```
- **Sin hook `before_save` de sanitización con `htmlentities()`** en
  `payload`: es JSON técnico, no texto para render en HTML;
  `htmlentities()` lo corrompería. Excepción documentada a la
  convención general de sanitización de modelos.
- Sin `has_many` / `belongs_to`: la relación con su agregado es lógica
  (`aggregate_type` + `aggregate_id`), no una FK real, porque un Event
  puede apuntar a cualquier tipo de agregado.
- Replay (Requisito 1.3):
  ```php
  $this->Event->Find([
      'conditions' => [
          ['aggregate_type', $tipo],
          ['aggregate_id', $id],
      ],
      'sort' => '`id` ASC',
  ]);
  ```

## Commands

**Ubicación:** `app/commands/` · **Namespace:** `App\Commands`
**Archivo:** `{nombre}_command.php` · **Clase:** `{Nombre}Command`

DTOs puros: propiedades públicas, sin lógica, sin persistencia.

```php
<?php
namespace App\Commands;

class PingCommand {
    public function __construct(
        public readonly string $message,
    ) {}
}

class CompletePingCommand {
    public function __construct(
        public readonly int    $pingId,
        public readonly string $message,
    ) {}
}
```

## Command Handlers

**Ubicación:** `app/command_handlers/` · **Namespace:** `App\CommandHandlers`
**Archivo:** `{nombre}_command_handler.php` · **Clase:** `{Nombre}CommandHandler`

> Corregido durante la verificación: la propuesta original decía
> `app/commandhandlers/` (sin guión bajo). El autoloader real del
> proyecto convierte `CommandHandlers` a `command_handlers` — misma
> regla que ya documenta `dumbophp-models.md` para nombres de tabla
> (`UserProperty` → `user_properties`). Confirmado contra el
> autoloader real, no asumido.

Extienden `DumboPHP\Controller` (misma base que un controlador de
background, según `sdd-workflow.md`) únicamente para reutilizar el
acceso a modelos por lazy load (`$this->Event`). Un Handler nunca
responde HTTP ni usa `$this->render`.

```php
<?php
namespace App\CommandHandlers;

use App\Commands\PingCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class PingCommandHandler extends Controller {

    public function Handle(PingCommand $command): void {
        $event = $this->Event->Niu([
            'aggregate_type' => 'Ping',
            'aggregate_id'   => 0,
            'event_type'     => 'PingStarted',
            'payload'        => json_encode(['message' => $command->message]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
```

```php
<?php
namespace App\CommandHandlers;

use App\Commands\CompletePingCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class CompletePingCommandHandler extends Controller {

    public function Handle(CompletePingCommand $command): void {
        $event = $this->Event->Niu([
            'aggregate_type' => 'Ping',
            'aggregate_id'   => $command->pingId,
            'event_type'     => 'PingCompleted',
            'payload'        => json_encode(['message' => $command->message]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
```

## Reactions

**Ubicación:** `app/reactions/` · **Namespace:** `App\Reactions`
**Archivo:** `{nombre}_reaction.php` · **Clase:** `{Nombre}Reaction`

Extienden `DumboPHP\Controller` por la misma razón que los Handlers
(puede necesitar modelos). Nunca crean Events — solo despachan
Commands (Requisito 3.5).

```php
<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\CompletePingCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnPingStartedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload = json_decode($event->payload, true);

        (new CommandBus())->Dispatch(
            new CompletePingCommand($event->aggregate_id, $payload['message'])
        );
    }
}
```

## Registro de Reactions

**Archivo:** `config/reactions_map.php`

```php
<?php
return [
    'PingStarted' => [
        \App\Reactions\OnPingStartedReaction::class,
    ],
];
```

Agregar una Reaction nueva a un Event existente = agregar una línea a
este array. No se toca el Handler ni las demás Reactions (Requisito
3.3).

## Buses

**Ubicación:** `app/buses/` · **Namespace:** `App\Buses`

Clases concretas, sin interfaz (decisión 2). Instanciadas directamente
con `new` donde se necesitan (decisión 5) — no pasan por el loader de
helpers.

### `CommandBus` (`app/buses/command_bus.php`)

```php
<?php
namespace App\Buses;

class CommandBus {

    public function Dispatch(object $command): void {
        $commandClass = get_class($command);
        $handlerClass = str_replace('\\Commands\\', '\\CommandHandlers\\', $commandClass) . 'Handler';

        class_exists($handlerClass)
            or throw new \Exception("Handler no encontrado para {$commandClass}: {$handlerClass}");

        $handler = new $handlerClass();
        $handler->Handle($command);
    }
}
```

### `EventBus` (`app/buses/event_bus.php`)

```php
<?php
namespace App\Buses;

use App\Models\Event;

class EventBus {

    public function Dispatch(Event $event): void {
        $map = include INST_PATH . 'config/reactions_map.php';
        $reactionClasses = $map[$event->event_type] ?? [];

        foreach ($reactionClasses as $reactionClass):
            try {
                $reaction = new $reactionClass();
                $reaction->Handle($event);
            } catch (\Exception $e) {
                // Requisito 3.4: una Reaction fallida no debe tumbar a
                // las demás. Se registra el error sin librería externa.
                error_log("Reaction fallida [{$reactionClass}] para evento {$event->event_type}: " . $e->getMessage());
            }
        endforeach;
    }
}
```

Uso típico, desde cualquier controlador o Reaction:

```php
use App\Buses\CommandBus;
use App\Commands\PingCommand;

(new CommandBus())->Dispatch(new PingCommand('hola'));
```

## Flujo de datos — arnés Ping → Complete

```text
Controller (ej. pingAction, temporal)
   → (new CommandBus())->Dispatch(new PingCommand('hola'))
        → CommandBus resuelve PingCommandHandler
             → PingCommandHandler::Handle()
                  → crea Event (event_type=PingStarted, aggregate_id=0)
                  → Event->Save()
                  → (new EventBus())->Dispatch($event)
                       → lee reactions_map.php
                       → OnPingStartedReaction::Handle($event)
                            → (new CommandBus())->Dispatch(new CompletePingCommand(0, 'hola'))
                                 → CompletePingCommandHandler::Handle()
                                      → crea Event (event_type=PingCompleted, aggregate_id=0)
                                      → Event->Save()
                                      → (new EventBus())->Dispatch($event)
                                           → sin Reactions registradas para PingCompleted → no-op
```

Este flujo por sí solo demuestra los Requisitos 2, 3.1–3.3, 3.5 y 4.1–4.3.
El Requisito 3.4 (Reaction que falla no bloquea a las demás) se verifica
aparte, con un par de Reactions de prueba dedicadas (ver `tasks.md`), sin
mezclarlo con la cadena Ping/Complete para no complicar el arnés.

## Vistas

Ninguna vista nueva en este spec — es infraestructura pura, sin UI.

## Fuera de alcance de este diseño

Ver sección "Fuera de alcance" en `requirements.md`.
