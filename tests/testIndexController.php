<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testIndexController extends dumboTests {

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
            'app_users',
        ]);
        $_SERVER['HTTP_x-sf-token'] = 'token';
        $_SESSION['xsfr_token']     = 'token';

        // MainController::requireLogin() exige sesión real — mismo
        // criterio que testAdminController.php. currentUserInitials()
        // (OperationalShell_Helper.php) hace
        // AppUser->Find($_SESSION['user']) en cada página con layout
        // completo, y el Cockpit (operationalShell activo) sí renderiza
        // layout completo.
        $user = $this->AppUser->Niu([
            'firstname' => 'Test',
            'lastname'  => 'Admin',
            'email'     => 'admin-test@uroboros.local',
            'status'    => 1,
        ]);
        $user->Save();
        $_SESSION['user'] = $user->id;
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
        $this->describe('GET /index/index debe calcular Deployment Success Rate y Lead Time reales sobre WorkflowExecution');

        $this->_createWorkflowExecutionFixture('completed', 60);
        $this->_createWorkflowExecutionFixture('completed', 120);
        $this->_createWorkflowExecutionFixture('completed', 180);
        $this->_createWorkflowExecutionFixture('failed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/index');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(75.0, $result->deploymentSuccessRate, '3 completed de 4 concluidas = 75.0%');
        $this->assertEquals('2m', $result->leadTime, 'Promedio de 60/120/180 segundos = 120s = 2m');
    }

    public function indexActionHealthEmptyStateTest(): void {
        $this->describe('GET /index/index sin ninguna WorkflowExecution debe mostrar estado vacío explícito, nunca 0%');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/index');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(null, $result->deploymentSuccessRate, 'Sin ejecuciones concluidas debe ser null, nunca 0%');
        $this->assertEquals('Sin datos en este período', $result->leadTime, 'Sin ejecuciones completadas debe mostrar el mensaje de estado vacío');
    }

    public function indexActionHealthMixedCaseTest(): void {
        $this->describe('GET /index/index con solo ejecuciones failed: success rate calculable (0%) pero lead time vacío — estados vacíos independientes');

        $this->_createWorkflowExecutionFixture('failed');
        $this->_createWorkflowExecutionFixture('failed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/index');

        $this->assertEquals(0.0, $result->deploymentSuccessRate, '0 completed de 2 concluidas = 0.0%, no null — sí hay dato');
        $this->assertEquals('Sin datos en este período', $result->leadTime, 'Ninguna completed — debe mostrar el mensaje de estado vacío, no 0s');
    }

    public function indexActionHealthWindowParamTest(): void {
        $this->describe('GET /index/index?window=N resuelve la ventana desde whitelist [7,30,90], default y valores inválidos caen a 7');

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $default = $this->_runAction('/index/index');
        $this->assertEquals(7, $default->healthWindowDays, 'Sin parámetro, default 7 días');

        $thirty = $this->_runAction('/index/index?window=30');
        $this->assertEquals(30, $thirty->healthWindowDays, '?window=30 debe resolver a 30');

        $invalid = $this->_runAction('/index/index?window=999');
        $this->assertEquals(7, $invalid->healthWindowDays, 'Valor fuera de whitelist cae al default de 7');
    }

    public function healthmetricsActionRecalculatesForRequestedWindowTest(): void {
        $this->describe('GET /index/healthmetrics?window=N responde JSON con las métricas recalculadas, sin recargar la página completa');

        $this->_createWorkflowExecutionFixture('completed', 10);
        $this->_createWorkflowExecutionFixture('failed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/healthmetrics?window=30');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200');
        $this->assertEquals(50.0, $result->_response['d']['success_rate'], '1 completed de 2 concluidas = 50.0%');
        $this->assertEquals('10s', $result->_response['d']['lead_time'], 'Lead time de la única ejecución completed = 10s');
    }

    public function healthmetricsActionFormatsLeadTimeInHoursTest(): void {
        $this->describe('formatLeadTime() debe usar horas cuando el promedio pasa de 3600 segundos');

        $this->_createWorkflowExecutionFixture('completed', 7200);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/healthmetrics');

        $this->assertEquals('2h', $result->_response['d']['lead_time'], '7200 segundos = 2h');
    }

    public function healthmetricsActionInvalidWindowFallsBackToDefaultTest(): void {
        $this->describe('GET /index/healthmetrics?window=999 (fuera de whitelist) cae al default de 7 días, no un error');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/healthmetrics?window=999');

        $this->assertEquals(HTTP_200, (int) $result->_code, 'Debe responder 200 igual, sin importar el valor inválido');
        $this->assertEquals(null, $result->_response['d']['success_rate'], 'Sin datos en la ventana default — null, no error');
    }
}
