<?php
namespace Migrations;
use DumboPHP\Migrations;

class CreateProjectConfigFiles extends Migrations {
    public function _init_(): void {
        $this->_fields = [
            ['field'=>'id', 'type'=>'INTEGER', 'autoincrement'=>true, 'primary'=>true],
            ['field'=>'project_id', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11'],
            ['field'=>'filename', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'format', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'20'],
            ['field'=>'content', 'type'=>'TEXT', 'null'=>'false'],
            ['field'=>'is_secret', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11', 'default'=>'0'],
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
