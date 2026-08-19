<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testAdminController extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'oem_metrics',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ]);
        $_SERVER['HTTP_x-sf-token'] = 'token';
        $_SESSION['xsfr_token']     = 'token';
    }

    public function eventsListRendersWithoutErrorTest(): void {
        $this->describe('GET /admin/events debe listar sin errores fatales');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'ExplorerListTest',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/events');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'GET /admin/events debe responder 200');
        $this->assertEquals(1, $result->data->counter(), 'Debe listar el Event creado arriba');
    }

    /**
     * Nota sobre por qué estos tres tests no assertan $result->_code:
     * AdminBaseTrait::landingAction() reporta el código de la
     * ControllerException vía $this->setResponseCode($code), que
     * escribe DumboPHP\Controller::$_http_response_code (privado, el
     * que sí llega al cliente real vía http_response_code()) — nunca
     * escribe MainController::$_code (la propiedad pública que
     * $result->_code expone y que el resto de este proyecto usa para
     * aserciones). Es un bug preexistente del trait compartido, no
     * introducido por este guard, y arreglarlo está fuera de alcance
     * de explorador-eventos. Verificado con curl contra el servidor
     * real (https://uroboros.rantes.local) que las tres respuestas
     * SÍ son HTTP 405 de verdad — ver reporte. Aquí se assertea lo
     * que _runAction() sí refleja de forma confiable: que ninguna
     * escritura tuvo efecto real sobre la tabla `events`.
     */
    public function eventsCreateIsBlockedTest(): void {
        $this->describe('POST /admin/events/add no debe crear ninguna fila (bloqueado por el guard)');

        $before = $this->Event->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['event'] = ['aggregate_type' => 'Test', 'aggregate_id' => 0, 'event_type' => 'ShouldNotBeCreated'];
        $this->_runAction('/admin/events/add');

        $this->assertEquals($before, $this->Event->Find()->counter(), 'No debe haberse creado ninguna fila nueva');
    }

    public function eventsUpdateIsBlockedTest(): void {
        $this->describe('PUT /admin/events/{id} no debe modificar la fila (bloqueado por el guard)');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'Original',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_POST['event'] = ['event_type' => 'Hacked'];
        $this->_runAction("/admin/events/{$event->id}");

        $stillOriginal = $this->Event->Find((int) $event->id);
        $this->assertEquals('Original', $stillOriginal->event_type, 'El event_type no debe haber cambiado');
    }

    public function eventsDeleteIsBlockedTest(): void {
        $this->describe('DELETE /admin/events/{id} no debe eliminar la fila (bloqueado por el guard)');

        $event = $this->Event->Niu([
            'aggregate_type' => 'Test',
            'aggregate_id'   => 0,
            'event_type'     => 'ShouldSurvive',
            'payload'        => json_encode([]),
        ]);
        $event->Save();

        $before = $this->Event->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->_runAction("/admin/events/{$event->id}");

        $this->assertEquals($before, $this->Event->Find()->counter(), 'No debe haberse eliminado ninguna fila');
    }

    public function executeworkflowDispatchesCommandAndReturns202Test(): void {
        $this->describe('GET /admin/executeworkflow/{id} debe despachar ExecuteWorkflowCommand con trigger_type=manual y responder 202');

        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Manual Trigger Workflow',
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Only Step',
            'type'                   => 'build',
            'command'                => 'echo hi',
            'step_order'             => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/executeworkflow/{$definition->id}");

        $this->assertEquals(HTTP_202, (int) $result->_code, 'Debe responder 202 — la ejecución sigue en background');

        $execution = $this->WorkflowExecution->Find([
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $this->assertEquals(1, $execution->counter(), 'Debe haber despachado ExecuteWorkflowCommand y creado la WorkflowExecution');
        $this->assertEquals('manual', $execution->trigger_type, 'El trigger_type debe ser manual');
    }

    public function executeworkflowRequiresIdTest(): void {
        $this->describe('GET /admin/executeworkflow sin id debe responder 400 sin disparar ningún Command');

        $before = $this->WorkflowExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/executeworkflow');

        $this->assertEquals(HTTP_400, (int) $result->_code, 'Debe responder 400 sin id');
        $this->assertEquals($before, $this->WorkflowExecution->Find()->counter(), 'No debe haberse creado ninguna WorkflowExecution');
    }

    private function _createWorkflowDefinitionFixture(): object {
        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Fixture Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        return $definition;
    }

    public function workflowStepDefinitionsListFiltersByWorkflowDefinitionIdTest(): void {
        $this->describe('GET /admin/workflow_step_definitions?workflow_definition_id=X debe listar solo los pasos de ese workflow, nunca los de otro');

        $definitionA = $this->_createWorkflowDefinitionFixture();
        $definitionB = $this->_createWorkflowDefinitionFixture();

        $stepA = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definitionA->id,
            'name'                   => 'Step A',
            'type'                   => 'build',
            'command'                => 'echo a',
            'step_order'             => 1,
        ]);
        $stepA->Save() or trigger_error((string) $stepA->_error, E_USER_ERROR);

        $stepB = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definitionB->id,
            'name'                   => 'Step B',
            'type'                   => 'build',
            'command'                => 'echo b',
            'step_order'             => 1,
        ]);
        $stepB->Save() or trigger_error((string) $stepB->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/workflow_step_definitions?workflow_definition_id={$definitionA->id}");

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(1, $result->data->counter(), 'Debe listar solo el paso del workflow A, nunca el de B');

        $listed = [];
        foreach ($result->data as $row):
            $listed[] = $row;
        endforeach;
        $this->assertEquals('Step A', $listed[0]->name, 'El único paso listado debe ser el del workflow A');
    }

    public function workflowStepDefinitionCanBeCreatedTest(): void {
        $this->describe('POST /admin/workflow_step_definitions/add debe crear un paso — no es de solo lectura');

        $definition = $this->_createWorkflowDefinitionFixture();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['workflow_step_definition'] = [
            'workflow_definition_id' => $definition->id,
            'name'                   => 'New Step',
            'type'                   => 'build',
            'command'                => 'echo new',
            'step_order'             => 1,
        ];
        $this->_runAction('/admin/workflow_step_definitions/add');

        $created = $this->WorkflowStepDefinition->Find([
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $this->assertEquals(1, $created->counter(), 'Debe haberse creado el paso — la entidad es CRUD plano, no solo lectura');
    }

    public function workflowStepDefinitionCanBeDeletedTest(): void {
        $this->describe('DELETE /admin/workflow_step_definitions/{id} debe eliminar el paso — no es de solo lectura');

        $definition = $this->_createWorkflowDefinitionFixture();
        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Deletable Step',
            'type'                   => 'build',
            'command'                => 'echo bye',
            'step_order'             => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->_runAction("/admin/workflow_step_definitions/{$step->id}");

        $this->assertEquals(0, $this->WorkflowStepDefinition->Find()->counter(), 'Debe haberse eliminado el paso');
    }

    /**
     * Confirma con evidencia real (no asumido) que agregar
     * 'workflow_execution'/'step_execution' a $_readOnlyModels basta
     * para bloquear escritura — mismo guard ya construido para
     * 'event' en explorador-eventos, sin cambios adicionales al
     * trait. Mismo patrón que eventsCreateIsBlockedTest y las notas
     * sobre por qué no se assertea $result->_code en estos casos.
     */
    public function workflowExecutionsCreateIsBlockedTest(): void {
        $this->describe('POST /admin/workflow_executions/add no debe crear ninguna fila (bloqueado por el guard)');

        $before = $this->WorkflowExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['workflow_execution'] = ['workflow_definition_id' => 1, 'status' => 'pending', 'trigger_type' => 'manual'];
        $this->_runAction('/admin/workflow_executions/add');

        $this->assertEquals($before, $this->WorkflowExecution->Find()->counter(), 'No debe haberse creado ninguna fila nueva');
    }

    public function workflowExecutionsUpdateIsBlockedTest(): void {
        $this->describe('PUT /admin/workflow_executions/{id} no debe modificar la fila (bloqueado por el guard)');

        $definition = $this->_createWorkflowDefinitionFixture();
        $execution = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $definition->id,
            'status'                 => 'pending',
            'trigger_type'           => 'manual',
        ]);
        $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_POST['workflow_execution'] = ['status' => 'completed'];
        $this->_runAction("/admin/workflow_executions/{$execution->id}");

        $stillOriginal = $this->WorkflowExecution->Find((int) $execution->id);
        $this->assertEquals('pending', $stillOriginal->status, 'El status no debe haber cambiado');
    }

    public function workflowExecutionsDeleteIsBlockedTest(): void {
        $this->describe('DELETE /admin/workflow_executions/{id} no debe eliminar la fila (bloqueado por el guard)');

        $definition = $this->_createWorkflowDefinitionFixture();
        $execution = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $definition->id,
            'status'                 => 'pending',
            'trigger_type'           => 'manual',
        ]);
        $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);

        $before = $this->WorkflowExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->_runAction("/admin/workflow_executions/{$execution->id}");

        $this->assertEquals($before, $this->WorkflowExecution->Find()->counter(), 'No debe haberse eliminado ninguna fila');
    }

    public function stepExecutionsCreateIsBlockedTest(): void {
        $this->describe('POST /admin/step_executions/add no debe crear ninguna fila (bloqueado por el guard)');

        $before = $this->StepExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['step_execution'] = ['workflow_execution_id' => 1, 'workflow_step_definition_id' => 1, 'status' => 'pending'];
        $this->_runAction('/admin/step_executions/add');

        $this->assertEquals($before, $this->StepExecution->Find()->counter(), 'No debe haberse creado ninguna fila nueva');
    }

    public function stepExecutionsUpdateIsBlockedTest(): void {
        $this->describe('PUT /admin/step_executions/{id} no debe modificar la fila (bloqueado por el guard)');

        $definition = $this->_createWorkflowDefinitionFixture();
        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Step',
            'type'                   => 'build',
            'command'                => 'echo hi',
            'step_order'             => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        $execution = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $definition->id,
            'status'                 => 'pending',
            'trigger_type'           => 'manual',
        ]);
        $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);

        $stepExecution = $this->StepExecution->Niu([
            'workflow_execution_id'       => $execution->id,
            'workflow_step_definition_id' => $step->id,
            'status'                      => 'pending',
        ]);
        $stepExecution->Save() or trigger_error((string) $stepExecution->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_POST['step_execution'] = ['status' => 'completed'];
        $this->_runAction("/admin/step_executions/{$stepExecution->id}");

        $stillOriginal = $this->StepExecution->Find((int) $stepExecution->id);
        $this->assertEquals('pending', $stillOriginal->status, 'El status no debe haber cambiado');
    }

    public function stepExecutionsDeleteIsBlockedTest(): void {
        $this->describe('DELETE /admin/step_executions/{id} no debe eliminar la fila (bloqueado por el guard)');

        $definition = $this->_createWorkflowDefinitionFixture();
        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $definition->id,
            'name'                   => 'Step',
            'type'                   => 'build',
            'command'                => 'echo hi',
            'step_order'             => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        $execution = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $definition->id,
            'status'                 => 'pending',
            'trigger_type'           => 'manual',
        ]);
        $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);

        $stepExecution = $this->StepExecution->Niu([
            'workflow_execution_id'       => $execution->id,
            'workflow_step_definition_id' => $step->id,
            'status'                      => 'pending',
        ]);
        $stepExecution->Save() or trigger_error((string) $stepExecution->_error, E_USER_ERROR);

        $before = $this->StepExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->_runAction("/admin/step_executions/{$stepExecution->id}");

        $this->assertEquals($before, $this->StepExecution->Find()->counter(), 'No debe haberse eliminado ninguna fila');
    }
}
