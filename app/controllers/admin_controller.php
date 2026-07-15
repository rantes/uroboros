<?php
namespace App\Controllers;

use Exception;
use App\Controllers\MainController;
use App\Controllers\AdminBaseTrait;

use DumboPHP\Secrets;

use function DumboPHP\strGenerate;
use function DumboPHP\Singulars;
use function DumboPHP\Camelize;

class AdminController extends MainController {
    use AdminBaseTrait;

    public bool $paginate = true;

    public function __construct() {
        parent::__construct();
        $this->_actions = [
            'projects',
            'groups'
        ];
        $this->noyes = ['no', 'si'];
        $this->statuses = ['Inactivo', 'Activo'];
    }
    /**
     * Set additional behaviors before filter hook
     */
    public function _additional_before_filter(): void {
        $this->_prepare_data();
        // switch ($this->_prevAction):
        //     case 'announcements':
        //         $this->_model_camelized = 'Content';
        //         if (in_array($this->params[0], ['edit', 'add'])):
        //             // $this->documentKinds = $this->Param->Find_by_name('document_kind');
        //         endif;
        //     break;
        //     case 'guards':
        //         $this->_model_camelized = 'AppUser';
        //         if (in_array($this->params[0], ['edit', 'add'])):
        //             $this->documentKinds = $this->Param->Find_by_name('document_kind');
        //         endif;
        //     break;
        //     case 'users':
        //         $this->_model_camelized = 'User';
        //         // $this->_listConditions = '`level`=2';
        //         if (in_array($this->params[0], ['edit', 'add'])):
        //             $this->documentKinds = $this->Param->Find_by_name('document_kind');
        //             $this->parentProperties = $this->Property->Find_by_property_id(0);
        //             $this->uProperties = empty($this->params[1]) ?
        //                 $this->UserProperty->Niu() :
        //                 $this->UserProperty->Find_by_user_id($this->params[1]);
        //         endif;
        //         if ($this->params[0] === 'save'):
        //             if (!empty($_POST['user_property']) and is_array($_POST['user_property'])):
        //                 foreach ($_POST['user_property'] as $p):
        //                     $up = $this->UserProperty->Niu($p);
        //                     if (!$up->Save()):
        //                         throw new ControllerException((string)$up->_error, HTTP_500);
        //                     endif;
        //                     // Al registrar al propietario principal se crea su
        //                     // cuenta de portal automáticamente (KMD-ROLES M3).
        //                     $up->is_owner and $this->_ensureOwnerAppUser((int) $up->user_id);
        //                 endforeach;
        //             endif;
        //         endif;
        //     break;
        // endswitch;
    }


    public function indexAction(): void {
        $this->sectionTitle = 'Inicio';
        $this->adminCRUDAction = '';
        $this->paginate = false;
    }

}
