<?php

include_once INST_PATH.'app/controllers/index_controller.php';
class testIndexController extends dumboTests {
    public function beforeEach() {
        /** before each test the table should be reset */
        $this->_migrateTables([
            'users',
            'projects',
            'project_groups',
            'commands',
            'executions'
        ]);
        http_response_code(HTTP_200);
        $_SESSION = [];
    }

    public function _init_() {
        $this->_controller = new IndexController();
    }

    public function controllerExistTest() {
        $this->describe('Should exists the Controller');

        $this->assertFalse(empty($this->_controller), 'Assert there is a controller instance');
        $this->assertTrue(is_a($this->_controller, 'Page'), 'Assert the instance is a Controller instance');
    }

    public function signinActionTest() {
        $this->describe('Should test signin action.');

        $_POST = null;
        $result = $this->_runAction('/index/signin');
        $obj = json_decode($result->_outputContent);
        $code = http_response_code();
        $this->assertTrue($code !== HTTP_404, 'Should exist the action and the answer is not 404.');
        $this->assertTrue(is_object($obj), 'Should respond with a JSON.');
        $this->assertTrue($code === HTTP_401, 'Should set a 401 response code on signin try with no params.');

        $_POST['u'] = '';
        $_POST['p'] = '';

        $result = $this->_runAction('/index/signin');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_401, 'Should set a 401 response code on signin try with empty post values.');

        $_POST['u'] = 'user';
        $_POST['p'] = '';

        $result = $this->_runAction('/index/signin');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_401, 'Should set a 401 response code on signin try with empty password value.');

        $_POST['u'] = '';
        $_POST['p'] = 'password';

        $result = $this->_runAction('/index/signin');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_401, 'Should set a 401 response code on signin try with empty user value.');

        $_POST['u'] = 'user';
        $_POST['p'] = 'password';

        $result = $this->_runAction('/index/signin');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_401, 'Should set a 401 response code on signin try for wrong user.');

        $testUser = [
            'firstname' => 'test',
            'lastname' => 'test',
            'identification_kind_id' => '1',
            'identification' => '123456789',
            'email' => 'email@test.com',
            'password' => 'password',
            'created_at' => 0,
            'updated_at' => 0
        ];

        $user = $this->User->Niu($testUser);
        $result = $user->Save();
        $errors = $user->_error->errFields();

        //validations to ensure the user is set properly before
        $this->assertEquals(sizeof($errors), 0, 'Assert the number of errors must be 0.');
        $this->assertTrue($result, 'Assert the result sould be true.');

        $_POST['u'] = 'email@test.com';
        $_POST['p'] = 'password';

        $result = $this->_runAction('/index/signin');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_202, 'Should return 202 response code on signin try for proper user and proper password.');
        $this->assertTrue($_SESSION['user'] === 1, 'Should set the user id in the session');
    }

    public function logoutActionTest() {
        $this->describe('Should test logout action.');

        $result = $this->_runAction('/index/logout');
        $code = http_response_code();
        $this->assertTrue($code !== HTTP_404, 'Should exist the action and the answer is not 404.');

        $_SESSION['user'] = '1';
        $result = $this->_runAction('/index/logout');
        $code = http_response_code();
        $this->assertTrue(empty($_SESSION['user']), 'Should there is no any data in session.');
        $this->assertTrue($code === HTTP_302, 'Should set a redirect.');
    }

    public function indexActionTest() {
        $this->describe('Should test index action.');


        $_SESSION['user'] = 1;
        $result = $this->_runAction('/index/index');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_200, 'Should exist the action and the answer is not 404.');
    }

    public function loginActionTest() {
        $this->describe('Should test login action.');

        $_SESSION = null;
        $result = $this->_runAction('/index/login');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_200, 'Should exist the action and the answer is 200.');

        $_SESSION['user'] = 1;
        $result = $this->_runAction('/index/login');
        $code = http_response_code();
        $this->assertTrue($code === HTTP_302, 'Should redirect when a session is active.');
    }
}

?>