<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Commands\ExecuteWorkflowCommand;
use App\Commands\RunStepCommand;
use App\CommandHandlers\RunStepCommandHandler;
use App\Buses\CommandBus;

require_once INST_PATH . 'app/helpers/StepOutput_Helper.php';

class testRunnerStuckSteps extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'projects',
            'project_credentials',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ]);
    }

    /** Crea workflow de 1 paso y lo encola; devuelve [execution, stepExecution]. */
    private function _queueStep(string $command): array {
        $project = $this->Project->Niu([
            'name' => 'Runner ' . bin2hex(random_bytes(4)), 'type' => 'backend',
            'status' => 1, 'working_directory' => sys_get_temp_dir(),
        ]);
        $project->Save() or trigger_error((string) $project->_error, E_USER_ERROR);

        $definition = $this->WorkflowDefinition->Niu([
            'name' => 'WF ' . bin2hex(random_bytes(4)), 'project_id' => $project->id,
            'status' => 1, 'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id, 'name' => 'S', 'type' => 'build',
            'command' => $command, 'step_order' => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([':first', 'conditions' => [['workflow_definition_id', $definition->id]]]);
        $stepExec  = $this->StepExecution->Find([':first', 'conditions' => [['workflow_execution_id', $execution->id]]]);

        return [$execution, $stepExec];
    }

    public function handlerFailureLeavesStepFailedTest(): void {
        $this->describe('a failure inside RunStepCommandHandler never leaves the step running');

        [$execution, $stepExec] = $this->_queueStep('echo hi');
        // Fallo forzado: sin la tabla de credenciales el handler lanza a mitad del paso.
        $this->_dropTables(['project_credentials']);

        (new CommandBus())->Dispatch(new RunStepCommand((int) $stepExec->id));

        $after = $this->StepExecution->Find((int) $stepExec->id);
        $this->assertEquals('failed', $after->status, 'Step must be failed, not running/pending');
        $this->assertTrue(str_contains((string) $after->output, 'Error interno del runner'));
    }

    public function truncateKeepsShortOutputIntactTest(): void {
        $this->describe('short output is untouched');
        $this->assertEquals("ok\nñandú", truncateStepOutput("ok\nñandú"));
    }

    public function truncateLongOutputTest(): void {
        $this->describe('long output keeps head and tail, marker present, within limit');
        $text   = '[Uroboros] inicio' . str_repeat("linea\n", 40000) . 'FINAL-CAUSA';
        $result = truncateStepOutput($text);

        $this->assertTrue(strlen($result) <= STEP_OUTPUT_MAX_BYTES);
        $this->assertTrue(str_starts_with($result, '[Uroboros] inicio'));
        $this->assertTrue(str_ends_with($result, 'FINAL-CAUSA'));
        $this->assertTrue((bool) preg_match('/\[\.\.\. \d+ bytes omitidos \.\.\.\]/', $result));
    }

    public function truncateKeepsValidUtf8AtEveryCutOffsetTest(): void {
        $this->describe('multibyte right at the cut never yields invalid UTF-8');

        // Cualquier desfase de 0..3 bytes deja el multibyte justo en el corte.
        foreach ([0, 1, 2, 3] as $shift):
            $text   = str_repeat('a', $shift) . str_repeat('ñ€😀', 30000);
            $result = truncateStepOutput($text);
            $this->assertTrue((bool) preg_match('//u', $result), "Invalid UTF-8 with shift {$shift}");
            $this->assertTrue($result !== '' && $result !== false);
            $this->assertTrue(strlen($result) <= STEP_OUTPUT_MAX_BYTES);
        endforeach;
    }

    public function truncateSanitizesInvalidInputTest(): void {
        $this->describe('invalid bytes never produce false or empty output');
        $result = truncateStepOutput("a\xFF\xFEb" . str_repeat("\xC3", 70000) . 'fin');

        $this->assertTrue((bool) preg_match('//u', $result));
        $this->assertTrue($result !== '');
        $this->assertEquals('a' . 'b', substr(sanitizeUtf8("a\xFF\xFEb"), 0));
    }

    public function secretSplitByCutIsStillMaskedTest(): void {
        $this->describe('credential is masked before truncation, so a cut cannot leak it');

        $project = $this->Project->Niu([
            'name' => 'Sec', 'type' => 'backend', 'status' => 1, 'working_directory' => sys_get_temp_dir(),
        ]);
        $project->Save();

        $secret = 'SUPERSECRETVALUE1234';
        // Secreto justo en el límite de la cabeza (8000 bytes).
        $text = str_repeat('x', STEP_OUTPUT_HEAD_BYTES - 5) . $secret . str_repeat('y', 100000);
        $masked = str_replace($secret, '***', $text); // mismo orden que el handler: mask -> truncate
        $result = truncateStepOutput($masked);

        $this->assertFalse(str_contains($result, 'SUPERSECRET'));
        $this->assertFalse(str_contains($result, '1234'));
    }

    public function timeoutFailsStepTest(): void {
        $this->describe('a command exceeding the timeout fails with the expected message');

        [$execution, $stepExec] = $this->_queueStep('sleep 20');
        $handler                 = new RunStepCommandHandler();
        $handler->timeoutSeconds = 1;

        $started = time();
        $handler->Handle(new RunStepCommand((int) $stepExec->id));

        $after = $this->StepExecution->Find((int) $stepExec->id);
        $this->assertEquals('failed', $after->status);
        $this->assertTrue(str_contains((string) $after->output, 'Tiempo agotado tras 1 s'));
        $this->assertTrue((time() - $started) < 10, 'Must not wait for the full sleep');
    }

    public function secondRunnerExitsWithoutProcessingTest(): void {
        $this->describe('while another runner holds the lock, processpending does nothing');

        [$execution, $stepExec] = $this->_queueStep('echo hi');

        $lock = fopen(STEP_RUNNER_LOCK_FILE, 'c');
        flock($lock, LOCK_EX | LOCK_NB);
        $this->_runAction('/workflow_runner/processpending');
        flock($lock, LOCK_UN);
        fclose($lock);

        $this->assertEquals('pending', $this->StepExecution->Find((int) $stepExec->id)->status);

        $this->_runAction('/workflow_runner/processpending');
        $this->assertEquals('completed', $this->StepExecution->Find((int) $stepExec->id)->status);
    }

    public function stuckDetectorTest(): void {
        $this->describe('old running step becomes failed, recent one is untouched');

        [$execOld, $stepOld] = $this->_queueStep('echo hi');
        [$execNew, $stepNew] = $this->_queueStep('echo hi');

        $stepOld->status     = 'running';
        $stepOld->started_at = time() - STEP_TIMEOUT_SECONDS - STEP_STUCK_GRACE_SECONDS - 60;
        $stepOld->Save();
        $stepNew->status     = 'running';
        $stepNew->started_at = time() - 60;
        $stepNew->Save();

        $this->_runAction('/workflow_runner/processpending');

        $old = $this->StepExecution->Find((int) $stepOld->id);
        $this->assertEquals('failed', $old->status);
        $this->assertTrue(str_contains((string) $old->output, 'Interrumpido'));
        $this->assertEquals('failed', $this->WorkflowExecution->Find((int) $execOld->id)->status);

        $this->assertEquals('running', $this->StepExecution->Find((int) $stepNew->id)->status);
    }
    // ---- Ajuste 1: el hijo no hereda el lock -------------------------

    public function childDoesNotInheritLockTest(): void {
        $this->describe('the step command does not inherit the runner lock descriptor');

        [$execution, $stepExec] = $this->_queueStep('ls -l /proc/self/fd');

        $lock = fopen(STEP_RUNNER_LOCK_FILE, 'c');
        flock($lock, LOCK_EX | LOCK_NB);
        $mine = glob('/proc/self/fd/*');
        $held = count(array_filter($mine, fn($p) => @readlink($p) === realpath(STEP_RUNNER_LOCK_FILE)));

        (new RunStepCommandHandler())->Handle(new RunStepCommand((int) $stepExec->id));
        flock($lock, LOCK_UN);
        fclose($lock);

        $after = $this->StepExecution->Find((int) $stepExec->id);
        $this->assertTrue($held >= 1, 'Control: the test process really holds the lock fd');
        $this->assertEquals('completed', $after->status);
        $this->assertFalse(str_contains((string) $after->output, 'workflow_runner.lock'), 'Child must not see the lock file');
    }

    public function userCommandEscapingIsPreservedTest(): void {
        $this->describe('quotes, dollars and substitutions in the user command stay literal/intact');

        [$execution, $stepExec] = $this->_queueStep("printf '%s\\n' \"a'b\" 'c\"d' \"\$(echo sub)\"");
        (new RunStepCommandHandler())->Handle(new RunStepCommand((int) $stepExec->id));

        $output = (string) $this->StepExecution->Find((int) $stepExec->id)->output;
        $this->assertTrue(str_contains($output, "a'b\nc\"d\nsub"), 'Output was: ' . $output);
    }

    // ---- Ajuste 2: ejecuciones running sin pasos activos --------------

    /** Ejecución running cuyo único paso ya terminó hace $ageSeconds. */
    private function _orphanExecution(int $ageSeconds, string $stepStatus = 'completed'): array {
        [$execution, $stepExec] = $this->_queueStep('echo hi');

        $stepExec->status       = $stepStatus;
        $stepExec->completed_at = time() - $ageSeconds;
        $stepExec->Save();
        $execution->status     = 'running';
        $execution->started_at = time() - $ageSeconds;
        $execution->Save();
        // Save() reescribe updated_at (AUTO_AUDITS): retrodatar sólo en el fixture.
        DB->exec('UPDATE workflow_executions SET updated_at = ' . (time() - $ageSeconds) . ' WHERE id = ' . (int) $execution->id);

        return [$execution, $stepExec];
    }

    public function orphanExecutionOldIsFailedTest(): void {
        $this->describe('old running execution without active steps becomes failed through the event path');

        [$execution] = $this->_orphanExecution(STEP_STUCK_GRACE_SECONDS + 600);
        $this->_runAction('/workflow_runner/processpending');

        $this->assertEquals('failed', $this->WorkflowExecution->Find((int) $execution->id)->status);
        $event = $this->Event->Find([':first', 'conditions' => [['aggregate_type', 'WorkflowExecution'], ['aggregate_id', $execution->id], ['event_type', 'WorkflowFailed']]]);
        $this->assertTrue(str_contains((string) $event->payload, 'sin pasos activos'), 'Reason goes in the WorkflowFailed payload');
    }

    public function orphanExecutionRecentIsUntouchedTest(): void {
        $this->describe('recent running execution without active steps is left alone');

        [$execution] = $this->_orphanExecution(30);
        $this->_runAction('/workflow_runner/processpending');

        $this->assertEquals('running', $this->WorkflowExecution->Find((int) $execution->id)->status);
    }

    public function orphanExecutionWithRecentStepActivityIsUntouchedTest(): void {
        $this->describe('old execution whose last step finished recently is left alone');

        [$execution, $stepExec] = $this->_orphanExecution(STEP_STUCK_GRACE_SECONDS + 600);
        $stepExec->completed_at = time() - 20;
        $stepExec->Save();
        $this->_runAction('/workflow_runner/processpending');

        $this->assertEquals('running', $this->WorkflowExecution->Find((int) $execution->id)->status);
    }

    public function orphanExecutionWithActiveStepIsUntouchedTest(): void {
        $this->describe('old execution with a pending step is processed, never closed as orphan');

        [$execution, $stepExec] = $this->_orphanExecution(STEP_STUCK_GRACE_SECONDS + 600, 'pending');
        $stepExec->completed_at = null;
        $stepExec->Save();
        $this->_runAction('/workflow_runner/processpending');

        $this->assertEquals('completed', $this->WorkflowExecution->Find((int) $execution->id)->status);
    }

    public function orphanRollbackExecutionWithActiveStepIsUntouchedTest(): void {
        $this->describe('a rollback execution in progress (pending rollback step) is not closed');

        [$execution, $stepExec] = $this->_orphanExecution(STEP_STUCK_GRACE_SECONDS + 600, 'pending');
        $stepDef       = $this->WorkflowStepDefinition->Find((int) $stepExec->workflow_step_definition_id);
        $stepDef->type = 'rollback';
        $stepDef->Save();
        $stepExec->completed_at = null;
        $stepExec->Save();

        $this->_runAction('/workflow_runner/processpending');

        $this->assertEquals('completed', $this->WorkflowExecution->Find((int) $execution->id)->status);
    }
}