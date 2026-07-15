<?php
namespace App\Controllers;

use DumboPHP\Controller;
use DumboPHP\Secrets;
use function DumboPHP\getallheaders;
use function DumboPHP\strGenerate;

/**
 *
 */
abstract class MainController extends Controller {
    public int $_loginLevel = 0;
    public array $_response = [
        'd'       => [],
        'message' => '',
    ];
    public int $_code    = HTTP_200;
    public string $nonce = '';
    public string $uid = '';

    public function __construct() {
        parent::__construct();
        $this->exceptsBeforeFilter = [
            'actions' => 'login,logout,pusher,signin',
            'controllers' => 'index'
        ];
        $this->helper     = ['Sessions'];
        $this->noTemplate = ['logout'];
        $this->layout     = 'layout';
        $this->uid        = $_SERVER['UNIQUE_ID'] ?? strGenerate();
        $this->nonce      = 'nonce-' . base64_encode($this->uid);
    }
    public function __xsfrToken(string $addtional = '') : string {
        $_SESSION['xsfr_token'] = null;
        unset($_SESSION['xsfr_token']);

        if (empty($_SESSION['xsfr_token'])):
            $_SESSION['xsfr_token'] = bin2hex(random_bytes(32) . $addtional);
        endif;

        return $_SESSION['xsfr_token'];
    }
    /**
     * This method is implemented on each controller later
     * Is executed when there are additional actions to perform before filter hook
     */
    public function _additional_before_filter(): void {}

    /**
     * Will perform actions before run any action
     */
    public function before_filter(): void {
        $headers               = getallheaders();
        $_method               = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_requiredTokenMethods = ['POST', 'PUT', 'DELETE'];
        $continue              = true;

        if (! isset($_SESSION['xsfr_token'])):
            $this->__xsfrToken();
        endif;

        if (in_array($_method, $_requiredTokenMethods)):
            if (empty($headers['x-sf-token']) or $headers['x-sf-token'] !== $_SESSION['xsfr_token']):
                $this->_code                = HTTP_403;
                $this->_response['message'] = 'Invalid CSRF Token';
            endif;
        endif;

        if ($_method === 'GET' and !empty($headers['x-sf-token']) and $headers['x-sf-token'] === 'fetch'):
            header('x-sf-token: ' . $this->__xsfrToken());
            $this->_code = HTTP_204;
            $this->_response['message'] = 'CSRF Token refreshed';
            $continue    = false;
        endif;

        if ($continue and $this->requireLogin()):
            $controller = $this->_getController_();
            switch ($controller):
                case 'admin':
                    empty($this->params[0]) and ($this->params[0] = 'list');
                    break;
            endswitch;
            $this->_additional_before_filter();
        else:
            $this->setResponseCode($this->_code);
            $this->respondToAJAX(json_encode($this->_response));
            $this->PreventLoad(true);
        endif;
    }
    /**
     * @return void
     */
    public function loginAction(): void {
        $this->layout = false;
        $this->render = ['layout' => null];

        $ctrl = $this->_getController_();
        $this->loginAction   = "/{$ctrl}/signin";
        $this->loginRedirect = "/{$ctrl}/index";

        if (!empty($_SESSION['user'])):
            $this->redirect("/{$ctrl}/index");
        endif;
    }

    /**
     * @return void
     */
    public function signinAction(): void {

        if ($_SERVER['REQUEST_METHOD'] === 'POST'):
            $this->_code = HTTP_401;
            $this->_response['message'] = 'Usuario o contraseña incorrectos';
            if (! empty($_POST['e']) and ! empty($_POST['p'])):
                $id = $this->AppUser->login($_POST['e'], $_POST['p']);

                if ($id > 0):
                    $user = $this->AppUser->Find($id);
                    $this->_code      = HTTP_201;
                    $_SESSION['user'] = $id;
                    $_SESSION['ul']   = (int) $user->level;

                    $this->_response['d'] = session_id();
                endif;
            endif;
        endif;

        $this->setResponseCode($this->_code);
        $this->respondToAJAX(json_encode($this->_response));
    }
    /**
     * Destroy all registered information of a session and redirect to the login page.
     */
    public function logoutAction(): void {
        $this->render['action'] = false;
        $this->layout           = false;
        php_sapi_name() !== 'cli' and session_destroy();
        $_SESSION = null;
        unset($_SESSION);
        $_SESSION = [];
        $_COOKIE  = [];
        $this->redirect("/{$this->controller}/login");
    }
    /**
     * Will handle the login requirement
     *
     * @return boolean
     */
    public function requireLogin(): bool {
        $actions = ['login', 'signin', 'logout'];
        $canGo   = true;

        // if (empty($_SESSION['user']) and !in_array($this->_getAction_(), $actions)):
        //     $canGo    = false;
        //     $_SESSION = []; // limpia las variables de sesión cruzadas antes de redirigir
        //     $this->redirect(INST_URI . $this->_getController_() . '/login');
        // endif;

        return $canGo;
    }

}
