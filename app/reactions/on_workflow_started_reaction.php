<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\QueueStepCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnWorkflowStartedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload   = json_decode($event->payload, true);
        $firstStep = $this->WorkflowStepDefinition->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $payload['workflow_definition_id']]],
            'sort'       => '`step_order` ASC',
        ]);

        (new CommandBus())->Dispatch(
            new QueueStepCommand((int) $event->aggregate_id, (int) $firstStep->id)
        );
    }
}
