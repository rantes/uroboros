<?php
namespace Tests;

use DumboPHP\lib\Timothy\dumboTests;
use Migrations\CreateProjects;
use Migrations\CreateGroups;
use Migrations\CreateProjectGroups;

class testTables extends dumboTests {

    /**
     * Force to connect to real DB in order to check the integration
     *
     * @return void
     */
    public function beforeEach(): void {
        /** before each test the table should be reset */
        $this->_migrateTables([
            'app_users',
            'events',
            'oem_metrics',
            'projects',
            'groups',
            'project_groups',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
            'project_config_files',
        ]);
    }

    /**
     * Equivalente a assertHasFields()/assertHasFieldTypes(), pero sin
     * modelo — projects/groups/project_groups (gestion-proyectos) no
     * tienen ActiveRecord propio: los resuelve el mecanismo genérico
     * del usuario, fuera de alcance de ese spec. assertHasFieldTypes()
     * exige un ActiveRecord porque solo usa $model->_TableName(); el
     * resto de su comparación (getDefinitions() de la migración vs.
     * DB->getColumnFields(DB->driver->getColumns($table)) real) no
     * depende del modelo en absoluto, así que se replica aquí
     * pasando el nombre de tabla directo.
     *
     * @return void
     */
    private function _assertMigrationMatchesDb(string $table, string $migrationClass): void {
        $migration    = new $migrationClass();
        $expectedDefs = $migration->getDefinitions();
        $actualDefs   = DB->getColumnFields(DB->driver->getColumns($table));

        $this->assertEquals(
            sizeof($expectedDefs),
            sizeof($actualDefs),
            "La tabla `{$table}` debe tener la misma cantidad de columnas que {$migrationClass}"
        );

        foreach ($expectedDefs as $i => $field):
            $expectedType = explode(' ', $field['type'])[0];
            $actualType   = preg_replace('/\(\d+\)/', '', $actualDefs[$i]['Type']);

            $this->assertEquals($field['field'], $actualDefs[$i]['Field'], "Campo #{$i} de `{$table}` debe ser `{$field['field']}`");
            $this->assertEquals($expectedType, $actualType, "Campo `{$field['field']}` de `{$table}` debe ser tipo {$expectedType}");
        endforeach;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function migrationsTest(): void {
        $this->describe('Verifying Fields');
        $this->assertHasFields($this->AppUser);
        $this->assertHasFields($this->Event);
        $this->assertHasFields($this->OemMetric);
        $this->assertHasFields($this->WorkflowDefinition);
        $this->assertHasFields($this->WorkflowStepDefinition);
        $this->assertHasFields($this->WorkflowExecution);
        $this->assertHasFields($this->StepExecution);
        $this->assertHasFields($this->ProjectConfigFile);

        $this->describe('Verifying Field types');
        $this->assertHasFieldTypes($this->AppUser);
        $this->assertHasFieldTypes($this->Event);
        $this->assertHasFieldTypes($this->OemMetric);
        $this->assertHasFieldTypes($this->WorkflowDefinition);
        $this->assertHasFieldTypes($this->WorkflowStepDefinition);
        $this->assertHasFieldTypes($this->WorkflowExecution);
        $this->assertHasFieldTypes($this->StepExecution);
        $this->assertHasFieldTypes($this->ProjectConfigFile);

        $this->describe('Verifying gestion-proyectos migrations (sin modelo — mecanismo genérico)');
        $this->_assertMigrationMatchesDb('projects', CreateProjects::class);
        $this->_assertMigrationMatchesDb('groups', CreateGroups::class);
        $this->_assertMigrationMatchesDb('project_groups', CreateProjectGroups::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function relationsTest(): void {
        $this->describe('Verifying object relations');
        $this->assertTrue(
            in_array('project', $this->ProjectConfigFile->belongs_to),
            'Verify ProjectConfigFile belongs_to Project relation'
        );
    }
}
