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
