<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class WorkflowExecution extends ActiveRecord {

    public ?int    $workflow_definition_id = null;
    public ?string $status                 = null;
    public ?string $trigger_type           = null;
    public ?int    $started_at             = null;
    public ?int    $completed_at           = null;

    public function _init_(): void {
        $this->belongs_to = ['workflow_definition'];
        $this->has_many   = ['step_executions'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'workflow_definition_id', 'message' => 'El workflow es obligatorio'],
                ['field' => 'status',                  'message' => 'El status es obligatorio'],
                ['field' => 'trigger_type',            'message' => 'El trigger_type es obligatorio'],
            ],
        ];

        $this->before_save = ['validateStatus', 'validateTriggerType'];
    }

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

    public function validateTriggerType(): void {
        $validTriggerTypes = ['manual', 'webhook'];

        in_array($this->trigger_type, $validTriggerTypes, true)
            or $this->_error->add(['field' => 'trigger_type', 'message' => 'El trigger_type debe ser uno de: ' . implode(', ', $validTriggerTypes)]);
    }
}
