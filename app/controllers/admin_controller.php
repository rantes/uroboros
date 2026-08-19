<?php
namespace App\Controllers;

use Exception;
use App\Controllers\MainController;
use App\Controllers\AdminBaseTrait;
use App\Controllers\ControllerException;
use App\Commands\ExecuteWorkflowCommand;
use App\Buses\CommandBus;

use DumboPHP\Secrets;

use function DumboPHP\strGenerate;
use function DumboPHP\Singulars;
use function DumboPHP\Camelize;

class AdminController extends MainController {
    use AdminBaseTrait;

    public bool $paginate = true;

    public function __construct() {
        parent::__construct();
        $this->operationalShell = true;
        $this->helper[]  = 'OperationalShell';
        $this->_readOnlyModels = ['event', 'workflow_execution', 'step_execution'];
        $this->_actions  = [
            'projects',
            'groups',
            'events',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
        ];
        $this->noyes = ['no', 'si'];
        $this->statuses = ['Inactivo', 'Activo'];
    }
    /**
     * Set additional behaviors before filter hook
     */
    public function _additional_before_filter(): void {
        $this->_prepare_data();
        switch ($this->_prevAction):
            case 'workflow_definitions':
                if (in_array($this->params[0], ['edit', 'add'])):
                    $this->projects = $this->Project->Find();
                endif;
            break;
            case 'workflow_step_definitions':
                // Colección anidada, no una lista global — cada
                // pantalla de gestión de pasos referencia un único
                // WorkflowDefinition por parámetro (design.md,
                // "Gestión de pasos anidados").
                $this->workflowDefinitionId = (int) ($this->params['workflow_definition_id'] ?? 0);
                $this->_listConditions = "`workflow_definition_id`='{$this->workflowDefinitionId}'";
            break;
        endswitch;
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

    /**
     * Disparo manual de un WorkflowDefinition (Requisito 2). No pasa
     * por AdminBaseTrait — es una acción de dominio real (despacha un
     * Command), no un registro que se guarda. Responde 202: la
     * ejecución sigue en background, procesada por
     * WorkflowRunnerController vía cron. Ver design.md.
     */
    public function executeworkflowAction(): void {
        $this->layout = null;

        try {
            $workflowDefinitionId = (int) ($this->params['id'] ?? 0);

            empty($workflowDefinitionId)
                and throw new ControllerException('workflow_definition_id requerido', HTTP_400);

            (new CommandBus())->Dispatch(
                new ExecuteWorkflowCommand($workflowDefinitionId, 'manual')
            );

            $this->_code = HTTP_202;
            $this->_response['message'] = 'En cola, se procesa en el próximo ciclo (hasta 1 minuto)';
        } catch (ControllerException $e) {
            $this->_code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $this->_code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($this->_code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }

}
