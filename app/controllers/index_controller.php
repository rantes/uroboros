<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class IndexController extends Page {
    use AdminBaseTrait;

    public $exceptsBeforeFilter = [
        'actions' => 'login,logout'
    ];

    public function __construct() {
        $this->_init();
    }

    public function indexAction() {
        $conditions = null;
        $this->params['group'] = $this->params['group'] ?? 0;
        if(!empty($this->params['group'])):
            $conditions = ['conditions' => [
                ['project_group_id', (integer) $this->params['group']]
            ]];
        endif;
        $this->projects = $this->Project->Find($conditions);
        $this->groups = $this->ProjectGroup->Find();
        $this->projectstatus = ['Fail','Success'];
    }

    public function loginAction() {
        $this->layout = false;
    }
    public function signinAction() {
        $code = HTTP_401;

        if (!empty($_POST['e']) and !empty($_POST['p'])):
            $id = $this->User->login($_POST['e'], $_POST['p']);
            $id > 0 and ($code = HTTP_202) and ($_SESSION['user'] = $id);
        endif;

        http_response_code($code);
        $this->respondToAJAX('{}');
    }

    /**
    * Destroy all registered information of a session and redirect to the login page.
    */
    public function logoutAction() {
        $this->layout = false;
        empty($_SESSION) or session_destroy();
        header('Location: /index/login');
    }
}