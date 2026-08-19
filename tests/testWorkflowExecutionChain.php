<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
use App\Commands\ExecuteWorkflowCommand;
use App\Commands\RunStepCommand;
use App\Buses\CommandBus;

class testWorkflowExecutionChain extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ]);
    }

    private function _createWorkflowWithSteps(): object {
        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Deploy Backend',
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        $step1 = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Build',
            'type'                   => 'build',
            'command'                => 'echo build',
            'step_order'             => 1,
        ]);
        $step1->Save() or trigger_error((string) $step1->_error, E_USER_ERROR);

        $step2 = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Deploy',
            'type'                   => 'deploy',
            'command'                => 'echo deploy',
            'step_order'             => 2,
        ]);
        $step2->Save() or trigger_error((string) $step2->_error, E_USER_ERROR);

        return $definition;
    }

    /**
     * RunStepCommand todavía no se dispara automáticamente (eso es la
     * Parte 3, desde el controlador de background) — esta prueba
     * confirma explícitamente que la cadena se detiene en
     * StepExecution(status=pending), no que el workflow se completa.
     */
    public function executeWorkflowQueuesFirstStepAndStopsTest(): void {
        $this->describe('Should chain ExecuteWorkflowCommand -> WorkflowStarted -> Reaction -> QueueStepCommand -> StepQueued, and stop there');

        $definition = $this->_createWorkflowWithSteps();

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $this->assertEquals(1, $execution->counter(), 'Should have created exactly one WorkflowExecution');
        $this->assertEquals('pending', $execution->status, 'WorkflowExecution should remain pending — RunStepCommand no se dispara todavía');
        $this->assertEquals('manual', $execution->trigger_type, 'Should preserve the trigger_type passed to the Command');

        $stepExecution = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);
        $this->assertEquals(1, $stepExecution->counter(), 'Should have queued exactly the first step, never the second');
        $this->assertEquals('pending', $stepExecution->status, 'StepExecution should be pending — RunStepCommand no se dispara todavía');
        $this->assertTrue(empty($stepExecution->exit_code), 'exit_code should still be empty — no se ejecutó nada');

        $firstStep = $this->WorkflowStepDefinition->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
            'sort'       => '`step_order` ASC',
        ]);
        $this->assertEquals($firstStep->id, $stepExecution->workflow_step_definition_id, 'Should have queued the step with the lowest step_order, not the second one');

        $startedEvents = $this->Event->Find(['conditions' => [['event_type', 'WorkflowStarted']]]);
        $this->assertEquals(1, $startedEvents->counter(), 'Should have exactly one WorkflowStarted event');

        $queuedEvents = $this->Event->Find(['conditions' => [['event_type', 'StepQueued']]]);
        $this->assertEquals(1, $queuedEvents->counter(), 'Should have exactly one StepQueued event');

        $completedEvents = $this->Event->Find(['conditions' => [['event_type', 'StepCompleted']]]);
        $this->assertEquals(0, $completedEvents->counter(), 'Should NOT have any StepCompleted event — RunStepCommand never dispatched in this part');

        $workflowCompletedEvents = $this->Event->Find(['conditions' => [['event_type', 'WorkflowCompleted']]]);
        $this->assertEquals(0, $workflowCompletedEvents->counter(), 'Should NOT have any WorkflowCompleted event yet');
    }

    /**
     * RunStepCommand no se dispara todavía desde ningún flujo
     * automático (eso es la Parte 3, desde el controlador de
     * background) — probado aquí de forma aislada, invocado
     * directamente, tal como permite el Paso 2 de esta parte.
     */
    public function runStepCommandCompletesStepAndQueuesNextStepTest(): void {
        $this->describe('RunStepCommand should mark the step completed and OnStepCompletedReaction should queue the next step');

        $definition = $this->_createWorkflowWithSteps();

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $stepExecution = $this->StepExecution->Find([
            ':first',
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);

        (new CommandBus())->Dispatch(new RunStepCommand((int) $stepExecution->id));

        $completed = $this->StepExecution->Find((int) $stepExecution->id);
        $this->assertEquals('completed', $completed->status, 'Should mark the step completed on exit_code=0');
        $this->assertEquals(0, (int) $completed->exit_code, 'Should record exit_code=0');
        $this->assertTrue(str_contains((string) $completed->output, 'build'), 'Should capture the command output');

        $secondStep = $this->WorkflowStepDefinition->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id], ['step_order', 2]],
        ]);
        $queuedSecondStep = $this->StepExecution->Find([
            'conditions' => [
                ['workflow_execution_id', $execution->id],
                ['workflow_step_definition_id', $secondStep->id],
            ],
        ]);
        $this->assertEquals(1, $queuedSecondStep->counter(), 'OnStepCompletedReaction should have queued the second step');
        $this->assertEquals('pending', $queuedSecondStep->status, 'The queued next step should be pending — not run yet');

        $workflowCompletedEvents = $this->Event->Find(['conditions' => [['event_type', 'WorkflowCompleted']]]);
        $this->assertEquals(0, $workflowCompletedEvents->counter(), 'Should NOT complete the workflow — there is still a second step pending');
    }

    public function runStepCommandFailsStepAndFailsWorkflowTest(): void {
        $this->describe('RunStepCommand should mark the step failed and OnStepFailedReaction should fail the workflow');

        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Broken Workflow',
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Broken Step',
            'type'                   => 'build',
            'command'                => 'exit 1',
            'step_order'             => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'manual'));

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $stepExecution = $this->StepExecution->Find([
            ':first',
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);

        (new CommandBus())->Dispatch(new RunStepCommand((int) $stepExecution->id));

        $failedStep = $this->StepExecution->Find((int) $stepExecution->id);
        $this->assertEquals('failed', $failedStep->status, 'Should mark the step failed on non-zero exit_code');
        $this->assertEquals(1, (int) $failedStep->exit_code, 'Should record the real exit_code');

        $failedExecution = $this->WorkflowExecution->Find((int) $execution->id);
        $this->assertEquals('failed', $failedExecution->status, 'FailWorkflowCommand should have marked the WorkflowExecution as failed');
        $this->assertNotFalse($failedExecution->completed_at, 'Should have set completed_at');

        $stepFailedEvents = $this->Event->Find(['conditions' => [['event_type', 'StepFailed']]]);
        $this->assertEquals(1, $stepFailedEvents->counter(), 'Should have exactly one StepFailed event');

        $workflowFailedEvents = $this->Event->Find(['conditions' => [['event_type', 'WorkflowFailed']]]);
        $this->assertEquals(1, $workflowFailedEvents->counter(), 'Should have exactly one WorkflowFailed event');
    }

    public function lastStepCompletedCompletesWorkflowTest(): void {
        $this->describe('When the last step completes, OnStepCompletedReaction should dispatch CompleteWorkflowCommand');

        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Single Step Workflow',
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Only Step',
            'type'                   => 'verification',
            'command'                => 'echo ok',
            'step_order'             => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        (new CommandBus())->Dispatch(new ExecuteWorkflowCommand((int) $definition->id, 'webhook'));

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $stepExecution = $this->StepExecution->Find([
            ':first',
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);

        (new CommandBus())->Dispatch(new RunStepCommand((int) $stepExecution->id));

        $completedExecution = $this->WorkflowExecution->Find((int) $execution->id);
        $this->assertEquals('completed', $completedExecution->status, 'Should mark the WorkflowExecution as completed when there are no more steps');
        $this->assertNotFalse($completedExecution->completed_at, 'Should have set completed_at');

        $allStepExecutions = $this->StepExecution->Find([
            'conditions' => [['workflow_execution_id', $execution->id]],
        ]);
        $this->assertEquals(1, $allStepExecutions->counter(), 'Should not have queued any further step — this was the only one');

        $workflowCompletedEvents = $this->Event->Find(['conditions' => [['event_type', 'WorkflowCompleted']]]);
        $this->assertEquals(1, $workflowCompletedEvents->counter(), 'Should have exactly one WorkflowCompleted event');
    }
}
