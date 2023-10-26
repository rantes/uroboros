<?php
class CreateSetting extends Migrations {
    function _init_() {
        $this->_fields = [
            ['field'=>'id', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11', 'primary'=>true, 'autoincrement'=>true],
            ['field'=>'name', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'value', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'created_at', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11'],
            ['field'=>'updated_at', 'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11']
        ];
    }

    function up() {
        $this->Create_Table();
    }

    function down() {
        $this->Drop_Table();
    }
}
