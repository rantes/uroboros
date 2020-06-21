<?php
  class CreateCommand extends Migrations {
    function _init_() {
          $this->_fields = array(
              array('field'=>'id', 'type'=>'INT AUTO_INCREMENT PRIMARY KEY', 'null'=>'false'),
				array('field'=>'project_id', 'type'=>'INT', 'null'=>'false'),
				array('field'=>'command', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255')
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