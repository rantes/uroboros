<?php
namespace tests\fixtures\CommandHandlers;

use tests\fixtures\Commands\TestFailingCommand;
use DumboPHP\Controller;

/**
 * Fixture de test — instrumentación de metricas-oem. Falla siempre
 * para verificar que CommandBus::Dispatch() cuenta command_dispatched
 * y command_failed, y vuelve a lanzar la excepción (Requisito 2.1).
 */
class TestFailingCommandHandler extends Controller {

    public function Handle(TestFailingCommand $command): void {
        throw new \Exception('Fallo intencional de prueba (metricas-oem)');
    }
}
