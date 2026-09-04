<?php
namespace App\Reactions;

use App\Models\Event;
use App\Commands\QueueStepCommand;
use App\Commands\FailWorkflowCommand;
use App\Buses\CommandBus;
use DumboPHP\Controller;

class OnWorkflowStartedReaction extends Controller {

    public function Handle(Event $event): void {
        $payload   = json_decode($event->payload, true);
        $firstStep = $this->WorkflowStepDefinition->Find([
            ':first',
            'conditions' => [['workflow_definition_id', $payload['workflow_definition_id']]],
            'sort'       => '`step_order` ASC',
        ]);

        // Un Workflow sin pasos definidos nunca debe encolar un
        // QueueStepCommand con un id inventado (0) — mismo criterio
        // que OnStepCompletedReaction usa counter() === 0 para
        // detectar "no encontrado", no ($firstStep->id ?? 0), que
        // silenciosamente produce una referencia a un
        // WorkflowStepDefinition inexistente (ver StepExecution
        // huérfano con workflow_step_definition_id=0, causa raíz del
        // TypeError en RunStepCommandHandler::_substituteCredentials()).
        if ($firstStep->counter() === 0):
            (new CommandBus())->Dispatch(new FailWorkflowCommand((int) $event->aggregate_id));
        else:
            (new CommandBus())->Dispatch(
                new QueueStepCommand((int) $event->aggregate_id, (int) $firstStep->id)
            );
        endif;
    }
}
