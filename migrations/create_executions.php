<?php
  class CreateExecution extends Migrations {
    function _init_() {
          $this->_fields = [
              ['field' => 'id', 'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
              ['field'=>'project_id', 'type'=>'INTEGER', 'null'=>'false'],
              ['field'=>'result', 'type'=>'INTEGER', 'null'=>'false'],
              ['field'=>'percentage', 'type'=>'INTEGER', 'null'=>'false'],
              ['field'=>'created_at', 'type'=>'INTEGER', 'null'=>'false', 'default'=>'0'],
              ['field'=>'updated_at', 'type'=>'INTEGER', 'null'=>'false', 'default'=>'0']
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