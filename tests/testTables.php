<?php
class testTables extends dumboTests {
    /**
     * Force to connect to real DB in order to check the integration
     *
     * @return void
     */
    public function beforeEach() {
        $GLOBALS['Connection'] = null;
        $GLOBALS['driver'] = null;
        unset($GLOBALS['Connection']);
        $GLOBALS['env'] = APP_ENV;
        $GLOBALS['Connection'] = new Connection();
    }

    public function migrationsTest() {
        $this->assertHasFields($this->Command);
        $this->assertHasFields($this->Execution);
        $this->assertHasFields($this->ProjectGroup);
        $this->assertHasFields($this->Project);
        $this->assertHasFields($this->User);

        $this->assertHasFieldTypes($this->Command);
        $this->assertHasFieldTypes($this->Execution);
        $this->assertHasFieldTypes($this->ProjectGroup);
        $this->assertHasFieldTypes($this->Project);
        $this->assertHasFieldTypes($this->User);
    }

    /**
     * Force to renew connection for test proceed
     *
     * @return void
     */
    public function _end_() {
        $GLOBALS['Connection'] = null;
        unset($GLOBALS['Connection']);
        $GLOBALS['driver'] = null;
        unset($GLOBALS['driver']);
    }
}
