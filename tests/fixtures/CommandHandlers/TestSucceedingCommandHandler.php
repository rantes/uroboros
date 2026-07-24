<?php
namespace tests\fixtures\CommandHandlers;

use tests\fixtures\Commands\TestSucceedingCommand;
use DumboPHP\Controller;

/**
 * Fixture de test — instrumentación de metricas-oem. No crea Events
 * ni despacha nada más: aísla el conteo de command_dispatched en
 * éxito de cualquier efecto de dominio.
 */
class TestSucceedingCommandHandler extends Controller {

    public function Handle(TestSucceedingCommand $command): void {
    }
}
