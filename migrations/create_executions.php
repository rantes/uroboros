<?php
  class CreateExecution extends Migrations {
    function _init_() {
          $this->_fields = array(
              array('field'=>'id', 'type'=>'INT AUTO_INCREMENT PRIMARY KEY', 'null'=>'false'),
				array('field'=>'project_id', 'type'=>'INT', 'null'=>'false'),
				array('field'=>'result', 'type'=>'INT', 'null'=>'false'),
				array('field'=>'percentage', 'type'=>'INT', 'null'=>'false'),
				array('field'=>'created_at', 'type'=>'INT', 'null'=>'false'),
				array('field'=>'updated_at', 'type'=>'INT', 'null'=>'false')
          );
    }

    function up() {
      $this->Create_Table();
    }

    function down() {
      $this->Drop_Table();
    }
  }
?>