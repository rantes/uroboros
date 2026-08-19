<?php
namespace App\Controllers;

use App\Commands\RunStepCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

/**
 * Sin sesión/HTTP — invocado vía `dumbo run
 * workflow_runner/processpending`, disparado por `cron`. Mismo patrón
 * que los Command Handlers/Reactions: extiende DumboPHP\Controller
 * directamente, no MainController.
 */
class WorkflowRunnerController extends Controller {

    /**
     * Bucle, no una sola pasada: procesa toda la cadena disponible en
     * una única invocación — si al correr un paso se encola el
     * siguiente, este mismo `while` lo recoge sin esperar al próximo
     * `cron`. Sigue siendo estrictamente secuencial, nunca dos
     * RunStepCommand a la vez. Ver design.md — "Controlador de
     * background".
     */
    public function processpendingAction(): void {
        $this->render = ['action' => false, 'layout' => false];

        while (true):
            $next = $this->StepExecution->Find([
                ':first',
                'conditions' => "`status`='pending'",
                'sort'       => '`id` ASC',
            ]);

            if (empty($next->id)):
                break;
            endif;

            (new CommandBus())->Dispatch(new RunStepCommand($next->id));
        endwhile;
    }
}
