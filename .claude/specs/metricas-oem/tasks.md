# Tareas de implementación — Métricas del Núcleo OEM

## Verificaciones previas (no asumidas)

- [x] 1. **Confirmado.** `ActiveRecord::Update()` liga cada valor como
      parámetro — no admite expresiones SQL (`count+1`). No existe
      `Increment()`/`UpdateCounters()` nativo. Se implementó
      `OemMetric::Increment()` con SQL crudo vía la constante global
      `DB`, siguiendo el mismo patrón que usa el propio framework
      internamente en `Save()`/`Insert()`/`Update()`.
- [x] 2. **Confirmado.** `Add_Index()` no soporta `UNIQUE` en ningún
      driver. Mismo hallazgo aplicado a `gestion-proyectos`.

## Implementación

- [x] 3. `migrations/create_oem_metrics.php` creada (con corrección
      manual: `id`/`created_at`/`updated_at` explícitos, que el
      generador omite pero la convención del proyecto exige).
- [x] 4. `dumbo migration up oem_metrics` ejecutada.
- [x] 5. Modelo `OemMetric` creado, con tipos corregidos a `?int` y
      método `Increment()`.
- [x] 6. `oem_metrics` agregada a `tests/testTables.php`.
- [x] 7. `app/buses/command_bus.php` modificado — `try/catch` con
      `throw $e;` explícito.
- [x] 8. `app/buses/event_bus.php` modificado — conteo agregado dentro
      de las ramas ya existentes, sin reestructurar el método.
- [x] 9. `tests/testOemMetrics.php` creado, con fixtures dedicadas
      (`TestSucceedingCommand`/`TestFailingCommand`) para no
      contaminar el conteo con la cadena Ping→Complete — PASS, 9/9
      assertions.
- [x] 10. `testNucleoOemPingFlow.php` y `testEventBusReactionFailure.php`
       confirmados sin ninguna modificación — código ni aserciones.
- [x] 11. `dumboTest all` — 0 fallos.

## Cierre

- [x] 12. **Confirmado por el agente:** la consulta agregada propuesta
       en `design.md` (`SUM(count) GROUP BY metric_type WHERE
       hour_bucket >= $desde`) funciona con el esquema real
       implementado. No se implementó nada de `dashboard-shell` aquí
       — queda listo para que ese spec lo consuma.

## Correcciones post-implementación (detectadas en revisión de código)

- [x] 13. `CommandBus`/`EventBus` pasaron a extender
       `DumboPHP\Controller` — `new OemMetric()` violaba la regla
       "nunca `new NombreModelo()`". Ver Enmienda a `nucleo-oem`
       Decisión 5 en `design.md`.
- [x] 14. `OemMetric::Increment()` reescrito sin SQL crudo —
       `Find()`+`Niu()`/`Save()`, respetando la regla del proyecto de
       nunca tocar la capa de BD directamente.
- [x] 15. `_incrementMetric()` extraído a `OemMetricsTrait`
       (`app/buses/oem_metrics_trait.php`) — estaba duplicado idéntico
       entre `CommandBus` y `EventBus`.
- [x] 16. Verificado (con evidencia contra el framework real) que
       `Controller`/`Core_General_Class` no declaran `__construct()`
       propio, y que el anidamiento `Bus→Reaction→Bus` no es un caso
       nuevo de riesgo — ya existía un nivel más adentro antes de esta
       corrección. `dumboTest all` en verde, cero regresión.