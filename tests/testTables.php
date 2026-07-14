<?php
namespace Tests;

use DumboPHP\lib\Timothy\dumboTests;

class testTables extends dumboTests {

    /**
     * Force to connect to real DB in order to check the integration
     *
     * @return void
     */
    public function beforeEach(): void {
        /** before each test the table should be reset */
        $this->_migrateTables([
            'app_users'
        ]);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function migrationsTest(): void {
        $this->describe('Verifying Fields');
        $this->assertHasFields($this->AppUser);

        $this->describe('Verifying Field types');
        $this->assertHasFieldTypes($this->AppUser);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function relationsTest(): void {
        $this->describe('Verifying object relations');
    }
}
