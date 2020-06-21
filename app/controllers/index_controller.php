<?php
require_once INST_PATH.'app/controllers/admin_base_trait.php';
class IndexController extends Page {
    use AdminBaseTrait;

    public function __construct() {
        $this->_init();
    }

    public function indexAction() {
    }

    public function loginAction() {
        $this->layout = false;
    }
    public function signinAction() {
        $code = HTTP_401;

        if (!empty($_POST['e']) and !empty($_POST['p'])):
            $user = $this->User->Find([
                'conditions' => "`email`='{$_POST['e']}' AND `password`='".sha1($_POST['p'])."'"
            ]);
            $user->counter() === 1 and ($code = HTTP_202) and ($_SESSION['user'] = $user->id);
        endif;

        http_response_code($code);
        $this->respondToAJAX('{}');
    }

    /**
    * Destroy all registered information of a session and redirect to the login page.
    */
    public function logoutAction() {
        $this->layout = false;
        setSession();
        empty($_SESSION) or session_destroy();
        header('Location: /index/login');
    }
}
?>