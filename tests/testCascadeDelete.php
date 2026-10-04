<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

/**
 * Borrado con dependientes ($dependents + has_many + hook de desvinculación de
 * WorkflowDefinition) y su atomicidad (Delete() abre la transacción más externa).
 */
class testCascadeDelete extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables([
            'events',
            'projects',
            'groups',
            'project_groups',
            'project_credentials',
            'project_config_files',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ]);
    }

    private function _save(object $record): object {
        $record->Save() or trigger_error((string) $record->_error, E_USER_ERROR);

        return $record;
    }

    /**
     * Proyecto con un grupo, una credencial, un archivo y un workflow con un paso,
     * una ejecución y una ejecución de paso.
     */
    private function _fullProject(string $name): array {
        $project = $this->_save($this->Project->Niu(['name' => $name, 'type' => 'backend', 'status' => 1]));
        $group   = $this->_save($this->Group->Niu(['name' => "Grupo {$name}"]));
        $pivot   = $this->_save($this->ProjectGroup->Niu(['project_id' => $project->id, 'group_id' => $group->id]));
        $cred    = $this->_save($this->ProjectCredential->Niu(['project_id' => $project->id, 'name' => 'tok', 'value' => 'v']));
        $file    = $this->_save($this->ProjectConfigFile->Niu(['project_id' => $project->id, 'filename' => '.env', 'format' => 'ini', 'content' => "A=1\n"]));
        $wf      = $this->_workflow("wf {$name}", (int) $project->id);
        $step    = $this->_save($this->WorkflowStepDefinition->Niu(['workflow_definition_id' => $wf->id, 'name' => 'paso', 'type' => 'build', 'command' => 'echo', 'step_order' => 1]));
        $exec    = $this->_save($this->WorkflowExecution->Niu(['workflow_definition_id' => $wf->id, 'status' => 'completed', 'trigger_type' => 'manual']));
        $stepEx  = $this->_save($this->StepExecution->Niu(['workflow_execution_id' => $exec->id, 'workflow_step_definition_id' => $step->id, 'status' => 'completed']));

        return compact('project', 'group', 'pivot', 'cred', 'file', 'wf', 'step', 'exec', 'stepEx');
    }

    private function _workflow(string $name, int $projectId, int $chainTo = 0): object {
        return $this->_save($this->WorkflowDefinition->Niu([
            'name' => $name, 'project_id' => $projectId, 'status' => 1,
            'webhook_token' => bin2hex(random_bytes(16)), 'workflow_definition_id' => $chainTo,
        ]));
    }

    private function _count(string $model, string $conditions = ''): int {
        return (int) $this->{$model}->Find(empty($conditions) ? [] : ['conditions' => $conditions])->counter();
    }

    public function deleteProjectCascadesToAllChildrenAndSparesOthersTest(): void {
        $this->describe('Borrar un Proyecto borra pivote, workflows (pasos, ejecuciones, ejecuciones de pasos), credenciales y archivos; no toca otros Proyectos ni el Grupo');

        $a = $this->_fullProject('A');
        $b = $this->_fullProject('B');
        $this->_save($this->Event->Niu(['aggregate_type' => 'WorkflowExecution', 'aggregate_id' => $a['exec']->id, 'event_type' => 'WorkflowStarted', 'payload' => '{}']));

        $this->assertTrue($this->Project->Find((int) $a['project']->id)->Delete(), 'Delete() debe devolver true');

        $this->assertEquals(0, $this->_count('Project', "id='{$a['project']->id}'"), 'El Proyecto se borró');
        $this->assertEquals(0, $this->_count('ProjectGroup', "project_id='{$a['project']->id}'"), 'Pivote');
        $this->assertEquals(0, $this->_count('WorkflowDefinition', "project_id='{$a['project']->id}'"), 'Workflows');
        $this->assertEquals(0, $this->_count('WorkflowStepDefinition', "workflow_definition_id='{$a['wf']->id}'"), 'Pasos');
        $this->assertEquals(0, $this->_count('WorkflowExecution', "workflow_definition_id='{$a['wf']->id}'"), 'Ejecuciones');
        $this->assertEquals(0, $this->_count('StepExecution', "workflow_execution_id='{$a['exec']->id}'"), 'Ejecuciones de pasos');
        $this->assertEquals(0, $this->_count('ProjectCredential', "project_id='{$a['project']->id}'"), 'Credenciales');
        $this->assertEquals(0, $this->_count('ProjectConfigFile', "project_id='{$a['project']->id}'"), 'Archivos');

        $this->assertEquals(1, $this->_count('Project', "id='{$b['project']->id}'"), 'El otro Proyecto sigue');
        $this->assertEquals(1, $this->_count('WorkflowStepDefinition', "workflow_definition_id='{$b['wf']->id}'"));
        $this->assertEquals(1, $this->_count('StepExecution', "workflow_execution_id='{$b['exec']->id}'"));
        $this->assertEquals(1, $this->_count('ProjectCredential', "project_id='{$b['project']->id}'"));
        $this->assertEquals(1, $this->_count('Group', "id='{$a['group']->id}'"), 'El Grupo NO se borra al borrar un Proyecto');
        $this->assertEquals(1, $this->_count('Event'), 'El Event Store (inmutable) no se toca');
    }

    public function deleteGroupRemovesOnlyItsPivotRowsTest(): void {
        $this->describe('Borrar un Grupo borra sus filas del pivote y deja intactos los Proyectos');

        $a = $this->_fullProject('A');

        $this->assertTrue($this->Group->Find((int) $a['group']->id)->Delete());

        $this->assertEquals(0, $this->_count('Group', "id='{$a['group']->id}'"));
        $this->assertEquals(0, $this->_count('ProjectGroup', "group_id='{$a['group']->id}'"), 'Sin filas huérfanas en el pivote');
        $this->assertEquals(1, $this->_count('Project', "id='{$a['project']->id}'"), 'El Proyecto sigue');
        $this->assertEquals(1, $this->_count('WorkflowDefinition', "project_id='{$a['project']->id}'"), 'Sus workflows siguen');
    }

    public function deleteWorkflowDefinitionCascadesAndUnlinksReferencesToZeroTest(): void {
        $this->describe('Borrar un Workflow borra pasos y ejecuciones, y deja en 0 (no borra) al encadenado aguas abajo y la asignación batch del pivote');

        $a          = $this->_fullProject('A');
        $downstream = $this->_workflow('aguas abajo', (int) $a['project']->id, (int) $a['wf']->id);
        $this->ProjectGroup->Update(['conditions' => "id='{$a['pivot']->id}'", 'data' => ['batch_workflow_definition_id' => (int) $a['wf']->id]]);

        $this->assertTrue($this->WorkflowDefinition->Find((int) $a['wf']->id)->Delete());

        $this->assertEquals(0, $this->_count('WorkflowDefinition', "id='{$a['wf']->id}'"));
        $this->assertEquals(0, $this->_count('WorkflowStepDefinition', "workflow_definition_id='{$a['wf']->id}'"), 'Pasos');
        $this->assertEquals(0, $this->_count('WorkflowExecution', "workflow_definition_id='{$a['wf']->id}'"), 'Ejecuciones');
        $this->assertEquals(0, $this->_count('StepExecution', "workflow_execution_id='{$a['exec']->id}'"), 'Ejecuciones de pasos');

        $survivor = $this->WorkflowDefinition->Find((int) $downstream->id);
        $this->assertEquals(1, $survivor->counter(), 'El encadenado sigue existiendo');
        $this->assertTrue($survivor->workflow_definition_id === 0, 'Quedó en 0 entero, no NULL ni el id borrado');

        $pivot = $this->ProjectGroup->Find((int) $a['pivot']->id);
        $this->assertEquals(1, $pivot->counter(), 'La fila del pivote sigue (el Proyecto está en el Grupo)');
        $this->assertTrue($pivot->batch_workflow_definition_id === 0, 'La asignación batch quedó en 0 entero');
    }

    public function unlinkOnlyTouchesReferencesToTheDeletedWorkflowTest(): void {
        $this->describe('El hook de desvinculación no toca encadenamientos ni asignaciones batch hacia OTROS workflows');

        $a      = $this->_fullProject('A');
        $other  = $this->_workflow('otro', (int) $a['project']->id);
        $chain  = $this->_workflow('encadenado al otro', (int) $a['project']->id, (int) $other->id);
        $this->ProjectGroup->Update(['conditions' => "id='{$a['pivot']->id}'", 'data' => ['batch_workflow_definition_id' => (int) $other->id]]);

        $this->assertTrue($this->WorkflowDefinition->Find((int) $a['wf']->id)->Delete());

        $this->assertEquals((int) $other->id, (int) $this->WorkflowDefinition->Find((int) $chain->id)->workflow_definition_id);
        $this->assertEquals((int) $other->id, (int) $this->ProjectGroup->Find((int) $a['pivot']->id)->batch_workflow_definition_id);
    }

    public function deleteWorkflowStepDefinitionRemovesItsStepExecutionsTest(): void {
        $this->describe('Borrar un paso borra sus ejecuciones de paso y no toca la ejecución ni el workflow');

        $a = $this->_fullProject('A');

        $this->assertTrue($this->WorkflowStepDefinition->Find((int) $a['step']->id)->Delete());

        $this->assertEquals(0, $this->_count('StepExecution', "workflow_step_definition_id='{$a['step']->id}'"));
        $this->assertEquals(1, $this->_count('WorkflowExecution', "id='{$a['exec']->id}'"));
        $this->assertEquals(1, $this->_count('WorkflowDefinition', "id='{$a['wf']->id}'"));
    }

    public function deleteWorkflowExecutionRemovesItsStepExecutionsTest(): void {
        $this->describe('Borrar una ejecución borra sus ejecuciones de paso y deja el paso definido');

        $a = $this->_fullProject('A');

        $this->assertTrue($this->WorkflowExecution->Find((int) $a['exec']->id)->Delete());

        $this->assertEquals(0, $this->_count('StepExecution', "workflow_execution_id='{$a['exec']->id}'"));
        $this->assertEquals(1, $this->_count('WorkflowStepDefinition', "id='{$a['step']->id}'"));
    }

    public function deleteCredentialAndConfigFileHaveNoCascadeTest(): void {
        $this->describe('Credencial y archivo de configuración se borran solos, sin tocar al Proyecto');

        $a = $this->_fullProject('A');

        $this->assertTrue($this->ProjectCredential->Find((int) $a['cred']->id)->Delete());
        $this->assertTrue($this->ProjectConfigFile->Find((int) $a['file']->id)->Delete());

        $this->assertEquals(0, $this->_count('ProjectCredential'));
        $this->assertEquals(0, $this->_count('ProjectConfigFile'));
        $this->assertEquals(1, $this->_count('Project', "id='{$a['project']->id}'"));
    }

    public function failureMidCascadeRollsEverythingBackTest(): void {
        $this->describe('Un fallo al borrar el último hijo revierte TODO: ni el padre ni los hijos ya borrados se pierden, y no queda transacción abierta');

        $a = $this->_fullProject('A');
        // El archivo es el ÚLTIMO hijo de Project::has_many (pivote, workflows, credenciales, archivos):
        // cuando falla, el pivote, el workflow (con pasos y ejecuciones) y la credencial ya se borraron.
        DB->exec("CREATE TRIGGER e2e_abort_file_delete BEFORE DELETE ON project_config_files WHEN OLD.id = {$a['file']->id} BEGIN SELECT RAISE(ABORT, 'fallo inyectado'); END");

        try {
            $result = $this->Project->Find((int) $a['project']->id)->Delete();
        } catch (\Throwable $e) {
            $result = false;
        }
        DB->exec('DROP TRIGGER IF EXISTS e2e_abort_file_delete');

        $this->assertFalse($result, 'Delete() debe fallar');
        $this->assertFalse(DB->inTransaction(), 'No queda ninguna transacción abierta');
        $this->assertEquals(1, $this->_count('Project', "id='{$a['project']->id}'"), 'El padre sigue');
        $this->assertEquals(1, $this->_count('ProjectGroup', "project_id='{$a['project']->id}'"), 'Pivote intacto');
        $this->assertEquals(1, $this->_count('WorkflowDefinition', "project_id='{$a['project']->id}'"), 'Workflow intacto');
        $this->assertEquals(1, $this->_count('WorkflowStepDefinition', "workflow_definition_id='{$a['wf']->id}'"), 'Paso intacto');
        $this->assertEquals(1, $this->_count('WorkflowExecution', "workflow_definition_id='{$a['wf']->id}'"), 'Ejecución intacta');
        $this->assertEquals(1, $this->_count('StepExecution', "workflow_execution_id='{$a['exec']->id}'"), 'Ejecución de paso intacta');
        $this->assertEquals(1, $this->_count('ProjectCredential', "project_id='{$a['project']->id}'"), 'Credencial intacta');
        $this->assertEquals(1, $this->_count('ProjectConfigFile', "project_id='{$a['project']->id}'"), 'Archivo intacto');
    }

    public function failureInUnlinkHookAbortsTheDeleteTest(): void {
        $this->describe('Si falla la desvinculación, el workflow NO se borra y lo ya desvinculado se revierte');

        $a          = $this->_fullProject('A');
        $downstream = $this->_workflow('aguas abajo', (int) $a['project']->id, (int) $a['wf']->id);
        $this->ProjectGroup->Update(['conditions' => "id='{$a['pivot']->id}'", 'data' => ['batch_workflow_definition_id' => (int) $a['wf']->id]]);
        // El primer UPDATE del hook (workflows) funciona; el segundo (pivote) falla.
        DB->exec("CREATE TRIGGER e2e_abort_pivot_update BEFORE UPDATE ON project_groups BEGIN SELECT RAISE(ABORT, 'fallo inyectado'); END");

        try {
            $result = $this->WorkflowDefinition->Find((int) $a['wf']->id)->Delete();
        } catch (\Throwable $e) {
            $result = false;
        }
        DB->exec('DROP TRIGGER IF EXISTS e2e_abort_pivot_update');

        $this->assertFalse($result, 'Delete() debe fallar');
        $this->assertFalse(DB->inTransaction(), 'No queda ninguna transacción abierta');
        $this->assertEquals(1, $this->_count('WorkflowDefinition', "id='{$a['wf']->id}'"), 'El workflow sigue');
        $this->assertEquals(1, $this->_count('WorkflowStepDefinition', "workflow_definition_id='{$a['wf']->id}'"), 'Sus pasos siguen');
        $this->assertEquals((int) $a['wf']->id, (int) $this->WorkflowDefinition->Find((int) $downstream->id)->workflow_definition_id, 'La desvinculación del encadenado se revirtió');
        $this->assertEquals((int) $a['wf']->id, (int) $this->ProjectGroup->Find((int) $a['pivot']->id)->batch_workflow_definition_id, 'La asignación batch sigue');
    }
}
