<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateWorkflowDefinitions extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',             'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'name',           'type' => 'VARCHAR', 'null' => 'false', 'limit' => '255'],
            ['field' => 'description',    'type' => 'TEXT',    'null' => 'true'],
            ['field' => 'project_id',     'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'status',         'type' => 'INTEGER', 'null' => 'false', 'limit' => '1', 'default' => '0'],
            ['field' => 'webhook_token',  'type' => 'VARCHAR', 'null' => 'false', 'limit' => '64'],
            ['field' => 'workflow_definition_id', 'type' => 'INTEGER', 'null' => 'true', 'limit' => '11'],
            ['field' => 'created_at',     'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at',     'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();
        $this->Add_Single_Index('name');
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
