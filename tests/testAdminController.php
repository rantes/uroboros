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
            'projects',
            'project_config_files',
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

    /**
     * Prueba de regresión de seguridad real (vista-ejecucion,
     * design.md "Extender executeworkflowAction()") — sin esta
     * protección, cualquiera podría forzar trigger_type=webhook desde
     * el botón manual y falsificar el origen real de un disparo.
     * Confirmado también con DumboChromeDriver contra datos reales
     * (ver reporte), este test lo deja como regresión permanente.
     */
    public function executeworkflowRejectsForgedTriggerTypeTest(): void {
        $this->describe('GET /admin/executeworkflow/{id}?trigger_type=webhook NUNCA debe crear una ejecución con trigger_type=webhook — cae a manual');

        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Forgery Attempt Workflow',
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
        $result = $this->_runAction("/admin/executeworkflow/{$definition->id}?trigger_type=webhook");

        $this->assertEquals(HTTP_202, (int) $result->_code, 'Debe responder 202 igual — el request en sí es válido, solo el trigger_type se corrige');

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $this->assertEquals('manual', $execution->trigger_type, 'CRÍTICO si esto falla: el trigger_type forzado debe caer a manual, nunca aceptar webhook crudo del request');
    }

    public function executeworkflowAcceptsRetryTriggerTypeTest(): void {
        $this->describe('GET /admin/executeworkflow/{id}?trigger_type=retry debe crear la ejecución con trigger_type=retry — valor real en la lista blanca');

        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Retry Workflow',
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
        $this->_runAction("/admin/executeworkflow/{$definition->id}?trigger_type=retry");

        $execution = $this->WorkflowExecution->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $this->assertEquals('retry', $execution->trigger_type, 'retry sí debe aceptarse — es un valor real de la lista blanca');
    }

    /**
     * Fixture completo para workflowexecutiondetailAction() — un
     * Project real, un WorkflowDefinition con 3 WorkflowStepDefinition
     * reales (step_order 1/2/3), y sus StepExecution creados
     * deliberadamente en orden INVERSO de id (3, 2, 1) — el único modo
     * de probar de verdad que $this->steps ordena por el step_order
     * real (join) y no por una coincidencia de id ASC == step_order
     * (la Fase 1 de vista-ejecucion confirmó que en el flujo normal
     * siempre coinciden, precisamente porque nunca se insertan así —
     * este fixture simula el caso fuera de flujo normal que el propio
     * código advierte que podría, en teoría, ocurrir vía el CRUD
     * genérico de step_executions).
     */
    private function _createOutOfOrderExecutionFixture(): object {
        $project = $this->Project->Niu(['name' => 'Detail Fixture ' . bin2hex(random_bytes(4)), 'type' => 'backend']);
        $project->Save() or trigger_error((string) $project->_error, E_USER_ERROR);

        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Detail Fixture Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => $project->id,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        $steps = [];
        foreach ([1, 2, 3] as $order) {
            $step = $this->WorkflowStepDefinition->Niu([
                'workflow_definition_id' => $definition->id,
                'name'                   => "Step {$order}",
                'type'                   => 'build',
                'command'                => 'echo hi',
                'step_order'             => $order,
            ]);
            $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);
            $steps[$order] = $step;
        }

        $execution = $this->WorkflowExecution->Niu([
            'workflow_definition_id' => $definition->id,
            'status'                 => 'failed',
            'trigger_type'           => 'manual',
        ]);
        $execution->Save() or trigger_error((string) $execution->_error, E_USER_ERROR);

        // Insertados a propósito en orden 3, 2, 1 — id ASC daría
        // exactamente el orden equivocado si el código confiara en id
        // en vez del step_order real.
        foreach ([3, 2, 1] as $order) {
            $stepExecution = $this->StepExecution->Niu([
                'workflow_execution_id'       => $execution->id,
                'workflow_step_definition_id' => $steps[$order]->id,
                'status'                      => $order === 3 ? 'failed' : 'completed',
            ]);
            $stepExecution->Save() or trigger_error((string) $stepExecution->_error, E_USER_ERROR);
        }

        return $execution;
    }

    public function workflowexecutiondetailOrdersByRealStepOrderTest(): void {
        $this->describe('workflowexecutiondetail debe ordenar por step_order real, no por id de StepExecution');

        $execution = $this->_createOutOfOrderExecutionFixture();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/workflowexecutiondetail/{$execution->id}");

        $names = [];
        foreach ($result->steps as $step):
            $names[] = $step->workflow_step_definition()->name;
        endforeach;

        $this->assertEquals(['Step 1', 'Step 2', 'Step 3'], $names, 'Debe respetar step_order (1,2,3) aunque los StepExecution se hayan insertado en orden inverso de id');
        $this->assertEquals('Step 3', $result->failedStep->workflow_step_definition()->name, 'failedStep debe ser el paso realmente marcado failed (Step 3)');
    }

    public function workflowexecutiondetailShowsRollbackWhenAvailableTest(): void {
        $this->describe('workflowexecutiondetail debe exponer rollbackWorkflow cuando el proyecto tiene un workflow con paso type=rollback');

        $execution = $this->_createOutOfOrderExecutionFixture();
        $projectId = (int) $execution->workflow_definition()->project_id;

        $rollbackDefinition = $this->WorkflowDefinition->Niu([
            'name'          => 'Rollback Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => $projectId,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $rollbackDefinition->Save() or trigger_error((string) $rollbackDefinition->_error, E_USER_ERROR);

        $rollbackStep = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $rollbackDefinition->id,
            'name'                   => 'Revert',
            'type'                   => 'rollback',
            'command'                => 'echo revert',
            'step_order'             => 1,
        ]);
        $rollbackStep->Save() or trigger_error((string) $rollbackStep->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/workflowexecutiondetail/{$execution->id}");

        $this->assertNotEmpty($result->rollbackWorkflow, 'rollbackWorkflow debe resolverse — el proyecto sí tiene un workflow con paso type=rollback');
        $this->assertEquals($rollbackDefinition->id, (int) $result->rollbackWorkflow->id, 'Debe ser exactamente el WorkflowDefinition con el paso rollback');
    }

    public function workflowexecutiondetailHidesRollbackWhenNoneExistsTest(): void {
        $this->describe('workflowexecutiondetail NO debe exponer rollbackWorkflow cuando el proyecto no tiene ningún workflow con paso type=rollback');

        $execution = $this->_createOutOfOrderExecutionFixture();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/workflowexecutiondetail/{$execution->id}");

        $this->assertTrue(empty($result->rollbackWorkflow), 'rollbackWorkflow debe quedar null — nunca inventar un botón Rollback sin un workflow real');
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

    private function _createProjectFixture(array $overrides = []): object {
        $project = $this->Project->Niu(array_merge([
            'name' => 'Fixture Project ' . bin2hex(random_bytes(4)),
            'type' => 'backend',
        ], $overrides));
        $project->Save() or trigger_error((string) $project->_error, E_USER_ERROR);

        return $project;
    }

    /**
     * Hallazgo real de producción — .claude/specs/gestion-proyectos/
     * tasks.md, "Hallazgo real de producción". saveprojectAction()
     * no escribe $this->_code (usa una variable local $code), mismo
     * hueco preexistente ya documentado arriba para landingAction()
     * — se assertea el efecto real en BD/disco, no $result->_code.
     */
    public function saveprojectRejectsRelativeWorkingDirectoryTest(): void {
        $this->describe('POST /admin/saveproject con working_directory relativo no debe crear el Proyecto');

        $before = $this->Project->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['project'] = [
            'name'              => 'Rejected Relative ' . bin2hex(random_bytes(4)),
            'type'              => 'backend',
            'status'            => 1,
            'working_directory' => 'quedicende',
        ];
        $this->_runAction('/admin/saveproject');

        $this->assertEquals($before, $this->Project->Find()->counter(), 'No debe haberse creado ninguna fila con ruta relativa');
    }

    public function saveprojectCreatesAbsoluteWorkingDirectoryTest(): void {
        $this->describe('POST /admin/saveproject con working_directory absoluto inexistente debe crearlo y guardar el Proyecto');

        $workingDirectory = sys_get_temp_dir() . '/uroboros-wd-test-' . bin2hex(random_bytes(4));
        $this->assertFalse(is_dir($workingDirectory), 'Precondición: el directorio no debe existir todavía');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['project'] = [
            'name'              => 'Accepted Absolute ' . bin2hex(random_bytes(4)),
            'type'              => 'backend',
            'status'            => 1,
            'working_directory' => $workingDirectory,
        ];
        $this->_runAction('/admin/saveproject');

        $this->assertTrue(is_dir($workingDirectory), 'El directorio debe haberse creado realmente en disco');

        $created = $this->Project->Find(['conditions' => [['working_directory', $workingDirectory]]]);
        $this->assertEquals(1, $created->counter(), 'El Proyecto debe haberse guardado');

        rmdir($workingDirectory);
    }

    public function saveprojectRejectsNonWritableWorkingDirectoryTest(): void {
        $this->describe('POST /admin/saveproject con working_directory absoluto sin permisos de escritura no debe crear el Proyecto');

        $workingDirectory = sys_get_temp_dir() . '/uroboros-wd-readonly-' . bin2hex(random_bytes(4));
        mkdir($workingDirectory, 0755, true);
        chmod($workingDirectory, 0555);
        $before = $this->Project->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['project'] = [
            'name'              => 'Rejected Readonly ' . bin2hex(random_bytes(4)),
            'type'              => 'backend',
            'status'            => 1,
            'working_directory' => $workingDirectory,
        ];
        $this->_runAction('/admin/saveproject');

        $this->assertEquals($before, $this->Project->Find()->counter(), 'No debe haberse creado ninguna fila apuntando a un directorio sin permisos de escritura');

        chmod($workingDirectory, 0755);
        rmdir($workingDirectory);
    }

    public function projectConfigFilesListFiltersByProjectIdTest(): void {
        $this->describe('GET /admin/project_config_files?project_id=X debe listar solo los archivos de ese proyecto, nunca los de otro');

        $projectA = $this->_createProjectFixture();
        $projectB = $this->_createProjectFixture();

        $fileA = $this->ProjectConfigFile->Niu([
            'project_id' => $projectA->id,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=A',
        ]);
        $fileA->Save() or trigger_error((string) $fileA->_error, E_USER_ERROR);

        $fileB = $this->ProjectConfigFile->Niu([
            'project_id' => $projectB->id,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=B',
        ]);
        $fileB->Save() or trigger_error((string) $fileB->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/project_config_files?project_id={$projectA->id}");

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(1, $result->data->counter(), 'Debe listar solo el archivo del proyecto A, nunca el de B');
    }

    public function projectConfigFileCanBeCreatedTest(): void {
        $this->describe('POST /admin/project_config_files/add debe crear un archivo — no es de solo lectura');

        $project = $this->_createProjectFixture();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['project_config_file'] = [
            'project_id' => $project->id,
            'filename'   => 'config/app.json',
            'format'     => 'json',
            'content'    => '{"key":"value"}',
        ];
        $this->_runAction('/admin/project_config_files/add');

        $created = $this->ProjectConfigFile->Find(['conditions' => [['project_id', $project->id]]]);
        $this->assertEquals(1, $created->counter(), 'Debe haberse creado el archivo');
    }

    public function projectConfigFileCanBeDeletedTest(): void {
        $this->describe('DELETE /admin/project_config_files/{id} debe eliminar el archivo — no es de solo lectura');

        $project = $this->_createProjectFixture();
        $file = $this->ProjectConfigFile->Niu([
            'project_id' => $project->id,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'KEY=1',
        ]);
        $file->Save() or trigger_error((string) $file->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->_runAction("/admin/project_config_files/{$file->id}");

        $this->assertEquals(0, $this->ProjectConfigFile->Find()->counter(), 'Debe haberse eliminado el archivo');
    }

    public function showconfigfilecontentReturnsDecryptedContentTest(): void {
        $this->describe('GET /admin/showconfigfilecontent/{id} debe devolver el contenido descifrado');

        $project = $this->_createProjectFixture();
        $file = $this->ProjectConfigFile->Niu([
            'project_id' => $project->id,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => 'SECRET=abc123',
            'is_secret'  => 1,
        ]);
        $file->Save() or trigger_error((string) $file->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/showconfigfilecontent/{$file->id}");

        $this->assertEquals(HTTP_200, (int) $result->_code);
        $this->assertEquals('SECRET=abc123', $result->_response['d']['content']);
    }

    public function syncconfigfilesWritesDecryptedFilesToDiskTest(): void {
        $this->describe('GET /admin/syncconfigfiles/{project_id} debe escribir los archivos descifrados en working_directory, incluyendo rutas anidadas');

        $workingDirectory = sys_get_temp_dir() . '/uroboros_test_sync_' . bin2hex(random_bytes(4));
        $project = $this->_createProjectFixture(['working_directory' => $workingDirectory]);

        $flat = $this->ProjectConfigFile->Niu([
            'project_id' => $project->id,
            'filename'   => '.env',
            'format'     => 'ini',
            'content'    => "KEY=value",
        ]);
        $flat->Save() or trigger_error((string) $flat->_error, E_USER_ERROR);

        $nested = $this->ProjectConfigFile->Niu([
            'project_id' => $project->id,
            'filename'   => 'config/app.json',
            'format'     => 'json',
            'content'    => '{"host":"localhost"}',
        ]);
        $nested->Save() or trigger_error((string) $nested->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/syncconfigfiles/{$project->id}");

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertTrue(is_file("{$workingDirectory}/.env"), 'Debe haber escrito .env en working_directory');
        $this->assertEquals('KEY=value', file_get_contents("{$workingDirectory}/.env"));
        $this->assertTrue(is_file("{$workingDirectory}/config/app.json"), 'Debe haber creado el subdirectorio config/ y escrito el archivo');
        $this->assertEquals('{"host":"localhost"}', file_get_contents("{$workingDirectory}/config/app.json"));

        exec('rm -rf ' . escapeshellarg($workingDirectory));
    }

    public function syncconfigfilesFailsCleanlyWithoutWorkingDirectoryTest(): void {
        $this->describe('GET /admin/syncconfigfiles/{project_id} sin working_directory debe fallar limpio con 422');

        $project = $this->_createProjectFixture();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/syncconfigfiles/{$project->id}");

        $this->assertEquals(HTTP_422, (int) $result->_code);
    }

    /**
     * Tarea 23 — el caso de prueba de seguridad más importante del
     * spec. Path traversal real contra el filesystem real (no solo
     * revisión de código): un filename que intenta escapar de
     * working_directory nunca debe escribir nada fuera de él.
     */
    public function syncconfigfilesRejectsPathTraversalTest(): void {
        $this->describe('GET /admin/syncconfigfiles/{project_id} debe rechazar filenames que intentan escapar de working_directory');

        $workingDirectory = sys_get_temp_dir() . '/uroboros_test_traversal_' . bin2hex(random_bytes(4));
        $project = $this->_createProjectFixture(['working_directory' => $workingDirectory]);

        $canary = sys_get_temp_dir() . '/uroboros_test_traversal_canary_' . bin2hex(random_bytes(4)) . '.txt';

        $traversalRelative = $this->ProjectConfigFile->Niu([
            'project_id' => $project->id,
            'filename'   => '../' . basename($canary),
            'format'     => 'ini',
            'content'    => 'PWNED=1',
        ]);
        $traversalRelative->Save() or trigger_error((string) $traversalRelative->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/syncconfigfiles/{$project->id}");

        $this->assertEquals(HTTP_500, (int) $result->_code, 'Debe rechazar el filename con ../ y responder error');
        $this->assertFalse(is_file($canary), 'Nunca debe haber escrito fuera de working_directory (../)');
        $this->assertFalse(is_dir($workingDirectory) && is_file("{$workingDirectory}/../" . basename($canary)), 'Doble verificación de que no escapó');

        $this->_truncateTables(['project_config_files']);

        $traversalAbsolute = $this->ProjectConfigFile->Niu([
            'project_id' => $project->id,
            'filename'   => '/etc/passwd',
            'format'     => 'ini',
            'content'    => 'PWNED=1',
        ]);
        $traversalAbsolute->Save() or trigger_error((string) $traversalAbsolute->_error, E_USER_ERROR);

        $result = $this->_runAction("/admin/syncconfigfiles/{$project->id}");
        $this->assertEquals(HTTP_500, (int) $result->_code, 'Debe rechazar una ruta absoluta y responder error');

        exec('rm -rf ' . escapeshellarg($workingDirectory));
        is_file($canary) and unlink($canary);
    }
}
