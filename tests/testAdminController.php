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

    /**
     * created_at es forzado a time() por el framework en INSERT y
     * excluido del array de datos en UPDATE (confirmado leyendo
     * ActiveRecord::Save() en dumbophp.php — no hay forma soportada
     * de sembrar un created_at histórico vía Niu()/Save()). Por eso
     * este fixture no intenta simular "fuera de la ventana" — lee el
     * created_at real que el framework acaba de asignar y fija
     * completed_at ese mismo momento + el offset pedido, vía un
     * segundo Save() (UPDATE sí permite completed_at, solo excluye
     * created_at).
     *
     * completed_at se fija para 'completed' Y 'failed' — mismo
     * comportamiento real de CompleteWorkflowCommandHandler y
     * FailWorkflowCommandHandler (ambos hacen
     * `$execution->completed_at = time();`, confirmado leyendo el
     * código real). El filtro de ventana de HealthMetrics_Helper usa
     * completed_at, no created_at — un fixture 'failed' sin
     * completed_at quedaría fuera de cualquier ventana y rompería
     * silenciosamente el caso mixto de abajo.
     */
    private function _createWorkflowExecutionFixture(string $status, int $leadTimeOffsetSeconds = 0): object {
        $definition = $this->_createWorkflowDefinitionFixture();
        $execution  = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $definition->id,
            'status'                 => $status,
            'trigger_type'           => 'manual',
        ]);
        $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);

        if (in_array($status, ['completed', 'failed'], true)):
            $execution->completed_at = (int) $execution->created_at + $leadTimeOffsetSeconds;
            $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);
        endif;

        return $execution;
    }

    public function indexActionHealthMetricsWithRealDataTest(): void {
        $this->describe('GET /admin/index debe calcular Deployment Success Rate y Lead Time reales sobre WorkflowExecution');

        $this->_createWorkflowExecutionFixture('completed', 60);
        $this->_createWorkflowExecutionFixture('completed', 120);
        $this->_createWorkflowExecutionFixture('completed', 180);
        $this->_createWorkflowExecutionFixture('failed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/index');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(75.0, $result->deploymentSuccessRate, '3 completed de 4 concluidas = 75.0%');
        $this->assertEquals('2m', $result->leadTime, 'Promedio de 60/120/180 segundos = 120s = 2m');
    }

    public function indexActionHealthEmptyStateTest(): void {
        $this->describe('GET /admin/index sin ninguna WorkflowExecution debe mostrar estado vacío explícito, nunca 0%');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/index');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(null, $result->deploymentSuccessRate, 'Sin ejecuciones concluidas debe ser null, nunca 0%');
        $this->assertEquals('Sin datos en este período', $result->leadTime, 'Sin ejecuciones completadas debe mostrar el mensaje de estado vacío');
    }

    public function indexActionHealthMixedCaseTest(): void {
        $this->describe('GET /admin/index con solo ejecuciones failed: success rate calculable (0%) pero lead time vacío — estados vacíos independientes');

        $this->_createWorkflowExecutionFixture('failed');
        $this->_createWorkflowExecutionFixture('failed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/index');

        $this->assertEquals(0.0, $result->deploymentSuccessRate, '0 completed de 2 concluidas = 0.0%, no null — sí hay dato');
        $this->assertEquals('Sin datos en este período', $result->leadTime, 'Ninguna completed — debe mostrar el mensaje de estado vacío, no 0s');
    }

    public function indexActionHealthWindowParamTest(): void {
        $this->describe('GET /admin/index?window=N resuelve la ventana desde whitelist [7,30,90], default y valores inválidos caen a 7');

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $default = $this->_runAction('/admin/index');
        $this->assertEquals(7, $default->healthWindowDays, 'Sin parámetro, default 7 días');

        $thirty = $this->_runAction('/admin/index?window=30');
        $this->assertEquals(30, $thirty->healthWindowDays, '?window=30 debe resolver a 30');

        $invalid = $this->_runAction('/admin/index?window=999');
        $this->assertEquals(7, $invalid->healthWindowDays, 'Valor fuera de whitelist cae al default de 7');
    }

    public function healthmetricsActionRecalculatesForRequestedWindowTest(): void {
        $this->describe('GET /admin/healthmetrics?window=N responde JSON con las métricas recalculadas, sin recargar la página completa');

        $this->_createWorkflowExecutionFixture('completed', 10);
        $this->_createWorkflowExecutionFixture('failed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/healthmetrics?window=30');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(50.0, $result->_response['d']['success_rate'], '1 completed de 2 concluidas = 50.0%');
        $this->assertEquals('10s', $result->_response['d']['lead_time'], 'Lead time de la única ejecución completed = 10s');
    }

    public function healthmetricsActionFormatsLeadTimeInHoursTest(): void {
        $this->describe('formatLeadTime() debe usar horas cuando el promedio pasa de 3600 segundos');

        $this->_createWorkflowExecutionFixture('completed', 7200);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/healthmetrics');

        $this->assertEquals('2h', $result->_response['d']['lead_time'], '7200 segundos = 2h');
    }

    public function healthmetricsActionInvalidWindowFallsBackToDefaultTest(): void {
        $this->describe('GET /admin/healthmetrics?window=999 (fuera de whitelist) cae al default de 7 días, no un error');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/admin/healthmetrics?window=999');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200 igual, sin importar el valor inválido');
        $this->assertEquals(null, $result->_response['d']['success_rate'], 'Sin datos en la ventana default — null, no error');
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
