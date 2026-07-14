<?php
namespace Migrations;

use DumboPHP\Migrations;

/**
 *
 */
class CreateAppUsers extends Migrations {
    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id', 'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'email', 'type' => 'VARCHAR', 'null' => 'false', 'limit' => '255'],
            ['field' => 'firstname', 'type' => 'VARCHAR', 'null' => 'false', 'limit' => '75'],
            ['field' => 'lastname', 'type' => 'VARCHAR', 'null' => 'false', 'limit' => '75'],
            ['field' => 'password', 'type' => 'VARCHAR', 'null' => 'true', 'limit' => '255'],
            ['field' => 'status', 'type' => 'INTEGER', 'limit' => '11', 'null' => 'false', 'default' => '0'],
            ['field' => 'created_at', 'type' => 'INTEGER', 'limit' => '11', 'null' => 'false', 'default' => '0'],
            ['field' => 'updated_at', 'type' => 'INTEGER', 'limit' => '11', 'null' => 'false', 'default' => '0'],
        ];
    }

    /**
     * @return void
     */
    public function up(): void {
        $this->Create_Table();

        $this->Add_Single_Index('email');
    }

    /**
     * @return void
     */
    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
