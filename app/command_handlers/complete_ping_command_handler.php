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
