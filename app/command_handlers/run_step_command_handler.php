<?php
namespace App\CommandHandlers;

use App\Commands\RunStepCommand;
use App\Commands\FailStepCommand;
use App\Buses\EventBus;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class RunStepCommandHandler extends Controller {

    /** Sobrescribible (tests); por defecto STEP_TIMEOUT_SECONDS. */
    public int $timeoutSeconds = STEP_TIMEOUT_SECONDS;

    /**
     * SOLO se despacha desde el controlador de background
     * (WorkflowRunnerController, Parte 3) — nunca desde una Reaction
     * ni desde un request HTTP. Ejecuta el comando externo de forma
     * bloqueante dentro de ese proceso. Ver design.md — "Ejecución
     * real del script externo".
     *
     * Ningún fallo deja el paso en `running`: cualquier Throwable
     * cierra el paso vía FailStepCommand (runner-pasos-atascados).
     */
    public function Handle(RunStepCommand $command): void {
        $error = null;

        try {
            $this->_execute($command);
        } catch (\Throwable $e) {
            $error = $e;
        }

        $error === null or $this->_failStep($command->stepExecutionId, $error);
    }

    private function _failStep(int $stepId, \Throwable $error): void {
        $reason = 'Error interno del runner: ' . get_class($error) . ': ' . substr($error->getMessage(), 0, 500);

        // Último recurso, siempre: ids en el log del runner.
        error_log("RunStepCommandHandler: paso #{$stepId} falló - {$reason}");

        try {
            (new CommandBus())->Dispatch(new FailStepCommand($stepId, $reason));
        } catch (\Throwable $e) {
            error_log("RunStepCommandHandler: NO se pudo cerrar el paso #{$stepId} (queda sin cerrar): " . $e->getMessage());
        }
    }

    /**
     * Guarda el resultado final. Si el guardado falla (excepción o
     * false) reintenta UNA vez con salida mínima, conservando el
     * estado real. Devuelve false solo si ambos intentos fallan.
     */
    private function _saveResult(object $stepExecution, string $output): bool {
        $saved    = false;
        $attempts = [$output, '[Uroboros] No se pudo guardar la salida completa del paso (ver log del runner).'];

        foreach ($attempts as $text):
            if (!$saved):
                try {
                    $stepExecution->output = $text;
                    $saved                 = (bool) $stepExecution->Save();
                } catch (\Throwable $e) {
                    error_log("RunStepCommandHandler: Save() del paso #{$stepExecution->id} falló: " . $e->getMessage());
                }
            endif;
        endforeach;

        return $saved;
    }

    private function _execute(RunStepCommand $command): void {
        $this->LoadHelper('StepOutput');

        // No dispatchado vía HTTP — el auto-load de $this->helper del
        // framework (DumboPHP\index::__construct()) nunca corre para
        // esta clase. LoadHelper() no depende de eso, es seguro
        // llamarlo directo.
        $this->LoadHelper('ProjectDirectory');

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
        else:
            $directoryCheck = $this->_prepareWorkingDirectory($project->working_directory);
            $outputLines    = $directoryCheck['log'];

            if (!$directoryCheck['ready']):
                $exitCode      = 1;
                $outputLines[] = $directoryCheck['error'];
            elseif ($stepDefinition->counter() === 0 or empty($stepDefinition->command)):
                // Defensa en profundidad — un StepExecution que referencia
                // un WorkflowStepDefinition inexistente o sin comando
                // (dato corrupto, borrado a mitad de camino, o cualquier
                // otro origen futuro) nunca debe llegar como NULL a
                // _substituteCredentials(string $command, ...) y tumbar
                // el proceso de cron con un TypeError no capturado. Mismo
                // criterio de fallo limpio que working_directory ausente.
                $exitCode      = 1;
                $outputLines[] = 'El paso no tiene un WorkflowStepDefinition válido o su comando está vacío.';
            else:
                $substitution = $this->_substituteCredentials($stepDefinition->command, (int) $project->id);

                // Solo el nombre de la credencial va al log — nunca el
                // valor. _substituteCredentials() ya sustituyó el valor
                // real dentro de $substitution['command'] (nunca
                // logueado tal cual), y _maskCredentials() más abajo
                // vuelve a pasar sobre TODO el output (incluido este log)
                // como defensa adicional, no como el único mecanismo.
                foreach ($substitution['substituted'] as $credentialName):
                    $outputLines[] = "[Uroboros] Sustituyendo credencial: {$credentialName}";
                endforeach;

                // Requisito 2.2 (credenciales-proyecto) — un placeholder
                // referenciando una credencial inexistente para este
                // proyecto es un fallo limpio del step, mismo criterio
                // que working_directory ausente: nunca ejecuta el
                // comando con el placeholder literal sin sustituir.
                if (!empty($substitution['missing'])):
                    $exitCode      = 1;
                    $outputLines[] = 'Faltan credenciales referenciadas: ' . implode(', ', $substitution['missing']);
                else:
                    $outputLines[] = "[Uroboros] cd {$project->working_directory}";
                    $outputLines[] = '--- Salida del comando ---';
                    $startedAt = time();
                    // `timeout` mata el grupo de procesos (hijos incluidos);
                    // --kill-after escala a SIGKILL si ignoran SIGTERM.
                    $result      = $this->_runCommand(
                        'cd ' . escapeshellarg($project->working_directory)
                        . ' && timeout --kill-after=' . (int) STEP_TIMEOUT_KILL_AFTER_SECONDS . ' ' . (int) $this->timeoutSeconds
                        . ' sh -c ' . escapeshellarg($substitution['command']) . ' 2>&1'
                    );
                    $outputLines = array_merge($outputLines, $result['lines']);
                    $exitCode    = $result['exit'];

                    // 124 = timeout; 137 = SIGKILL tras --kill-after (solo
                    // cuenta como timeout si realmente se agotó el tiempo).
                    $timedOut = $exitCode === STEP_TIMEOUT_EXIT_CODE
                        || ($exitCode === 137 && (time() - $startedAt) >= $this->timeoutSeconds);
                    $timedOut and ($outputLines[] = "[Uroboros] Tiempo agotado tras {$this->timeoutSeconds} s");
                endif;
            endif;
        endif;

        // Enmascarar ANTES de truncar: un secreto partido por el corte
        // no se reconocería después.
        $finalOutput = truncateStepOutput($this->_maskCredentials(implode("\n", $outputLines), (int) $project->id));

        $stepExecution->exit_code    = $exitCode;
        $stepExecution->completed_at = time();
        $stepExecution->status       = $exitCode === 0 ? 'completed' : 'failed';
        $this->_saveResult($stepExecution, $finalOutput)
            or throw new \Exception('No se pudo guardar el resultado del paso #' . $stepExecution->id);

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
     * Ejecuta el comando (stdout; el 2>&1 va dentro del string) SIN
     * que el hijo herede el lock del runner. PHP abre el archivo del
     * lock sin close-on-exec, así que exec()/proc_open() lo pasan al
     * hijo; un hijo huérfano tras un kill -9 al runner retendría el
     * flock. proc_open permite reemplazar ese fd en el hijo por
     * /dev/null (dup2), con cualquier número de fd y sin extensiones.
     */
    private function _runCommand(string $shellCommand): array {
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w']];
        $pipes       = [];
        $lines       = [];
        $exit        = 1;

        foreach ($this->_lockDescriptors() as $fd):
            $descriptors[$fd] = ['file', '/dev/null', 'r'];
        endforeach;

        $process = proc_open($shellCommand, $descriptors, $pipes);

        if (is_resource($process)):
            $raw = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit  = proc_close($process);
            $lines = $raw === '' ? [] : array_map('rtrim', explode("\n", rtrim($raw, "\n")));
        else:
            $lines = ['[Uroboros] No se pudo lanzar el comando del paso.'];
        endif;

        return ['lines' => $lines, 'exit' => $exit];
    }

    /**
     * Números de fd (>2) de ESTE proceso que apuntan al archivo del
     * lock. Linux (/proc); sin /proc devuelve [] y no hay override.
     */
    private function _lockDescriptors(): array {
        $lockPath = realpath(STEP_RUNNER_LOCK_FILE);
        $found    = [];

        foreach ((array) glob('/proc/self/fd/*') as $entry):
            $fd = (int) basename($entry);
            if ($lockPath !== false && $fd > 2 && @readlink($entry) === $lockPath):
                $found[] = $fd;
            endif;
        endforeach;

        return $found;
    }

    /**
     * Verifica/crea/corrige permisos del working_directory con
     * ensureWritableProjectDirectory() (ProjectDirectory_Helper.php),
     * devolviendo el log verboso de lo intentado (Requisito de log de
     * orquestación) junto con si quedó listo para usar. El mensaje de
     * error distingue dos causas reales, no una genérica: el
     * directorio nunca existió y no se pudo crear, vs. el directorio
     * existe pero el proceso actual no pudo corregirle los permisos
     * (típicamente porque no es su dueño — chmod() en Linux solo lo
     * puede hacer el dueño o root, nunca un mero miembro del grupo;
     * confirmado empíricamente).
     */
    private function _prepareWorkingDirectory(string $path): array {
        $log = ["[Uroboros] Verificando working_directory: {$path}"];

        $existedBefore = is_dir($path);
        // projectDirectoryMissingGroupWrite(), no is_writable() — el
        // chmod real de ensureWritableProjectDirectory() se dispara
        // por el bit de grupo ausente en el modo, no por si el
        // proceso actual ya puede escribir (que da true por el bit de
        // dueño incluso cuando el bit de grupo sigue faltando — ver
        // ProjectDirectory_Helper.php). El log debe reflejar la misma
        // condición que realmente dispara la acción.
        $neededFixBefore = $existedBefore and projectDirectoryMissingGroupWrite($path);

        if (!$existedBefore):
            $log[] = '[Uroboros] Directorio no existe — creando (mkdir 0775)';
        elseif ($neededFixBefore):
            $log[] = '[Uroboros] Directorio existe, permisos insuficientes — intentando corregir (chmod 0775)';
        endif;

        $ready = ensureWritableProjectDirectory($path);

        if (!$existedBefore):
            $log[] = is_dir($path)
                ? '[Uroboros] Directorio creado correctamente'
                : '[Uroboros] No se pudo crear el directorio de trabajo';
        elseif ($neededFixBefore):
            $log[] = is_writable($path)
                ? '[Uroboros] Permisos corregidos correctamente'
                : '[Uroboros] No se pudieron corregir los permisos automáticamente';
        endif;

        $error = null;
        if (!$ready and !is_dir($path)):
            $error = "No se pudo crear el directorio de trabajo: {$path}";
        elseif (!$ready):
            $error = "El directorio de trabajo existe pero no tiene permisos de escritura para el usuario que ejecuta los Workflows — verifica el dueño/grupo real ({$path}).";
        endif;

        return ['ready' => $ready, 'log' => $log, 'error' => $error];
    }

    /**
     * Sustituye {{credential:nombre}} por el valor real descifrado,
     * en memoria, justo antes de exec() — $stepDefinition->command
     * en BD nunca cambia (Requisito 2.3, credenciales-proyecto).
     * Cualquier placeholder que no coincide con una ProjectCredential
     * de este proyecto queda listado en 'missing' — el llamador
     * decide el fallo limpio (Requisito 2.2), esta función nunca
     * ejecuta nada ni lanza excepción por sí misma. 'substituted'
     * lleva solo los NOMBRES realmente sustituidos — el llamador los
     * usa para el log de orquestación, nunca el valor descifrado.
     */
    private function _substituteCredentials(string $command, int $projectId): array {
        $credentials = $this->ProjectCredential->Find(['conditions' => [['project_id', $projectId]]]);
        $substituted = [];

        foreach ($credentials as $credential):
            $placeholder = '{{credential:' . $credential->name . '}}';
            if (str_contains($command, $placeholder)):
                $command       = str_replace($placeholder, $credential->DecryptedValue(), $command);
                $substituted[] = $credential->name;
            endif;
        endforeach;

        preg_match_all('/\{\{credential:([a-zA-Z0-9_]+)\}\}/', $command, $matches);
        $missing = $matches[1];

        return ['command' => $command, 'missing' => $missing, 'substituted' => $substituted];
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
