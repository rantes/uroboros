<?php
namespace App\CommandHandlers;

use App\Commands\RunStepCommand;
use App\Buses\EventBus;
use DumboPHP\Controller;

class RunStepCommandHandler extends Controller {

    /**
     * SOLO se despacha desde el controlador de background
     * (WorkflowRunnerController, Parte 3) — nunca desde una Reaction
     * ni desde un request HTTP. Ejecuta el comando externo de forma
     * bloqueante dentro de ese proceso. Ver design.md — "Ejecución
     * real del script externo".
     */
    public function Handle(RunStepCommand $command): void {
        $stepExecution     = $this->StepExecution->Find($command->stepExecutionId);
        $stepDefinition    = $stepExecution->workflow_step_definition();
        $workflowExecution = $this->WorkflowExecution->Find((int) $stepExecution->workflow_execution_id);
        $outputLines       = [];
        $exitCode          = 0;

        // Corrección — el diseño original nunca marcaba la transición
        // pending -> running a nivel de WorkflowExecution (iba directo
        // a completed/failed). Solo la primera vez que cualquier step
        // de esta ejecución realmente arranca (no al encolar — un
        // step puede esperar minutos al próximo ciclo de cron). Ver
        // design.md, "Corrección — transición de WorkflowExecution a
        // running".
        if ($workflowExecution->status === 'pending'):
            $workflowExecution->status     = 'running';
            $workflowExecution->started_at = time();
            $workflowExecution->Save()
                or throw new \Exception((string) $workflowExecution->_error);

            $runningEvent = $this->Event->Niu([
                'aggregate_type' => 'WorkflowExecution',
                'aggregate_id'   => $workflowExecution->id,
                'event_type'     => 'WorkflowRunning',
                'payload'        => json_encode(['workflow_definition_id' => $workflowExecution->workflow_definition_id]),
            ]);
            $runningEvent->Save()
                or throw new \Exception((string) $runningEvent->_error);

            (new EventBus())->Dispatch($runningEvent);
        endif;

        $stepExecution->status     = 'running';
        $stepExecution->started_at = time();
        $stepExecution->Save()
            or throw new \Exception((string) $stepExecution->_error);

        $project = $workflowExecution->workflow_definition()->project();

        // working_directory (gestion-proyectos) — Uroboros nunca clona el
        // repositorio, solo garantiza que el directorio exista antes de
        // ejecutar ahí. Cualquier fallo de esta resolución es un fallo de
        // step limpio (exit_code=1, mensaje en output), nunca una
        // excepción no capturada que tumbe el proceso de cron completo.
        if (empty($project->working_directory)):
            $exitCode    = 1;
            $outputLines = ['El proyecto no tiene working_directory configurado.'];
        elseif (!is_dir($project->working_directory) and !@mkdir($project->working_directory, 0755, true)):
            $exitCode    = 1;
            $outputLines = ["No se pudo crear el directorio de trabajo: {$project->working_directory}"];
        else:
            exec('cd ' . escapeshellarg($project->working_directory) . ' && ' . $stepDefinition->command . ' 2>&1', $outputLines, $exitCode);
        endif;

        $stepExecution->exit_code    = $exitCode;
        $stepExecution->output       = implode("\n", $outputLines);
        $stepExecution->completed_at = time();
        $stepExecution->status       = $exitCode === 0 ? 'completed' : 'failed';
        $stepExecution->Save()
            or throw new \Exception((string) $stepExecution->_error);

        $event = $this->Event->Niu([
            'aggregate_type' => 'StepExecution',
            'aggregate_id'   => $stepExecution->id,
            'event_type'     => $exitCode === 0 ? 'StepCompleted' : 'StepFailed',
            'payload'        => json_encode([
                'workflow_execution_id'       => $stepExecution->workflow_execution_id,
                'workflow_step_definition_id' => $stepExecution->workflow_step_definition_id,
                'exit_code'                   => $exitCode,
            ]),
        ]);

        $event->Save()
            or throw new \Exception((string) $event->_error);

        (new EventBus())->Dispatch($event);
    }
}
