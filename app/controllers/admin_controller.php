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
            case 'projects':
                if (in_array($this->params[0] ?? null, ['edit', 'add'])):
                    $this->groups = $this->Group->Find();
                    $this->selectedGroupIds = [];
                    if ($this->params[0] === 'edit' and !empty($this->params[1])):
                        $assigned = $this->ProjectGroup->Find(['conditions' => [['project_id', (int) $this->params[1]]]]);
                        foreach ($assigned as $projectGroup):
                            $this->selectedGroupIds[] = (int) $projectGroup->group_id;
                        endforeach;
                    endif;
                endif;
            break;
            case 'workflow_definitions':
                if (in_array($this->params[0] ?? null, ['edit', 'add'])):
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

        // Active Operations — único widget con dominio real hoy
        // (ejecucion-workflows). El resto del grid (Operational
        // Health, Risk Indicators, Dependency Graph, Recomendaciones)
        // sigue en _widget-empty-state.phtml hasta que exista el
        // dominio correspondiente — ver dashboard-shell/design.md.
        $this->activeExecutions = $this->WorkflowExecution->Find([
            'conditions' => [
                ['status', 'IN', ['pending', 'running']],
            ],
            'sort'  => '`id` DESC',
            'limit' => 10,
        ]);
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

    /**
     * Acción dedicada para Proyecto — el CRUD genérico de
     * AdminBaseTrait no puede sincronizar el pivote project_groups
     * desde un dmb-select multiple (Find()/Save() no conocen esa
     * columna). 'saveproject' NO está en $this->_actions
     * deliberadamente: _prepare_data() nunca debe interceptarla. Ver
     * design.md, "Asignación de Grupos — acción dedicada".
     *
     * Confirmado contra AdminBaseTrait::_create_reg()/_update_reg()
     * (código real, no el snippet ilustrativo de design.md): ambos
     * simplemente llaman Niu($array) con el array completo del POST
     * (incluyendo 'id' cuando existe) y dejan que Save() detecte
     * INSERT/UPDATE según la presencia de $this->id. No hay ningún
     * Find()+asignación campo a campo — por eso aquí tampoco hace
     * falta.
     */
    public function saveprojectAction(): void {
        $this->layout = null;
        $code = HTTP_200;

        try {
            $data = $_POST['project'] ?? [];
            empty($data) and throw new ControllerException('Datos de proyecto requeridos', HTTP_422);

            $groupIds = $data['groups'] ?? [];
            unset($data['groups']);
            !empty($data['id']) and ($data['id'] = (int) $data['id']);

            $project = $this->Project->Niu($data);
            $project->Save()
                or throw new ControllerException((string) $project->_error, HTTP_422);

            // Sync simple del pivote: borra todas las filas existentes
            // de este proyecto y recrea desde la selección actual.
            // Más simple que diffear altas/bajas — la escala (pocos
            // grupos por proyecto) no justifica un diff real.
            $existing = $this->ProjectGroup->Find(['conditions' => [['project_id', $project->id]]]);
            foreach ($existing as $projectGroup):
                $projectGroup->Delete();
            endforeach;

            foreach ($groupIds as $groupId):
                $newProjectGroup = $this->ProjectGroup->Niu([
                    'project_id' => $project->id,
                    'group_id'   => (int) $groupId,
                ]);
                $newProjectGroup->Save()
                    or throw new ControllerException((string) $newProjectGroup->_error, HTTP_422);
            endforeach;

            $this->_response['d'] = $project;
            $this->_response['message'] = 'Proyecto guardado satisfactoriamente';
        } catch (ControllerException $e) {
            $code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }

}
