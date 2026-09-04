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
        elseif ($stepDefinition->counter() === 0 or empty($stepDefinition->command)):
            // Defensa en profundidad — un StepExecution que referencia
            // un WorkflowStepDefinition inexistente o sin comando
            // (dato corrupto, borrado a mitad de camino, o cualquier
            // otro origen futuro) nunca debe llegar como NULL a
            // _substituteCredentials(string $command, ...) y tumbar
            // el proceso de cron con un TypeError no capturado. Mismo
            // criterio de fallo limpio que working_directory ausente.
            $exitCode    = 1;
            $outputLines = ['El paso no tiene un WorkflowStepDefinition válido o su comando está vacío.'];
        else:
            $substitution = $this->_substituteCredentials($stepDefinition->command, (int) $project->id);

            // Requisito 2.2 (credenciales-proyecto) — un placeholder
            // referenciando una credencial inexistente para este
            // proyecto es un fallo limpio del step, mismo criterio
            // que working_directory ausente: nunca ejecuta el
            // comando con el placeholder literal sin sustituir.
            if (!empty($substitution['missing'])):
                $exitCode    = 1;
                $outputLines = ['Faltan credenciales referenciadas: ' . implode(', ', $substitution['missing'])];
            else:
                exec('cd ' . escapeshellarg($project->working_directory) . ' && ' . $substitution['command'] . ' 2>&1', $outputLines, $exitCode);
            endif;
        endif;

        $stepExecution->exit_code    = $exitCode;
        $stepExecution->output       = $this->_maskCredentials(implode("\n", $outputLines), (int) $project->id);
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

    /**
     * Sustituye {{credential:nombre}} por el valor real descifrado,
     * en memoria, justo antes de exec() — $stepDefinition->command
     * en BD nunca cambia (Requisito 2.3, credenciales-proyecto).
     * Cualquier placeholder que no coincide con una ProjectCredential
     * de este proyecto queda listado en 'missing' — el llamador
     * decide el fallo limpio (Requisito 2.2), esta función nunca
     * ejecuta nada ni lanza excepción por sí misma.
     */
    private function _substituteCredentials(string $command, int $projectId): array {
        $credentials = $this->ProjectCredential->Find(['conditions' => [['project_id', $projectId]]]);
        $missing     = [];

        foreach ($credentials as $credential):
            $placeholder = '{{credential:' . $credential->name . '}}';
            str_contains($command, $placeholder)
                and ($command = str_replace($placeholder, $credential->DecryptedValue(), $command));
        endforeach;

        preg_match_all('/\{\{credential:([a-zA-Z0-9_]+)\}\}/', $command, $matches);
        $missing = $matches[1];

        return ['command' => $command, 'missing' => $missing];
    }

    /**
     * Enmascara en el output capturado el valor real de CUALQUIER
     * credencial del proyecto (Requisito 3.1, credenciales-proyecto)
     * — no solo la referenciada en el command de este step, mismo
     * criterio que GitHub Actions. Se aplica antes de persistir
     * StepExecution.output, nunca como filtro de vista.
     */
    private function _maskCredentials(string $output, int $projectId): string {
        $credentials = $this->ProjectCredential->Find(['conditions' => [['project_id', $projectId]]]);

        foreach ($credentials as $credential):
            $output = str_replace($credential->DecryptedValue(), '***', $output);
        endforeach;

        return $output;
    }
}
