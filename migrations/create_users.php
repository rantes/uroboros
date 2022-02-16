<?php
class CreateUser extends Migrations{
    function _init_() {
        $this->_fields = [
            ['field' => 'id', 'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field'=>'email', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field'=>'firstname', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'75'],
            ['field'=>'lastname', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'75'],
            ['field'=>'password', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
            ['field' => 'status', 'type' => 'INTEGER', 'null' => FALSE, 'default'=>'0'],
            ['field' => 'created_at', 'type' => 'INTEGER', 'null' => FALSE, 'default'=>'0'],
            ['field' => 'updated_at', 'type' => 'INTEGER', 'null' => FALSE, 'default'=>'0']
        ];
    }
    function up(){
        $this->Create_Table();
        $this->Add_Single_Index('email');
    }
    function down(){
        $this->Drop_Table();
    }
}
