<?php
class CreateProject extends Migrations {
    function _init_() {
        $this->_fields = [
            ['field'=>'id', 'type'=>'INT AUTO_INCREMENT PRIMARY KEY', 'null'=>'false'],
            ['field'=>'name', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'description', 'type'=>'TEXT', 'null'=>'true'],
            ['field'=>'path', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'run_after', 'type'=>'INT', 'null'=>'false', 'default'=>'0'],
            ['field'=>'project_group_id', 'type'=>'INT', 'null'=>'false', 'default'=>'0'],
            ['field'=>'created_at', 'type'=>'INT', 'null'=>'false', 'default'=>'0'],
            ['field'=>'updated_at', 'type'=>'INT', 'null'=>'false', 'default'=>'0']
        ];
    }

    function up() {
        $this->Create_Table();
        $this->Add_Column(['field'=>'project_group_id', 'type'=>'INT', 'null'=>'false', 'default'=>'0']);
        $this->Add_Single_Index('run_after');
        $this->Add_Single_Index('project_group_id');
    }

    function down() {
        $this->Drop_Table();
    }
}
?>