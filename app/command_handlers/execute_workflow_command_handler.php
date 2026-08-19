<?php
namespace App\CommandHandlers;

use App\Commands\ExecuteWorkflowCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class ExecuteWorkflowCommandHandler extends Controller {

    public function Handle(ExecuteWorkflowCommand $command): void {
        $execution = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $command->workflowDefinitionId,
            'status'                 => 'pending',
            'trigger_type'           => $command->triggerType,
        ]);

        $execution->Save()
            or throw new \Exception((string) $execution->_error);

        $event = $this->Event->Niu([
            'aggregate_type' => 'WorkflowExecution',
            'aggregate_id'   => $execution->id,
            'event_type'     => 'WorkflowStarted',
            'payload'        => json_encode(['workflow_definition_id' => $command->workflowDefinitionId]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
