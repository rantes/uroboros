<?php
namespace App\Controllers;

use Exception;
use App\Controllers\ControllerException;
use function DumboPHP\Singulars;
use function DumboPHP\Camelize;

trait AdminBaseTrait {
    protected string $_model = '';
    protected string $_model_camelized = '';
    protected bool $_is_routed = false;
    protected array $_actions = [];
    protected string $_listConditions = '';
    protected string $_prevAction = '';
    public array $noyes = [];
    public array $statuses = [];
    public string $adminCRUDAction = 'list';
    public string $loginTitle      = 'UROBOROS';
    public $searchFields = [];
    public $sectionTitle = '';

    /**
     * Detecta si la acción actual está en $_actions y redirige internamente
     * hacia landingAction(), guardando la acción original en $_prevAction.
     * Llamar desde _additional_before_filter() en cada controlador que use el trait.
     */
    protected function _prepare_data(): void {
        if (in_array($this->action, $this->_actions)):
            $this->_model           = Singulars($this->action);
            $this->_model_camelized = Camelize($this->_model);
            $this->_is_routed       = true;
            $this->_prevAction      = $this->action;
            $this->action           = 'landing';
        endif;
    }

    /**
     * @return void
     * @throws ControllerException
     */
    private function _edit_reg(): void {
        $this->layout = false;
        $this->title = 'Editar Registro';

        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('ETag: 123');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Pragma: no-cache');

        if(empty($this->params[1])):
            throw new ControllerException('ID param must be given', HTTP_400);
        else:
            $this->render = ['file'=>"admin/{$this->_model}_addedit.phtml"];
            $this->data = $this->{$this->_model_camelized}->Find($this->params[1]);

            // switch ($this->_model):
            //     case 'property':
            //         $this->_addeditproperty();
            //     break;
            // endswitch;
        endif;
    }

    /**
     * @return void
     */
    private function _add_reg(): void {
        $this->layout = false;
        $this->render = ['file'=>"admin/{$this->_model}_addedit.phtml"];
        $this->data = $this->{$this->_model_camelized}->Niu();
        $this->title = 'Agregar Registro';

        switch ($this->_model):
        endswitch;
    }

