<?php
class CreateProject extends Migrations {
    function _init_() {
        $this->_fields = [
            ['field' => 'id', 'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field'=>'name', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'description', 'type'=>'TEXT', 'null'=>'true'],
            ['field'=>'path', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'git_repo', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'run_after', 'type'=>'INTEGER', 'null'=>'false', 'default'=>'0'],
            ['field'=>'project_group_id', 'type'=>'INTEGER', 'null'=>'false', 'default'=>'0'],
            ['field'=>'created_at', 'type'=>'INTEGER', 'null'=>'false', 'default'=>'0'],
            ['field'=>'updated_at', 'type'=>'INTEGER', 'null'=>'false', 'default'=>'0']
        ];
    }

    function up() {
        $this->Create_Table();
        $this->Add_Single_Index('run_after');
        $this->Add_Single_Index('project_group_id');
    }

    function down() {
        $this->Drop_Table();
    }
}
?>