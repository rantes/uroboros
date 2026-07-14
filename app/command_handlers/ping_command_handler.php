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
