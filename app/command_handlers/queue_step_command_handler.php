<?php
namespace App\CommandHandlers;

use App\Commands\QueueStepCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class QueueStepCommandHandler extends Controller {

    public function Handle(QueueStepCommand $command): void {
        $stepExecution = $this->StepExecution->Niu([
            'workflow_execution_id'       => $command->workflowExecutionId,
            'workflow_step_definition_id' => $command->stepDefinitionId,
            'status'                      => 'pending',
        ]);

        $stepExecution->Save()
            or throw new \Exception((string) $stepExecution->_error);

        $event = $this->Event->Niu([
            'aggregate_type' => 'StepExecution',
            'aggregate_id'   => $stepExecution->id,
            'event_type'     => 'StepQueued',
            'payload'        => json_encode([
                'workflow_execution_id'       => $command->workflowExecutionId,
                'workflow_step_definition_id' => $command->stepDefinitionId,
            ]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
