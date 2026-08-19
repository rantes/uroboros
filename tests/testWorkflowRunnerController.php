<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Commands\ExecuteWorkflowCommand;
use App\Buses\CommandBus;

class testWorkflowRunnerController extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ]);
    }

    private function _createWorkflowWithSteps(array $commands): object {
        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Runner Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        foreach ($commands as $order => $command):
            $step = $this->WorkflowStepDefinition->Niu([
                'workflow_definition_id' => $definition->id,
                'name'                   => "Step {$order}",
                'type'                   => 'build',
                'command'                => $command,
                'step_order'             => $order + 1,
            ]);
            $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);
        endforeach;

        return $definition;
    }

    /**
     * Esto es lo que prueba que la corrección del `while` (Paso 0/1)
     * funciona de verdad: un solo llamado a processpendingAction()
     * debe consumir toda la cadena disponible, no solo el primer
     * paso — sin esperar a un segundo tick de `cron`.
     */
    public function processpendingProcessesFullChainInOneCallTest(): void {
        $this->describe('processpendingAction should process the entire available chain in a single invocation');

        $definition = $this->_createWorkflowWithSteps([
            'echo "step 1"',
            'echo "step 2"',
            'echo "step 3"',
        ]);

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);

        $pendingBefore = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id], ['status', 'pending']],
        ]);
        $this->assertEquals(1, $pendingBefore->counter(), 'Only the first step should be queued before the runner runs');

        $this->_runAction('/workflow_runner/processpending');

        $completedExecution = $this->WorkflowExecution->Find((int) $execution->id);
        $this->assertEquals('completed', $completedExecution->status, 'The whole chain should complete within this single invocation');
        $this->assertNotFalse($completedExecution->completed_at, 'Should have set completed_at');

        $allSteps = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);
        $this->assertEquals(3, $allSteps->counter(), 'All three steps should have been created and run in this single call');

        $stillPending = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id], ['status', 'pending']],
        ]);
        $this->assertEquals(0, $stillPending->counter(), 'Nothing should remain pending after a single processpendingAction call');

        // Cada StepExecution debe estar completed, y el orden en que
        // se ejecutaron (id ASC, orden de creación por las Reactions)
        // debe coincidir con el step_order de su WorkflowStepDefinition
        // — no basta con que "nada quedó pending", hay que confirmar
        // que ninguno terminó en un estado intermedio y que el orden
        // de ejecución respetó step_order.
        $orderedSteps = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id]],
            'sort'       => '`id` ASC',
        ]);

        $expectedOrder = 1;
        foreach ($orderedSteps as $stepExecution):
            $this->assertEquals('completed', $stepExecution->status, "StepExecution #{$stepExecution->id} debe estar completed");

            $stepDefinition = $this->WorkflowStepDefinition->Find((int) $stepExecution->workflow_step_definition_id);
            $this->assertEquals($expectedOrder, (int) $stepDefinition->step_order, 'El orden de ejecución debe respetar step_order');
            $expectedOrder++;
        endforeach;
    }

    public function processpendingStopsChainOnFailureTest(): void {
        $this->describe('When a step fails, subsequent steps should never be queued and the workflow should end failed');

        $definition = $this->_createWorkflowWithSteps([
            'echo "step 1"',
            'exit 1',
            'echo "should never run"',
        ]);

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);

        $this->_runAction('/workflow_runner/processpending');

        $failedExecution = $this->WorkflowExecution->Find((int) $execution->id);
        $this->assertEquals('failed', $failedExecution->status, 'Workflow should end failed');
        $this->assertNotFalse($failedExecution->completed_at, 'Should have set completed_at');

        $allSteps = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);
        $this->assertEquals(2, $allSteps->counter(), 'Only the first two steps should exist — the third never gets queued after the failure');

        $failedStep = $this->StepExecution->Find([
            ':first',
            'conditions' => [['workflow_execution_id', $execution->id], ['status', 'failed']],
        ]);
        $this->assertEquals(1, (int) $failedStep->exit_code, 'Should have recorded the real exit_code of the failing step');
    }
}