    /**
     * @return void
     */
    private function _list_regs(): void {
        $this->render = ['file'=>"admin/{$this->_model}_list.phtml"];
        $this->adminCRUDAction = 'list';
        $conditions = '';
        $this->searchterm = '';

        if(!empty($_POST['search']) and !empty($_POST['search']['term']) and !empty($_POST['search']['fields'])):
            $term = str_replace("'", "''", (string) ($_POST['search']['term'] ?? ''));
            $this->searchterm = $term;
            while(null !== ($field = array_shift($_POST['search']['fields']))):
                // Solo permitir identificadores válidos (letras, números, guión bajo)
                // — $field viene de $_POST y se interpola directo como nombre de columna.
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)):
                    continue;
                endif;
                empty($conditions) or ($conditions = "{$conditions} OR ");
                $conditions = "{$conditions}`{$field}` LIKE '%{$term}%'";
            endwhile;
        endif;
        empty($conditions) or ($conditions = "{$conditions}");
        empty($this->_listConditions) or ($this->_listConditions = "{$this->_listConditions}");
        !empty($this->_listConditions)
            and !empty($conditions)
            and ($conditions = "{$this->_listConditions} AND {$conditions}");
        empty($conditions) and !empty($this->_listConditions) and ($conditions = $this->_listConditions);

        $this->data = $this->{$this->_model_camelized}->Paginate($this->fullUrl(), ['conditions'=>$conditions]);

        switch ($this->_model):
            case 'project':
                $this->sectionTitle = 'Proyectos';
            break;
            case 'group':
                $this->sectionTitle = 'Grupos';
            break;
        endswitch;
    }

    /**
     * @return void
     */
    private function _delete_reg(): void {
        $code = HTTP_422;

        $id = $this->params['id'] ?? $this->params[1];
        if (!empty($this->_model) and !empty($id)):
            $obj = $this->{$this->_model_camelized}->Find((integer)$id);
            (
                $obj->Delete()
                    and ($code = HTTP_200)
                    and ($this->_response['message'] = 'Registro eliminado satisfactoriamente')
            ) or ($this->_response['m'] = (string)$obj->_error);
        endif;

        $this->setResponseCode($code);
        $this->respondToAJAX(json_encode($this->_response));
    }

    private function _parse_put_input(): array {
        if (APP_ENV === 'test'):
            return [];
        endif;
        $dataParsed = '';
        preg_match_all('@name="([a-z_\[\]]+)"[\s]+(.+)\s-@m', file_get_contents("php://input"), $out);
        foreach($out[1] as $i => $val):
            $clean = trim(urlencode($out[2][$i]));
            $dataParsed = "{$dataParsed}{$val}={$clean}&";
        endforeach;
        $result = [];
        parse_str($dataParsed, $result);
        return $result;
    }

    public function _update_reg(?array $putfp = null): void {
        $code = HTTP_404;
        $this->layout = null;

        $putfp ??= $this->_parse_put_input();

        if(empty($this->params['id']) or empty($putfp) or empty($putfp[$this->_model])):
            throw new ControllerException('Registro no encontrado.', $code);
        endif;
        foreach($putfp[$this->_model] as $i => $val):
            $putfp[$this->_model][$i] = trim($val);
        endforeach;
        $putfp[$this->_model]['id'] = (int) $this->params['id'];
        $data = $this->{$this->_model_camelized}->Niu($putfp[$this->_model]);

        if ($data->Save()):
            $this->_response['d'] = [];
            $this->_response['message'] = 'Registro actualizado satisfactoriamente';
            $code = HTTP_204;
        else:
            throw new ControllerException((string) $data->_error, HTTP_406);
        endif;

        $this->setResponseCode($code);
        $this->respondToAJAX(json_encode($this->_response));
    }

    /**
     * @return void
     */
    private function _create_reg(): void {
        $code = HTTP_422;

        if(!empty($this->_model) and !empty($_POST[$this->_model])):
            if (isset($_POST[$this->_model]['id'])) unset($_POST[$this->_model]['id']);
            $data = $this->{$this->_model_camelized}->Niu($_POST[$this->_model]);
            (
                $data->Save()
                and (
                    $this->_response['d'] = $data
                    and ($this->_response['message'] = 'Registro creado satisfactoriamente') and $code = HTTP_201)
            )
            or throw new ControllerException((string)$data->_error, $code);
        endif;

        $this->setResponseCode($code);
        $this->respondToAJAX(json_encode($this->_response));
    }
    /**
     * @return void
     */
    public function landingAction(): void {
        $this->render = ['text'=>'noop'];
        $code = HTTP_200;
        try {
            if($this->_is_routed):
                empty($this->params[0]) and ($this->params[0] = 'list');
                switch ($_SERVER['REQUEST_METHOD']):
                    case 'GET':
                        switch ($this->params[0]):
                            case 'list':
                                $this->_list_regs();
                            break;
                            case 'edit':
                                $this->_edit_reg();
                            break;
                            case 'add':
                                $this->_add_reg();
                            break;
                            default:
                                $this->setResponseCode(HTTP_404);
                                $this->respondToAJAX(json_encode($this->_response));
                            break;
                        endswitch;
                        break;
                    case 'DELETE':
                        $this->_delete_reg();
                        break;
                    case 'PUT':
                        $this->_update_reg();
                        break;
                    case 'POST':
                        if ($this->params[0] === 'list'):
                            $this->_list_regs();
                        else:
                            $this->_create_reg();
                        endif;
                        break;
                    default:
                        $this->setResponseCode(HTTP_404);
                        $this->respondToAJAX(json_encode($this->_response));
                        break;
                endswitch;
            endif;
        } catch (ControllerException $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
        } catch (Exception $e) {
            $code = HTTP_500;
            $message = $e->getMessage();
        } finally {
            $this->setResponseCode($code);
            if (!empty($message)):
                $this->render = ['text'=>$message];
                $this->yield = $message;
            endif;
        }
    }
}
