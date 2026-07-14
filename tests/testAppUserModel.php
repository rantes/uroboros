<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;
class testAppUserModel extends dumboTests {
    private $_pass = 'Th1$1s4Test';
    private $_testFullUser = [
        'firstname' => 'test',
        'lastname' => 'test',
        'email' => 'email@test.com',
        'password' => null,
        'created_at' => 0,
        'updated_at' => 0,
    ];

    public function beforeEach(): void {
        $this->_migrateTables([
            'app_users'
        ]);
    }

    public function modelExistTest(): void {
        $this->describe('Should exists the User Model');
        $user = $this->AppUser->Niu();

        $this->assertFalse(empty($user), 'Assert there is a User model instance');
        $this->assertTrue(is_a($user, 'DumboPHP\ActiveRecord'), 'Assert the instance is an ActiveRecord instance');
    }

    public function triggerErrorsOnInsertTest(): void {
        $this->describe('Should have errors trying to insert new reg: mandatory fields');
        $testUser = [
            'firstname' => 'test',
            'lastname' => 'test',
            'email' => 'notemail',
        ];
        $user = $this->AppUser->Niu();
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 1, 'Assert the number of errors must be 1.');
        $this->assertEquals($result, false, 'Assert the result sould be false.');
        $this->assertEquals(in_array('email', $errors), true, 'Assert if the field email is in the errors.');

        $user = $this->AppUser->Niu($testUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->describe('Should have errors trying to insert new reg: email address should be proper');
        $this->assertEquals($result, false, 'Assert the result sould be false.');
        $this->assertEquals(sizeof($errors), 1, 'Assert the number of errors must be 1.');
        $this->assertEquals(in_array('email', $errors), true, 'Assert if the field email is in the errors.');
    }

    public function saveOkTest(): void {
        $this->describe('Should have no errors trying to insert new reg');
        $user = $this->AppUser->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertEquals($result, true, 'Assert the result sould be true.');
    }

    public function sanitizationTest(): void {
        $this->describe('Should have sanitize first and last name on trying to insert new reg');
        $this->_testFullUser['firstname'] = 'niño';
        $this->_testFullUser['lastname'] = 'nuñez';
        $user = $this->AppUser->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertEquals($result, true, 'Assert the result sould be true.');
        $this->assertEquals($user->firstname, 'ni&ntilde;o', 'Assert the firstname sanitization.');
        $this->assertEquals($user->lastname, 'nu&ntilde;ez', 'Assert the lastname sanitization.');
    }

    public function passwordEncryptTest(): void {
        $this->describe('Should encrypt password on trying to insert new reg');
        $this->_testFullUser['password'] = $this->_pass;

        $user = $this->AppUser->Niu($this->_testFullUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertEquals($result, true, 'Assert the result sould be true.');
        $this->assertTrue(password_verify($this->_pass, $user->password), 'Assert password is encrypted.');
    }

    public function loginSuccessTest(): void {
        $this->describe('Should allow login when credentials and bitmask are valid, returning the user id');

        $this->_testFullUser['status']   = 1;
        $this->_testFullUser['password'] = $this->_pass;

        $user = $this->AppUser->Niu($this->_testFullUser);
        $this->assertTrue($user->Save(), 'Test user must save: ' . (string) $user->_error);

        $id = $this->AppUser->login($user->email, $this->_pass);
        $this->assertEquals((int) $user->id, $id, 'Login should return the real user id.');
    }

    public function loginWrongPasswordTest(): void {
        $this->describe('Should reject login when the password is incorrect');

        $this->_testFullUser['status']   = 1;
        $this->_testFullUser['password'] = $this->_pass;

        $user = $this->AppUser->Niu($this->_testFullUser);
        $this->assertTrue($user->Save(), 'Test user must save: ' . (string) $user->_error);

        // password incorrecta → rechazo aunque el bitmask coincida
        $id = $this->AppUser->login($user->email, 'not the password', 4);
        $this->assertEquals(0, $id, 'Login should return 0 when the password does not match.');
    }

}