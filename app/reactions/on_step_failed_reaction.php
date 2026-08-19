<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\FailWorkflowCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnStepFailedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload = json_decode($event->payload, true);

        (new CommandBus())->Dispatch(
            new FailWorkflowCommand((int) $payload['workflow_execution_id'], (int) $event->aggregate_id)
        );
    }
}
