<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class IndexController extends Page {
    use AdminBaseTrait;

    public $excepts_before_filter = [
        'actions' => 'login,logout'
    ];

    public function __construct() {
        $this->_init();
    }

    public function indexAction() {
        $this->projects = $this->Project->Find();
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
?>