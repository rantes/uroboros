# Diseño técnico — Métricas del Núcleo OEM

## Migración — `create_oem_metrics.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | INTEGER | autoincrement, primary |
| `metric_type` | VARCHAR(50) | NOT NULL — `command_dispatched`, `command_failed`, `reaction_executed`, `reaction_failed` |
| `hour_bucket` | INTEGER | NOT NULL — timestamp Unix truncado a la hora (`floor(time() / 3600) * 3600`) |
| `count` | INTEGER | NOT NULL, `default=0` |
| `created_at` / `updated_at` | INTEGER | automáticos |

Índice:
- `Add_Index(['metric_type', 'hour_bucket'])` — compuesto, único
  idealmente (una sola fila por combinación); mismo pendiente de
  verificación de `UNIQUE` que ya quedó anotado en
  `gestion-proyectos/design.md` para `project_groups` — no se asume,
  se confirma contra el framework real antes de implementar.

## Modelo — `OemMetric` (`app/models/oem_metric.php`)

Modelo ActiveRecord estándar, sin relaciones.

> **Diseño definitivo, no una concesión.** Se descartó SQL crudo
> (`UPDATE ... count = count + 1`) — el proyecto nunca toca la capa de
> BD directamente, siempre `Find()`/`Save()`/`Update()` con
> `conditions`. Eso reintroduce, en teoría, una ventana de carrera
> (dos procesos leen el mismo `count`, ambos escriben el mismo valor
> incrementado, se pierde un incremento). Se acepta explícitamente
> porque:
> 1. Este contador es un pulso operativo aproximado para el footer del
>    dashboard, no una fuente de auditoría exacta — para eso ya existe
>    `events`, precisa porque cada fila es un INSERT independiente.
> 2. La escala real del sistema (1-2 devops, sin ACL) hace la
>    colisión improbable en el uso normal.
> 3. **El principio nuevo de `CLAUDE.md` ("ejecución por lotes es
>    secuencial") elimina el escenario que sí importaba** — el
>    procesamiento de muchos proyectos a la vez (ej. una actualización
>    masiva) se diseña como un solo proceso recorriendo un
>    `for`/`foreach`, nunca como N procesos del SO en paralelo. Sin
>    paralelismo real a nivel de proceso, no hay dos escrituras
>    simultáneas que colisionen.

```php
public function Increment(string $metricType): void {
    $hourBucket = (int) (floor(time() / 3600) * 3600);

    $existing = $this->Find([
        'conditions' => [
            ['metric_type', $metricType],
            ['hour_bucket', $hourBucket],
        ],
    ]);

    if ($existing->counter() === 0):
        $existing = $this->Niu([
            'metric_type' => $metricType,
            'hour_bucket' => $hourBucket,
            'count'       => 1,
        ]);
    else:
        $existing->count = $existing->count + 1;
    endif;

    $existing->Save(); // created_at/updated_at los maneja el framework
}
```

Confirmado por el usuario, ya no es una suposición: `counter()` existe
en todo objeto `ActiveRecord`, no solo en resultados multi-fila —
`$this->Model->Niu()->counter()` es `0`. Y `:first` es innecesario
aquí porque la condición compuesta (`metric_type` + `hour_bucket`) ya
narrows a una sola fila por sí misma, igual que pasaría con `id=1`.

`$this->Find(...)` llamado desde dentro del propio modelo (sobre
`$this`) — confirmado válido por el usuario, no asumido.

## Enmienda a `nucleo-oem` — Decisión 5 (Buses como clases planas)

> **Corregido tras revisión de código real.** `nucleo-oem/design.md`
> Decisión 5 justificaba `CommandBus`/`EventBus` como clases planas
> *porque no tocaban modelos*. Ese supuesto dejó de ser cierto en
> cuanto este spec les dio una razón real para tocar `OemMetric`. La
> implementación inicial usó `new OemMetric()` dentro de
> `_incrementMetric()`, violando la regla no negociable de
> `CLAUDE.md` ("nunca `new NombreModelo()`, siempre
> `$this->NombreModelo`") — porque una clase plana no tiene acceso al
> lazy-load.
>
> **Corrección:** `CommandBus` y `EventBus` pasan a extender
> `DumboPHP\Controller`, mismo patrón que `CommandHandlers`/
> `Reactions` (que ya corren en modo "background", sin HTTP). Se
> siguen instanciando igual (`new CommandBus()`, `new EventBus()` en
> cada punto de uso) — eso no cambia, cambia únicamente la clase base,
> para heredar `$this->OemMetric` en vez de instanciarlo a mano.
>
> Lo que cambia en esta enmienda es solo el punto de acceso al modelo:
> `$this->OemMetric->Increment($type)`, no
> `(new OemMetric())->Increment($type)`. La implementación interna de
> `Increment()` se rediseñó por separado, más adelante en este mismo
> documento (sección "Modelo — `OemMetric`") — sin SQL crudo, con
> `Find()`+`Niu()`/`Save()`. Esta nota se corrigió porque quedó
> desactualizada: decía que `Increment()` usaba "SQL crudo, atómico" y
> "no cambia", contradiciendo el bloque de código real más abajo en el
> mismo documento. El agente lo detectó correctamente durante la
> implementación y siguió el código concreto, no esta frase — quede
> constancia de que la inconsistencia era del documento, no de la
> implementación.

## `OemMetricsTrait` — comportamiento compartido, no duplicado

> **Corrección:** `_incrementMetric()` estaba duplicado, idéntico,
> entre `CommandBus` y `EventBus` — dos archivos con la misma lógica.
> Duplicación real entre clases (lo que SonarQube marca y DRY
> prohíbe), no solo repetición dentro de un mismo método. Se extrae a
> un trait, no a una clase base compartida: `CommandBus` y `EventBus`
> no comparten ningún otro comportamiento real (resuelven sus
> respectivos flujos de forma distinta), así que una clase base
> quedaría vacía salvo por este método — forzar herencia para algo
> que es "qué hacen aparte", no "qué son". El proyecto ya tiene
> precedente real de este patrón (`AdminBaseTrait`, usado por
> `AdminController`).
>
> **Ubicación y nombre de archivo confirmados:** `app/buses/`, junto a
> `command_bus.php`/`event_bus.php` — mismo criterio que
> `AdminBaseTrait` (`app/controllers/admin_base_trait.php`, snake_case
> siguiendo la política de discovery del autoload). El archivo nuevo
> es `app/buses/oem_metrics_trait.php`.

```php
<?php
namespace App\Buses;

trait OemMetricsTrait {
    private function _incrementMetric(string $type): void {
        try {
            $this->OemMetric->Increment($type);
        } catch (\Exception $e) {
            // Silencioso a propósito — Requisito 2.1: un fallo al
            // contar nunca debe interrumpir el flujo real.
        }
    }
}
```

`CommandBus`/`EventBus` agregan `use OemMetricsTrait;` dentro del
cuerpo de la clase (además de `extends Controller`). `$this->OemMetric`
sigue funcionando igual dentro del trait — al mezclarse en la clase
consumidora, `$this` es la instancia real, que ya tiene lazy-load por
heredar de `Controller`.

## Punto de instrumentación — `CommandBus::Dispatch()`

```php
<?php
namespace App\Buses;

use DumboPHP\Controller;

class CommandBus extends Controller {

    use OemMetricsTrait;

    public function Dispatch(object $command): void {
        $commandClass = get_class($command);
        $handlerClass = str_replace('\\Commands\\', '\\CommandHandlers\\', $commandClass) . 'Handler';

        class_exists($handlerClass)
            or throw new \Exception("Handler no encontrado para {$commandClass}: {$handlerClass}");

        try {
            $handler = new $handlerClass();
            $handler->Handle($command);
            $this->_incrementMetric('command_dispatched');
        } catch (\Exception $e) {
            $this->_incrementMetric('command_dispatched');
            $this->_incrementMetric('command_failed');
            throw $e;
        }
    }
}
```

## Punto de instrumentación — `EventBus::Dispatch()`

```php
<?php
namespace App\Buses;

use App\Models\Event;
use DumboPHP\Controller;

class EventBus extends Controller {

    use OemMetricsTrait;

    public function Dispatch(Event $event): void {
        $map = include INST_PATH . 'config/reactions_map.php';
        $reactionClasses = $map[$event->event_type] ?? [];

        foreach ($reactionClasses as $reactionClass):
            try {
                $reaction = new $reactionClass();
                $reaction->Handle($event);
                $this->_incrementMetric('reaction_executed');
            } catch (\Exception $e) {
                $this->_incrementMetric('reaction_executed');
                $this->_incrementMetric('reaction_failed');
                error_log("Reaction fallida [{$reactionClass}] para evento {$event->event_type}: " . $e->getMessage());
            }
        endforeach;
    }
}
```

## Consulta agregada para el rango de horas

```php
$this->OemMetric->Find([
    'fields'     => 'metric_type, SUM(count) AS total',
    'conditions' => [['hour_bucket', '>=', $desde]],
    'group'      => 'metric_type',
]);
```

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.