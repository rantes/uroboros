<?php
namespace App\CommandHandlers;

use App\Commands\FailStepCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

/**
 * Cierra como `failed` un paso que no terminó por el camino normal
 * (excepción en RunStepCommandHandler, runner muerto a mitad de paso).
 * Emite StepFailed → OnStepFailedReaction → FailWorkflowCommand.
 * Idempotente: solo actúa sobre pasos pending/running.
 */
class FailStepCommandHandler extends Controller {

    public function Handle(FailStepCommand $command): void {
        $this->LoadHelper('StepOutput');

        $stepExecution = $this->StepExecution->Find($command->stepExecutionId);
        $open          = ['pending', 'running'];

        if (!empty($stepExecution->id) and in_array($stepExecution->status, $open, true)):
            $stepExecution->status       = 'failed';
            $stepExecution->exit_code    = $command->exitCode;
            $stepExecution->output       = truncateStepOutput($command->reason);
            $stepExecution->completed_at = time();
            $stepExecution->Save()
                or throw new \Exception((string) $stepExecution->_error);

            $event = $this->Event->Niu([
                'aggregate_type' => 'StepExecution',
                'aggregate_id'   => $stepExecution->id,
                'event_type'     => 'StepFailed',
                'payload'        => json_encode([
                    'workflow_execution_id'       => $stepExecution->workflow_execution_id,
                    'workflow_step_definition_id' => $stepExecution->workflow_step_definition_id,
                    'exit_code'                   => $command->exitCode,
                ]),
            ]);
            $event->Save()
                or throw new \Exception((string) $event->_error);

            (new EventBus())->Dispatch($event);
        endif;
    }
}
