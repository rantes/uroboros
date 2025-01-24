<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class IndexController extends Page {
    use AdminBaseTrait;

    public $exceptsBeforeFilter = [
        'actions' => 'logout,signin'
    ];

    public $noTemplate = ['logout'];

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
        !empty($_SESSION['user']) and header('Location: /admin/index');
    }
    public function signinAction() {
        $code = HTTP_401;
        $id = null;

        if (!empty($_POST['u']) and !empty($_POST['p'])):
            $id = $this->User->login($_POST['u'], $_POST['p']);
            $id > 0 and ($code = HTTP_201) and ($_SESSION['user'] = $id);
        endif;
        $this->setResponseCode($code);
        $this->respondToAJAX('{"user":"'.$id.'"}');
    }
    /**
    * Destroy all registered information of a session and redirect to the login page.
    */
    public function logoutAction() {
        $this->layout = false;
        php_sapi_name() !== 'cli' && session_destroy();
        $_SESSION = null;
        unset($_SESSION);
        header('Location: /index/login');
    }
}