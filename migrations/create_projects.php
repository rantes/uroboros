<?php
  class CreateProject extends Migrations {
    function _init_() {
          $this->_fields = array(
              array('field'=>'id', 'type'=>'INT AUTO_INCREMENT PRIMARY KEY', 'null'=>'false'),
				array('field'=>'name', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'),
				array('field'=>'description', 'type'=>'TEXT', 'null'=>'false'),
				array('field'=>'path', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'),
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