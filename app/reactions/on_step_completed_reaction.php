<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\QueueStepCommand;
use App\Commands\CompleteWorkflowCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnStepCompletedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload       = json_decode($event->payload, true);
        $completedStep = $this->WorkflowStepDefinition->Find((int) $payload['workflow_step_definition_id']);
        $nextStep      = $this->WorkflowStepDefinition->Find([
            ':first',
            'conditions' => [
                ['workflow_definition_id', $completedStep->workflow_definition_id],
                ['step_order', '>', $completedStep->step_order],
            ],
            'sort' => '`step_order` ASC',
        ]);

        if ($nextStep->counter() === 0):
            (new CommandBus())->Dispatch(new CompleteWorkflowCommand((int) $payload['workflow_execution_id']));
        else:
            (new CommandBus())->Dispatch(new QueueStepCommand((int) $payload['workflow_execution_id'], (int) $nextStep->id));
        endif;
    }
}
