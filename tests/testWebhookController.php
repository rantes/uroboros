<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testWebhookController extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ]);
    }

    private function _createWorkflowDefinition(string $token): object {
        $definition = $this->WorkflowDefinition->Niu([
            'name'          => 'Webhook Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => 1,
            'status'        => 1,
            'webhook_token' => $token,
        ]);
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        return $definition;
    }

    public function validTokenDispatchesCommandAndReturns202Test(): void {
        $this->describe('GET /webhook/trigger?token=<valido> debe despachar ExecuteWorkflowCommand con trigger_type=webhook y responder 202');

        $token      = bin2hex(random_bytes(16));
        $definition = $this->_createWorkflowDefinition($token);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/webhook/trigger?token={$token}");

        $this->assertEquals(HTTP_202, (int) $result->_code, 'Token válido debe responder 202');

        $execution = $this->WorkflowExecution->Find([
            'conditions' => [['workflow_definition_id', $definition->id]],
        ]);
        $this->assertEquals(1, $execution->counter(), 'Debe haber despachado ExecuteWorkflowCommand');
        $this->assertEquals('webhook', $execution->trigger_type, 'El trigger_type debe ser webhook, no manual');
    }

    public function invalidTokenReturns401WithoutDispatchingTest(): void {
        $this->describe('GET /webhook/trigger?token=<invalido> debe responder 401 sin despachar ningún Command');

        $this->_createWorkflowDefinition(bin2hex(random_bytes(16)));

        $before = $this->WorkflowExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/webhook/trigger?token=token-invalido-que-no-existe');

        $this->assertEquals(HTTP_401, (int) $result->_code, 'Token inválido debe responder 401');
        $this->assertEquals($before, $this->WorkflowExecution->Find()->counter(), 'No debe haberse creado ninguna WorkflowExecution');
    }

    public function missingTokenReturns401WithoutDispatchingTest(): void {
        $this->describe('GET /webhook/trigger sin token debe responder 401 sin despachar ningún Command');

        $before = $this->WorkflowExecution->Find()->counter();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction('/webhook/trigger');

        $this->assertEquals(HTTP_401, (int) $result->_code, 'Sin token debe responder 401');
        $this->assertEquals($before, $this->WorkflowExecution->Find()->counter(), 'No debe haberse creado ninguna WorkflowExecution');
    }

    public function triggerWorksWithoutSessionOrCsrfTest(): void {
        $this->describe('El webhook debe funcionar sin sesión ni CSRF token (origen externo) — exceptsBeforeFilter cubre toda la superficie');

        $token      = bin2hex(random_bytes(16));
        $definition = $this->_createWorkflowDefinition($token);

        $_SESSION = [];
        unset($_SERVER['HTTP_x-sf-token']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/webhook/trigger?token={$token}");

        $this->assertEquals(HTTP_202, (int) $result->_code, 'Debe funcionar sin sesión ni CSRF, al ser origen externo');
    }
}
