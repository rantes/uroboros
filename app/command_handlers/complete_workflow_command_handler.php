<?php
namespace App\CommandHandlers;

use App\Commands\CompleteWorkflowCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class CompleteWorkflowCommandHandler extends Controller {

    public function Handle(CompleteWorkflowCommand $command): void {
        $execution = $this->WorkflowExecution->Find($command->workflowExecutionId);

        $execution->status       = 'completed';
        $execution->completed_at = time();
        $execution->Save()
            or throw new \Exception((string) $execution->_error);

        $event = $this->Event->Niu([
            'aggregate_type' => 'WorkflowExecution',
            'aggregate_id'   => $execution->id,
            'event_type'     => 'WorkflowCompleted',
            'payload'        => json_encode(['workflow_definition_id' => $execution->workflow_definition_id]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
