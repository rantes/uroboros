<?php
namespace App\CommandHandlers;

use App\Commands\FailWorkflowCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class FailWorkflowCommandHandler extends Controller {

    public function Handle(FailWorkflowCommand $command): void {
        $execution = $this->WorkflowExecution->Find($command->workflowExecutionId);

        $execution->status       = 'failed';
        $execution->completed_at = time();
        $execution->Save()
            or throw new \Exception((string) $execution->_error);

        $event = $this->Event->Niu([
            'aggregate_type' => 'WorkflowExecution',
            'aggregate_id'   => $execution->id,
            'event_type'     => 'WorkflowFailed',
            'payload'        => json_encode([
                'workflow_definition_id'   => $execution->workflow_definition_id,
                'failed_step_execution_id' => $command->failedStepExecutionId,
            ]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
