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
