<?php
  class CreateCommand extends Migrations {
    function _init_() {
          $this->_fields = [
              ['field' => 'id', 'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
              ['field'=>'project_id', 'type'=>'INTEGER', 'null'=>'false'],
              ['field'=>'command', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255']
          ];
    }

    function up() {
      $this->Create_Table();
      $this->Add_Single_Index('project_id');
    }

    function down() {
      $this->Drop_Table();
    }
  }
?>