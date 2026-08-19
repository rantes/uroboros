<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateWorkflowExecutions extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',                     'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'workflow_definition_id', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'status',                 'type' => 'VARCHAR', 'null' => 'false', 'limit' => '20'],
            ['field' => 'trigger_type',           'type' => 'VARCHAR', 'null' => 'false', 'limit' => '20'],
            ['field' => 'started_at',             'type' => 'INTEGER', 'null' => 'true'],
            ['field' => 'completed_at',           'type' => 'INTEGER', 'null' => 'true'],
            ['field' => 'created_at',             'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at',             'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
