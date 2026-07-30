<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateGroups extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',         'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'name',       'type' => 'VARCHAR', 'null' => 'false', 'limit' => '255'],
            ['field' => 'created_at', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
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
