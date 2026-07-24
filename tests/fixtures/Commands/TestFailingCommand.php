<?php
namespace tests\fixtures\Commands;

/**
 * Fixture de test — instrumentación de metricas-oem. DTO puro sin
 * efectos de dominio; su Handler siempre falla para verificar el
 * conteo de command_dispatched/command_failed en CommandBus::Dispatch().
 */
class TestFailingCommand {
}
