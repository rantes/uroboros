<?php
class CreateProjectGroup extends Migrations {
    function _init_() {
          $this->_fields = [
              ['field'=>'id', 'type'=>'INT AUTO_INCREMENT PRIMARY KEY', 'null'=>'false'],
                ['field'=>'name', 'type'=>'VARCHAR', 'null'=>'false', 'limit'=>'255'],
                ['field'=>'created_at', 'type'=>'INT', 'null'=>'false'],
                ['field'=>'updated_at', 'type'=>'INT', 'null'=>'false']
          ];
    }

    function up() {
        $this->Create_Table();
    }

    function down() {
        $this->Drop_Table();
    }
}
