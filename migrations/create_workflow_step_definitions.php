<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateWorkflowStepDefinitions extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',                      'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'workflow_definition_id',  'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'name',                     'type' => 'VARCHAR', 'null' => 'false', 'limit' => '255'],
            ['field' => 'type',                     'type' => 'VARCHAR', 'null' => 'false', 'limit' => '50'],
            ['field' => 'command',                  'type' => 'TEXT',    'null' => 'false'],
            ['field' => 'step_order',               'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'created_at',               'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at',               'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();

        // Compuesto, no único: Add_Index()/AddIndex() del framework real
        // no soporta declarar UNIQUE (mismo hallazgo ya confirmado para
        // project_groups y oem_metrics). El controlador de background
        // consulta constantemente por workflow_definition_id ordenado
        // por step_order — ver design.md.
        $this->Add_Index(['name' => 'idx_wf_step_defs_wfdef_order', 'fields' => ['workflow_definition_id', 'step_order']]);
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
