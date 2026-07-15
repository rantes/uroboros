# Tareas de implementación — Núcleo OEM

## Event Store

- [ ] 1. Crear migración `create_events.php` (tabla `events`, campos
      según `design.md`, índice compuesto `aggregate_type+aggregate_id`
      e índice simple en `event_type`)
- [ ] 2. Ejecutar `dumbo migration up events`
- [ ] 3. Crear modelo `Event` (`app/models/event.php`) con las
      validaciones y propiedades de `design.md`, sin sanitización
      `htmlentities()` en `payload`
- [ ] 4. Agregar `events` a `tests/testTables.php` (`migrationsTest`)
- [ ] 5. Crear `tests/testEventModel.php`:
      - caso feliz: guardar un Event válido
      - validación: falla sin `aggregate_type` / sin `event_type`
      - replay: crear 3 Events del mismo agregado y verificar que se
        recuperan en orden cronológico (Requisito 1.3)

## Buses (clases concretas, sin interfaz)

- [ ] 6. Crear `app/buses/command_bus.php` (`App\Buses\CommandBus`)
- [ ] 7. Crear `app/buses/event_bus.php` (`App\Buses\EventBus`)
- [ ] 8. Confirmar que `App\Buses\CommandBus` y `App\Buses\EventBus`
      se autocargan correctamente vía el autoload del proyecto sin
      necesidad de `require` manual (mismo mecanismo que usan
      `App\Models\*` y `App\Controllers\*`, solo cambia el namespace
      raíz) — si el autoloader depende de convención de carpeta,
      ajustar aquí sin cambiar el diseño conceptual

## Reactions y su registro

- [ ] 9. Crear `config/reactions_map.php` con la entrada de
      `PingStarted` para la verificación end-to-end

## Verificación end-to-end (Ping → Complete)

- [ ] 10. Crear `app/commands/ping_command.php`
       (`App\Commands\PingCommand`)
- [ ] 11. Crear `app/commands/complete_ping_command.php`
       (`App\Commands\CompletePingCommand`)
- [ ] 12. Crear `app/command_handlers/ping_command_handler.php`
       (`App\CommandHandlers\PingCommandHandler`)
- [ ] 13. Crear `app/command_handlers/complete_ping_command_handler.php`
       (`App\CommandHandlers\CompletePingCommandHandler`)
- [ ] 14. Crear `app/reactions/on_ping_started_reaction.php`
       (`App\Reactions\OnPingStartedReaction`) — despacha
       `CompletePingCommand`, nunca crea un Event
- [ ] 15. Agregar una acción temporal de verificación (ej.
       `pingAction()` en un controlador existente o uno nuevo de prueba)
       que despache `PingCommand`
- [ ] 16. Crear `tests/testNucleoOemPingFlow.php` que:
       - ejecute la acción/flujo de Ping
       - assert que existe un `Event` con `event_type = 'PingStarted'`
         y `aggregate_id = 0`
       - assert que existe un segundo `Event` con
         `event_type = 'PingCompleted'` y el mismo `aggregate_id`
       - assert el orden cronológico entre ambos (Requisito 4.3)

## Verificación aislada del Requisito 3.4 (Reaction fallida no bloquea)

- [ ] 17. Mover `app/reactions/test_failing_reaction.php` y
       `app/reactions/test_succeeding_reaction.php` a
       `tests/fixtures/TestFailingReaction.php` y
       `tests/fixtures/TestSucceedingReaction.php` respectivamente —
       nombre de archivo en PascalCase idéntico al de la clase, no
       snake_case (convención de `tests/`, distinta a la de `app/`:
       ver `testing.md`, "El namespace es `tests\` (minúscula)"), con
       namespace `tests\fixtures` (no `App\Reactions`) — decisión
       confirmada: se conservan de forma permanente, pero fuera de la
       carpeta de Reactions de dominio para no confundirlas con
       código de negocio real. `App\Reactions\TestFailingReaction` →
       `tests\fixtures\TestFailingReaction`;
       `App\Reactions\TestSucceedingReaction` →
       `tests\fixtures\TestSucceedingReaction`. Actualizar el
       namespace dentro de ambos archivos.
- [ ] 18. Test que despache un Event de ese tipo directamente vía
       `(new EventBus())->Dispatch($event)` y verifique que el efecto
       observable de la segunda Reaction sí ocurrió a pesar del fallo
       de la primera — actualizar `tests/testEventBusReactionFailure.php`
       si referencia las clases por su namespace anterior
- [ ] 19. **Decisión tomada: conservar, no eliminar.** Actualizar
       `config/reactions_map.php` para que la entrada
       `ReactionFailureTestEvent` apunte a
       `\tests\fixtures\TestFailingReaction::class` y
       `\tests\fixtures\TestSucceedingReaction::class`. Mantener el
       comentario que marca esa entrada como fixture de test — es la
       única excepción intencional de mezclar algo de test en un
       archivo de configuración de runtime, y debe quedar visible como
       tal, no oculta.

## Cierre

- [ ] 20. Ejecutar `dumboTest testEventModel testNucleoOemPingFlow`
       `testEventBusReactionFailure`, confirmar que todo pasa después
       de mover las fixtures (tarea 17-19)
- [ ] 21a. **Decisión tomada: conservar.** El arnés Ping/Complete
       (`PingCommand`, `CompletePingCommand`, `PingCommandHandler`,
       `CompletePingCommandHandler`, `OnPingStartedReaction` y
       `tests/testNucleoOemPingFlow.php`) se mantiene de forma
       permanente como test de regresión del núcleo — es la única
       prueba automatizada del ciclo completo
       Command→Handler→Event→Bus→Reaction→Command, independiente de
       cualquier dominio real. No requiere ninguna acción adicional
       más allá de lo ya implementado.
- [ ] 21b. **Decisión tomada: eliminar.** Borrar
       `app/controllers/oemcheck_controller.php` — era solo para
       verificación manual por HTTP mientras no existían tests
       automatizados; ya no aporta nada que `testNucleoOemPingFlow.php`
       no cubra, y deja un endpoint sin autenticación innecesario.
- [x] 22. **Hecho.** `CLAUDE.md`, `code-conventions.md` y `testing.md`
       actualizados manualmente con las tres propuestas
       (`.claude/rules/propuesta-CLAUDE-md-adicion.md`,
       `propuesta-code-conventions-adicion.md`,
       `propuesta-testing-md-adicion.md`). Verificación de forma
       (Markdown bien formado, sin duplicados) pendiente vía
       `PROMPT-cierre-tarea22.md`.
- [x] 23. **Confirmado.** El núcleo OEM no impone ninguna dependencia
       de un dominio específico — Commands/Handlers/Reactions/Buses
       son genéricos, y `Event` no tiene relaciones `has_many`/
       `belongs_to` con nada de "Proyectos". El spec de "Gestión de
       Proyectos" puede apoyarse en este núcleo sin requerir ningún
       cambio aquí.
