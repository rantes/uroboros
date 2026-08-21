<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testWorkflowDefinitionModel extends dumboTests {

    public function beforeEach(): void {
        $this->_migrateTables(['projects', 'workflow_definitions']);
    }

    private function _createProjectFixture(): object {
        $project = $this->Project->Niu([
            'name' => 'Test Project ' . bin2hex(random_bytes(4)),
            'type' => 'backend',
        ]);
        $project->Save() or trigger_error((string) $project->_error, E_USER_ERROR);

        return $project;
    }

    private function _createDefinitionFixture(int $projectId, array $overrides = []): object {
        $definition = $this->WorkflowDefinition->Niu(array_merge([
            'name'          => 'Workflow ' . bin2hex(random_bytes(4)),
            'project_id'    => $projectId,
            'status'        => 1,
            'webhook_token' => bin2hex(random_bytes(16)),
        ], $overrides));
        $definition->Save() or trigger_error((string) $definition->_error, E_USER_ERROR);

        return $definition;
    }

    public function modelExistTest(): void {
        $this->describe('Should exist the Model');

        $obj = $this->WorkflowDefinition->Niu();
        $this->assertFalse(empty($obj), 'Assert there is a model instance');
        $this->assertTrue(
            is_a($obj, 'DumboPHP\ActiveRecord'),
            'Assert the instance is an ActiveRecord'
        );
    }

    public function saveWithoutCascadeIsUnaffectedTest(): void {
        $this->describe('Guardar sin workflow_definition_id no debe fallar — comportamiento actual sin cambios');

        $project    = $this->_createProjectFixture();
        $definition = $this->_createDefinitionFixture((int) $project->id);

        $this->assertNotEmpty($definition->id);
        $this->assertTrue(empty($definition->workflow_definition_id), 'Debe quedar null/vacío por defecto');
    }

    public function saveWithCascadeToAnotherWorkflowIsAllowedTest(): void {
        $this->describe('Encadenar a otro Workflow distinto debe guardarse sin error');

        $project = $this->_createProjectFixture();
        $origin  = $this->_createDefinitionFixture((int) $project->id);
        $chained = $this->_createDefinitionFixture((int) $project->id, [
            'workflow_definition_id' => $origin->id,
        ]);

        $this->assertNotEmpty($chained->id);
        $this->assertEquals((int) $origin->id, (int) $chained->workflow_definition_id);
    }

    public function cannotCascadeAfterItselfOnCreateTest(): void {
        $this->describe('Un Workflow nuevo no puede apuntar a un id que todavía no tiene — no aplica en creación, solo en edición');

        // En creación, $this->id todavía no existe (es 0/null antes de
        // Save()), así que un valor arbitrario de
        // workflow_definition_id nunca puede coincidir
        // con el id propio todavía inexistente — la validación real se
        // prueba en edición (test siguiente).
        $project    = $this->_createProjectFixture();
        $definition = $this->_createDefinitionFixture((int) $project->id);

        $this->assertNotEmpty($definition->id, 'Debe crearse normalmente');
    }

    public function cannotCascadeAfterItselfOnUpdateTest(): void {
        $this->describe('Editar un Workflow para que se encadene a sí mismo debe rechazarse (Paso 9, ciclo trivial de un nodo)');

        $project    = $this->_createProjectFixture();
        $definition = $this->_createDefinitionFixture((int) $project->id);

        $definition->workflow_definition_id = $definition->id;
        $result = $definition->Save();

        $this->assertFalse($result, 'Save() debe retornar false');
        $this->assertTrue(
            in_array('workflow_definition_id', $definition->_error->errFields()),
            'El campo workflow_definition_id debe estar marcado con error'
        );

        $reloaded = $this->WorkflowDefinition->Find((int) $definition->id);
        $this->assertTrue(empty($reloaded->workflow_definition_id), 'No debe haber persistido la auto-referencia');
    }
}
