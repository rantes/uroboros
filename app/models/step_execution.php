<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class StepExecution extends ActiveRecord {

    public ?int    $workflow_execution_id       = null;
    public ?int    $workflow_step_definition_id = null;
    public ?string $status                      = null;
    public ?int    $exit_code                   = null;
    public ?string $output                      = null;
    public ?int    $started_at                  = null;
    public ?int    $completed_at                = null;

    public function _init_(): void {
        $this->belongs_to = ['workflow_execution', 'workflow_step_definition'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'workflow_execution_id',       'message' => 'La ejecución del workflow es obligatoria'],
                ['field' => 'workflow_step_definition_id', 'message' => 'El paso del workflow es obligatorio'],
                ['field' => 'status',                       'message' => 'El status es obligatorio'],
            ],
        ];

        $this->before_save = ['validateStatus'];
    }

    // Sin sanitizeOutput(): es la salida cruda del script/comando
    // externo (logs), no texto para render. htmlentities() la
    // corrompería. Mismo criterio que Event::payload y
    // WorkflowDefinition::webhook_token. Ver design.md.

    /**
     * No existe una regla de validación tipo "inclusión en lista" en
     * $this->validate — mismo hallazgo ya confirmado en
     * Project::validateType(). Lista cerrada aplicada a mano en un
     * hook before_save.
     */
    public function validateStatus(): void {
        $validStatuses = ['pending', 'running', 'completed', 'failed'];

        in_array($this->status, $validStatuses, true)
            or $this->_error->add(['field' => 'status', 'message' => 'El status debe ser uno de: ' . implode(', ', $validStatuses)]);
    }
}
