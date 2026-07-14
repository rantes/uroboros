<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\CompletePingCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnPingStartedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload = json_decode($event->payload, true);

        (new CommandBus())->Dispatch(
            new CompletePingCommand((int) $event->aggregate_id, $payload['message'])
        );
    }
}
