<?php
class testUserModel extends dumboTests {
    private $_pass = 'Th1$1s4Test';
    private $_testFullUser = [
        'firstname' => 'test',
        'lastname' => 'test',
        'identification_kind_id' => '1',
        'identification' => '123456789',
        'email' => 'email@test.com',
        'password' => 'password',
        'created_at' => 0,
        'updated_at' => 0,
    ];

    public function beforeEach() {
        $this->_migrateTables([
            'users'
        ]);
    }

    public function modelExistTest() {
        $this->describe('Should exists the User Model');
        $user = $this->User->Niu();

        $this->assertFalse(empty($user), 'Assert there is a User model instance');
        $this->assertTrue(is_a($user, 'ActiveRecord'), 'Assert the instance is an ActiveRecord instance');
    }

    public function triggerErrorsOnInsertTest() {
        $this->describe('Should have errors trying to insert new reg: mandatory fields');
        $testUser = [
            'firstname' => 'test',
            'lastname' => 'test',
            'identification_kind_id' => '1',
            'identification' => '123456789',
            'email' => 'notemail',
        ];
        $user = $this->User->Niu();
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 4, 'Assert the number of errors must be 3.');
        $this->assertEquals($result, false, 'Assert the result sould be false.');
        $this->assertEquals(in_array('email', $errors), true, 'Assert if the field email is in the errors.');
        $this->assertEquals(in_array('firstname', $errors), true, 'Assert if the field firstname is in the errors.');
        $this->assertEquals(in_array('lastname', $errors), true, 'Assert if the field lastname is in the errors.');
        $this->assertEquals(in_array('password', $errors), true, 'Assert if the field password is in the errors.');

        $user = $this->User->Niu($testUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->describe('Should have errors trying to insert new reg: email address should be proper');
        $this->assertEquals($result, false, 'Assert the result sould be false.');
        $this->assertEquals(sizeof($errors), 2, 'Assert the number of errors must be 2: email and password are mandatory.');
        $this->assertEquals(in_array('email', $errors), true, 'Assert if the field email is in the errors.');
        $this->assertEquals(in_array('password', $errors), true, 'Assert if the field password is in the errors due a mandatory field.');
    }

    public function saveOkTest() {
        $this->describe('Should have no errors trying to insert new reg');
        $user = $this->User->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertEquals($result, true, 'Assert the result sould be true.');
    }

    public function sanitizationTest() {
        $this->describe('Should have sanitize first and last name on trying to insert new reg');
        $this->_testFullUser['firstname'] = 'niño';
        $this->_testFullUser['lastname'] = 'nuñez';
        $user = $this->User->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertEquals($result, true, 'Assert the result sould be true.');
        $this->assertEquals($user->firstname, 'ni&ntilde;o', 'Assert the firstname sanitization.');
        $this->assertEquals($user->lastname, 'nu&ntilde;ez', 'Assert the lastname sanitization.');
    }

    public function passwordEncryptTest() {
        $this->describe('Should encrypt password on trying to insert new reg');
        $this->_testFullUser['password'] = $this->_pass;

        $user = $this->User->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertEquals($result, true, 'Assert the result sould be true.');
        $this->assertEquals($user->password, sha1($this->_pass), 'Assert password is encrypted.');
    }


    public function loginTest() {
        $this->describe('Should Rejects a wrong login attempts and allows to login with the proper credentials');
        $GLOBALS['env'] = 'test';
        $this->_migrateTables([
            'users'
        ]);

        $this->_testFullUser['password'] = sha1($this->_pass);
        $user = $this->User->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();
        $this->assertEquals($result, true, 'Assert there is no errors inserting data for testing.');
        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');

        try {
            $this->User->login();
        } catch (Throwable $e) {
            $this->assertEquals('ArgumentCountError', get_class($e), 'Should throw an exception for no params');
        }

        try {
            $this->User->login('a param');
        } catch (Throwable $e) {
            $this->assertEquals('ArgumentCountError', get_class($e), 'Should throw an exception for not enough params');
        }

        $this->assertEquals($this->User->login('any', 'any'), 0, 'Should not allow to login if email or/and password not match, returning 0');
        $this->assertEquals($this->User->login($user->email, 'not password'), 0, 'Should not allow to login if email or/and password not match, returning 0');
        $this->assertTrue(is_integer($this->User->login($user->email, $this->_pass)), 'Should allow to login, returning the user id');
    }
}
?>