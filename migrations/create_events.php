<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateEvents extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',             'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'aggregate_type', 'type' => 'VARCHAR', 'null' => 'false', 'limit' => '100'],
            ['field' => 'aggregate_id',   'type' => 'INTEGER', 'null' => 'false'],
            ['field' => 'event_type',     'type' => 'VARCHAR', 'null' => 'false', 'limit' => '150'],
            ['field' => 'payload',        'type' => 'TEXT',    'null' => true],
            ['field' => 'created_at',     'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at',     'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();
        $this->Add_Index(['name' => 'idx_events_aggregate', 'fields' => ['aggregate_type', 'aggregate_id']]);
        $this->Add_Single_Index('event_type');
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
