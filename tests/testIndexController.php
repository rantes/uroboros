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

    private function _createWorkflowDefinitionFixture(?int $projectId = null, ?int $chainedTo = null): object {
        $data = [
            'name'          => 'Fixture Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => $projectId ?? 1,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ];
        $chainedTo === null or ($data['workflow_definition_id'] = $chainedTo);

        $definition = $this->WorkflowDefinition->Niu($data);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        return $definition;
    }

    private function _createProjectFixture(string $name): object {
        $project = $this->Project->Niu([
            'name'   => $name,
            'type'   => 'backend',
            'status' => 1,
        ]);
        $project->Save() or trigger_error((string) $project->_error, E_USER_ERROR);

        return $project;
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

    // --- Dependency Graph (dependency-graph/design.md) ---

    public function dependencyGraphEmptyStateTest(): void {
        $this->describe('Sin ningún WorkflowDefinition encadenado, el widget y el detalle deben quedar en estado vacío honesto (svg null), nunca un grafo vacío');

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $index = $this->_runAction('/index/index');
        $this->assertEquals(HTTP_200, (int) $index->_code);
        $this->assertEquals(null, $index->dependencyGraphSvg, 'Sin dependencias, svg debe quedar null — la vista distingue este caso del "Próximamente" genérico');
        $this->assertEquals(null, $index->dependencyCyclePath, 'Sin dependencias no hay ciclo que reportar');
        $this->assertEquals(null, $index->dependencyCycleLabel);

        $detail = $this->_runAction('/index/dependencygraph');
        $this->assertEquals(HTTP_200, (int) $detail->_code);
        $this->assertEquals(null, $detail->dependencyGraphSvg);
    }

    public function dependencyGraphExcludesSelfReferenceWithinSameProjectTest(): void {
        $this->describe('Un Workflow encadenado a otro del mismo Proyecto no debe generar ninguna arista — no es una dependencia entre Proyectos');

        $project = $this->_createProjectFixture('Solo Project ' . bin2hex(random_bytes(4)));
        $origin  = $this->_createWorkflowDefinitionFixture((int) $project->id);
        $this->_createWorkflowDefinitionFixture((int) $project->id, (int) $origin->id);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/index/index');

        $this->assertEquals(null, $result->dependencyGraphSvg, 'Auto-referencia dentro del mismo Proyecto no debe producir grafo');
    }

    public function dependencyGraphShowsRealRelationsBetweenRealProjectsTest(): void {
        $this->describe('Con relaciones reales entre Proyectos reales, el widget y el detalle deben mostrar el grafo con los nombres reales, sin advertencia de ciclo');

        $projectA = $this->_createProjectFixture('Alpha Fixture ' . bin2hex(random_bytes(4)));
        $projectB = $this->_createProjectFixture('Beta Fixture ' . bin2hex(random_bytes(4)));
        $projectC = $this->_createProjectFixture('Gamma Fixture ' . bin2hex(random_bytes(4)));

        $definitionA = $this->_createWorkflowDefinitionFixture((int) $projectA->id);
        $definitionB = $this->_createWorkflowDefinitionFixture((int) $projectB->id, (int) $definitionA->id);
        $this->_createWorkflowDefinitionFixture((int) $projectC->id, (int) $definitionB->id);

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $index = $this->_runAction('/index/index');
        $this->assertEquals(HTTP_200, (int) $index->_code);
        $this->assertNotEmpty($index->dependencyGraphSvg, 'Debe generar el SVG compacto');
        $this->assertTrue(str_contains($index->dependencyGraphSvg, $projectA->name), 'El SVG debe incluir el nombre real de A');
        $this->assertTrue(str_contains($index->dependencyGraphSvg, $projectB->name), 'El SVG debe incluir el nombre real de B');
        $this->assertTrue(str_contains($index->dependencyGraphSvg, $projectC->name), 'El SVG debe incluir el nombre real de C');
        $this->assertEquals(null, $index->dependencyCyclePath, 'Cadena lineal A->B->C, sin ciclo');
        $this->assertEquals(null, $index->dependencyCycleLabel);

        $detail = $this->_runAction('/index/dependencygraph');
        $this->assertEquals(HTTP_200, (int) $detail->_code);
        $this->assertNotEmpty($detail->dependencyGraphSvg);
        $this->assertTrue(str_contains($detail->dependencyGraphSvg, $projectA->name));
        $this->assertTrue(str_contains($detail->dependencyGraphSvg, $projectC->name));
        $this->assertEquals(null, $detail->dependencyCyclePath);
    }

    /**
     * Tarea 4/12 — ciclo real armado a propósito (A depende de B, B
     * depende de A). Confirma que el cómputo TERMINA (esta misma
     * prueba correría indefinidamente si _computeLayout()/_detectCycle()
     * tuvieran un bug de recursión infinita) y que la advertencia es
     * clara en ambas acciones — Requisito 1.3 y 3.2. La visibilidad
     * real en el HTML renderizado (widget y detalle) se confirma con
     * DumboChromeDriver (tasks.md, Fase 3) — _rawOutput no sirve aquí
     * porque parseContent() usa include_once tanto para la vista como
     * para el layout, así que solo la PRIMERA acción con layout
     * completo que corre en todo el proceso de la suite produce
     * contenido real; cualquier llamada posterior (como esta, después
     * de los tests de health metrics) devuelve _rawOutput vacío sin
     * que eso sea un bug de esta feature.
     */
    public function dependencyGraphDetectsRealCycleWithoutHangingTest(): void {
        $this->describe('Un ciclo real entre dos Proyectos debe detectarse y el cómputo debe terminar — nunca colgarse — tanto en el widget compacto como en el detalle completo');

        $projectX = $this->_createProjectFixture('CycleX Fixture ' . bin2hex(random_bytes(4)));
        $projectY = $this->_createProjectFixture('CycleY Fixture ' . bin2hex(random_bytes(4)));

        $definitionX = $this->_createWorkflowDefinitionFixture((int) $projectX->id);
        $definitionY = $this->_createWorkflowDefinitionFixture((int) $projectY->id, (int) $definitionX->id);

        // Cierra el ciclo: X ahora también depende de Y.
        $definitionX->workflow_definition_id = $definitionY->id;
        $definitionX->Save() or trigger_error((string) $definitionX->_error, E_USER_ERROR);

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $index = $this->_runAction('/index/index');
        $this->assertEquals(HTTP_200, (int) $index->_code, 'El cómputo debe terminar y responder 200, no colgarse');
        $this->assertNotEmpty($index->dependencyCyclePath, 'Debe detectar el ciclo');
        $this->assertTrue(in_array((int) $projectX->id, $index->dependencyCyclePath, true), 'El Proyecto X debe estar señalado en el ciclo');
        $this->assertTrue(in_array((int) $projectY->id, $index->dependencyCyclePath, true), 'El Proyecto Y debe estar señalado en el ciclo');
        $this->assertNotEmpty($index->dependencyCycleLabel, 'La advertencia debe tener un texto listo para mostrar en el widget compacto');
        $this->assertTrue(str_contains($index->dependencyCycleLabel, $projectX->name), 'El texto de advertencia debe nombrar al Proyecto X');
        $this->assertTrue(str_contains($index->dependencyCycleLabel, $projectY->name), 'El texto de advertencia debe nombrar al Proyecto Y');
        $this->assertTrue(str_contains($index->dependencyGraphSvg, 'var(--warning)'), 'El SVG compacto debe resaltar el ciclo visualmente');
        $this->assertNotEmpty($index->dependencyGraphSvg, 'El grafo se muestra completo igual, nunca se bloquea por el ciclo');

        $detail = $this->_runAction('/index/dependencygraph');
        $this->assertEquals(HTTP_200, (int) $detail->_code, 'El detalle también debe responder 200, no colgarse');
        $this->assertNotEmpty($detail->dependencyCyclePath);
        $this->assertNotEmpty($detail->dependencyCycleLabel, 'La advertencia debe ser visible también en el detalle completo, no solo en el widget');
        $this->assertTrue(str_contains($detail->dependencyGraphSvg, 'var(--warning)'), 'El SVG completo debe resaltar el ciclo visualmente');
    }
}
