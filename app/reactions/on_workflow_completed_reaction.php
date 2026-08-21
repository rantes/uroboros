<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\ExecuteWorkflowCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnWorkflowCompletedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload              = json_decode($event->payload, true);
        $completedDefinitionId = (int) $payload['workflow_definition_id'];
        $completedDefinition   = $this->WorkflowDefinition->Find($completedDefinitionId);

        $chained = $completedDefinition->workflow_definition('down');

        foreach ($chained as $definition):
            (new CommandBus())->Dispatch(
                new ExecuteWorkflowCommand((int) $definition->id, 'cascade')
            );
        endforeach;
    }
}
