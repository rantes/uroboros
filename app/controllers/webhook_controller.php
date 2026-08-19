<?php
namespace App\Controllers;

use App\Controllers\MainController;
use App\Controllers\ControllerException;
use App\Commands\ExecuteWorkflowCommand;
use App\Buses\CommandBus;

/**
 * Controlador público — origen externo (webhook de una plataforma
 * externa como un proveedor Git). Sin sesión/CSRF:
 * exceptsBeforeFilter cubre toda la superficie de este controlador,
 * no solo una acción. Ver design.md — "Disparo por webhook".
 */
class WebhookController extends MainController {

    public function __construct() {
        parent::__construct();
        $this->exceptsBeforeFilter = [
            'controllers' => 'webhook',
        ];
    }

    public function triggerAction(): void {
        $this->layout = null;

        try {
            $definition = $this->WorkflowDefinition->Find([
                ':first',
                'conditions' => [['webhook_token', $this->params['token'] ?? '']],
            ]);

            empty($definition->id)
                and throw new ControllerException('Token inválido', HTTP_401);

            (new CommandBus())->Dispatch(
                new ExecuteWorkflowCommand((int) $definition->id, 'webhook')
            );

            $this->_code = HTTP_202;
            $this->_response['message'] = 'En cola, se procesa en el próximo ciclo (hasta 1 minuto)';
        } catch (ControllerException $e) {
            $this->_code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (\Exception $e) {
            $this->_code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($this->_code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }
}
