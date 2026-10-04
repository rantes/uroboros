<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testExecuteGroup extends dumboTests {

    private int $_groupId = 0;

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'oem_metrics',
            'projects',
            'groups',
            'project_groups',
            'project_config_files',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
            'app_users',
        ]);
        $_SERVER['HTTP_x-sf-token'] = 'token';
        $_SESSION['xsfr_token']     = 'token';

        $user = $this->AppUser->Niu([
            'firstname' => 'Test',
            'lastname'  => 'Admin',
            'email'     => 'batch-test@uroboros.local',
            'status'    => 1,
        ]);
        $user->Save();
        $_SESSION['user'] = $user->id;

        $group = $this->Group->Niu(['name' => 'Clientes']);
        $group->Save() or trigger_error((string) $group->_error, E_USER_ERROR);
        $this->_groupId = (int) $group->id;
    }

    private function _project(string $name, ?int $batchWorkflowId = null, bool $linked = true): object {
        $project = $this->Project->Niu(['name' => $name, 'type' => 'backend']);
        $project->Save() or trigger_error((string) $project->_error, E_USER_ERROR);

        $linked and $this->_link((int) $project->id, $batchWorkflowId);

        return $project;
    }

    private function _link(int $projectId, ?int $batchWorkflowId): void {
        $pivot = $this->ProjectGroup->Niu([
            'project_id'                   => $projectId,
            'group_id'                     => $this->_groupId,
            'batch_workflow_definition_id' => $batchWorkflowId,
        ]);
        $pivot->Save() or trigger_error((string) $pivot->_error, E_USER_ERROR);
    }

    private function _workflow(string $name, int $projectId): object {
        $workflow = $this->WorkflowDefinition->Niu([
            'name'          => $name,
            'project_id'    => $projectId,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ]);
        $workflow->Save() or trigger_error((string) $workflow->_error, E_USER_ERROR);

        $step = $this->WorkflowStepDefinition->Niu([
            'workflow_definition_id' => $workflow->id, 'name' => 'Paso', 'type' => 'deploy',
            'command' => 'echo hi', 'step_order' => 1,
        ]);
        $step->Save() or trigger_error((string) $step->_error, E_USER_ERROR);

        return $workflow;
    }

    private function _executeGroup(int $groupId): object {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        return $this->_runAction("/admin/executegroup/{$groupId}");
    }

    public function executesAssignedMembersAndReportsOmittedTest(): void {
        $this->describe('3 Proyectos (2 con workflow, 1 sin): dispara exactamente 2 con trigger_type=batch y señala el omitido');

        $alfa  = $this->_project('Alfa');
        $beta  = $this->_project('Beta');
        $gamma = $this->_project('Gamma');
        $wfA   = $this->_workflow('Deploy Alfa', (int) $alfa->id);
        $wfB   = $this->_workflow('Deploy Beta', (int) $beta->id);
        $this->_workflow('Otro Gamma', (int) $gamma->id); // existe, pero NO asignado
        $this->ProjectGroup->Find(['conditions' => [['project_id', $alfa->id]]])->Update(['conditions' => "project_id='{$alfa->id}'", 'data' => ['batch_workflow_definition_id' => $wfA->id]]);
        $this->ProjectGroup->Find(['conditions' => [['project_id', $beta->id]]])->Update(['conditions' => "project_id='{$beta->id}'", 'data' => ['batch_workflow_definition_id' => $wfB->id]]);

        $result     = $this->_executeGroup($this->_groupId);
        $executions = $this->WorkflowExecution->Find();

        $this->assertEquals(HTTP_202, (int) $result->_code);
        $this->assertEquals(2, $executions->counter(), 'Exactamente 2 ejecuciones');
        foreach ($executions as $execution):
            $this->assertEquals('batch', $execution->trigger_type);
        endforeach;
        $this->assertEquals(
            [(int) $wfA->id, (int) $wfB->id],
            array_map('intval', array_column($result->_response['d']['triggered'], 'workflow_definition_id'))
        );
        $this->assertEquals(1, count($result->_response['d']['skipped']));
        $this->assertEquals('Gamma', $result->_response['d']['skipped'][0]['project']);
        $this->assertTrue(str_contains($result->_response['d']['skipped'][0]['reason'], 'Sin workflow asignado'));
        $this->assertTrue(str_contains($result->_response['message'], 'Gamma'), 'El omitido queda en el mensaje');
        $this->assertTrue(str_contains($result->_response['message'], 'Alfa'), 'El disparado queda en el mensaje');
    }

    public function staleAssignedWorkflowIsSkippedTest(): void {
        $this->describe('Un workflow asignado que ya no existe (o es de otro Proyecto) se omite, no se dispara');

        $alfa    = $this->_project('Alfa', 9999); // id inexistente
        $beta    = $this->_project('Beta');
        $foreign = $this->_workflow('De Alfa', (int) $alfa->id);
        $this->ProjectGroup->Find(['conditions' => [['project_id', $beta->id]]])->Update(['conditions' => "project_id='{$beta->id}'", 'data' => ['batch_workflow_definition_id' => $foreign->id]]);

        $result = $this->_executeGroup($this->_groupId);

        $this->assertEquals(0, $this->WorkflowExecution->Find()->counter(), 'No se dispara nada');
        $this->assertEquals(2, count($result->_response['d']['skipped']));
        $this->assertTrue(str_contains($result->_response['d']['skipped'][0]['reason'], 'ya no existe'));
    }

    public function emptyGroupAndUnknownGroupTest(): void {
        $this->describe('Grupo sin miembros → 202 con 0/0; grupo inexistente → 404');

        $empty = $this->_executeGroup($this->_groupId);
        $this->assertEquals(HTTP_202, (int) $empty->_code);
        $this->assertEquals(0, count($empty->_response['d']['triggered']) + count($empty->_response['d']['skipped']));

        $missing = $this->_executeGroup(9999);
        $this->assertEquals(HTTP_404, (int) $missing->_code);
    }

    public function dispatchFailureDoesNotStopTheRestTest(): void {
        $this->describe('Si el despacho falla en un miembro, se reporta como omitido y la acción no revienta');

        $alfa = $this->_project('Alfa');
        $wf   = $this->_workflow('Deploy Alfa', (int) $alfa->id);
        $this->ProjectGroup->Find(['conditions' => [['project_id', $alfa->id]]])->Update(['conditions' => "project_id='{$alfa->id}'", 'data' => ['batch_workflow_definition_id' => $wf->id]]);
        $this->_dropTables(['workflow_executions']);

        $result = $this->_executeGroup($this->_groupId);

        $this->assertEquals(HTTP_202, (int) $result->_code);
        $this->assertEquals(0, count($result->_response['d']['triggered']));
        $this->assertTrue(str_contains($result->_response['d']['skipped'][0]['reason'], 'Error al disparar'));
    }

    private function _saveProject(array $project): object {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['project' => $project];

        return $this->_runAction('/admin/saveproject');
    }

    public function batchAssignmentSurvivesProjectEditTest(): void {
        $this->describe('Editar el Proyecto sin tocar el workflow batch NO lo pierde (el pivote se recrea)');

        $alfa = $this->_project('Alfa');
        $wf   = $this->_workflow('Deploy Alfa', (int) $alfa->id);

        $first = $this->_saveProject([
            'id' => $alfa->id, 'name' => 'Alfa', 'type' => 'backend',
            'groups' => [$this->_groupId], 'batch_workflows' => [$this->_groupId => $wf->id],
        ]);
        $this->assertEquals(HTTP_200, (int) $first->_code);
        $this->assertEquals((int) $wf->id, (int) $this->ProjectGroup->Find([':first', 'conditions' => [['project_id', $alfa->id]]])->batch_workflow_definition_id);

        // Segunda edición — el formulario no envía batch_workflows
        $second = $this->_saveProject(['id' => $alfa->id, 'name' => 'Alfa renombrado', 'type' => 'backend', 'groups' => [$this->_groupId]]);
        $this->assertEquals(HTTP_200, (int) $second->_code);
        $this->assertEquals((int) $wf->id, (int) $this->ProjectGroup->Find([':first', 'conditions' => [['project_id', $alfa->id]]])->batch_workflow_definition_id, 'La asignación persiste');
        $this->assertEquals(1, $this->ProjectGroup->Find(['conditions' => [['project_id', $alfa->id]]])->counter(), 'Sin filas duplicadas');
    }

    public function batchAssignmentCanBeClearedTest(): void {
        $this->describe('Enviar "Sin asignar" limpia la asignación');

        $alfa = $this->_project('Alfa');
        $wf   = $this->_workflow('Deploy Alfa', (int) $alfa->id);
        $this->_saveProject(['id' => $alfa->id, 'name' => 'Alfa', 'type' => 'backend', 'groups' => [$this->_groupId], 'batch_workflows' => [$this->_groupId => $wf->id]]);

        $this->_saveProject(['id' => $alfa->id, 'name' => 'Alfa', 'type' => 'backend', 'groups' => [$this->_groupId], 'batch_workflows' => [$this->_groupId => '']]);

        $this->assertTrue(empty($this->ProjectGroup->Find([':first', 'conditions' => [['project_id', $alfa->id]]])->batch_workflow_definition_id));
    }

    public function rejectsBatchWorkflowOfAnotherProjectTest(): void {
        $this->describe('Un workflow batch de OTRO Proyecto se rechaza');

        $alfa    = $this->_project('Alfa');
        $beta    = $this->_project('Beta', null, false);
        $foreign = $this->_workflow('De Beta', (int) $beta->id);

        $result = $this->_saveProject([
            'id' => $alfa->id, 'name' => 'Alfa intento', 'type' => 'backend',
            'groups' => [$this->_groupId], 'batch_workflows' => [$this->_groupId => $foreign->id],
        ]);

        // saveprojectAction() usa $code local + setResponseCode(), así que
        // $result->_code no refleja el error (mismo caso ya documentado en
        // testAdminController) — se verifica mensaje y estado real.
        $this->assertTrue(str_contains((string) $result->_response['message'], 'propio proyecto'), 'Mensaje de rechazo');
        $this->assertEquals(1, $this->ProjectGroup->Find(['conditions' => [['project_id', $alfa->id]]])->counter(), 'El pivote existente no se toca');
        $this->assertEquals('Alfa', $this->Project->Find((int) $alfa->id)->name, 'Tampoco se guardó el Proyecto');
        $this->assertTrue(empty($this->ProjectGroup->Find([':first', 'conditions' => [['project_id', $alfa->id]]])->batch_workflow_definition_id), 'Ni quedó el workflow ajeno');
    }

    public function editFormListsOnlyOwnWorkflowsPerAssignedGroupTest(): void {
        $this->describe('El formulario de edición ofrece solo workflows propios y la asignación actual');

        $alfa = $this->_project('Alfa');
        $beta = $this->_project('Beta', null, false);
        $wf   = $this->_workflow('Deploy Alfa', (int) $alfa->id);
        $this->_workflow('De Beta', (int) $beta->id);
        $this->ProjectGroup->Find(['conditions' => [['project_id', $alfa->id]]])->Update(['conditions' => "project_id='{$alfa->id}'", 'data' => ['batch_workflow_definition_id' => $wf->id]]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = $this->_runAction("/admin/projects/edit/{$alfa->id}");

        $this->assertEquals(1, $result->projectWorkflows->counter(), 'Solo el workflow del propio Proyecto');
        $this->assertEquals((int) $wf->id, $result->batchByGroup[$this->_groupId]);
        $this->assertTrue(str_contains($result->_rawOutput, "project[batch_workflows][{$this->_groupId}]"));
        $this->assertFalse(str_contains($result->_rawOutput, 'De Beta'));
    }
}
