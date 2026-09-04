<?php
namespace Migrations;
use DumboPHP\Migrations;

class CreateProjectCredentials extends Migrations {
    public function _init_(): void {
        $this->_fields = [
            ['field'=>'id', 'type'=>'INTEGER', 'autoincrement'=>true, 'primary'=>true],
            ['field'=>'project_id', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11'],
            ['field'=>'name', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'100'],
            ['field'=>'value', 'type'=>'TEXT', 'null'=>'false'],
            ['field'=>'created_at', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11'],
            ['field'=>'updated_at', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();
        $this->Add_Single_Index('project_id');
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
