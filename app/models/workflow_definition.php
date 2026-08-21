<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class WorkflowDefinition extends ActiveRecord {

    public ?string $name          = null;
    public ?string $description   = null;
    public ?int    $project_id    = null;
    public ?int    $status        = null;
    public ?string $webhook_token = null;
    public ?int    $workflow_definition_id = null;

    public function _init_(): void {
        $this->belongs_to = ['project'];
        $this->has_many   = ['workflow_step_definitions', 'workflow_executions'];
        $this->has_many_and_belongs_to = ['workflow_definition'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'name',           'message' => 'El nombre es obligatorio'],
                ['field' => 'project_id',     'message' => 'El proyecto es obligatorio'],
                ['field' => 'webhook_token',  'message' => 'El webhook_token es obligatorio'],
            ],
            'unique' => [
                ['field' => 'name', 'message' => 'Ya existe un workflow con ese nombre'],
            ],
        ];

        $this->before_save = ['sanitizeName', 'sanitizeDescription', 'validateCascadeNotSelf'];
    }

    public function sanitizeName(): void {
        $this->name = htmlentities(trim((string) $this->name), ENT_QUOTES, 'UTF-8', false);
    }

    public function sanitizeDescription(): void {
        empty($this->description) or ($this->description = htmlentities(trim($this->description), ENT_QUOTES, 'UTF-8', false));
    }

    // Sin sanitizeWebhookToken(): htmlentities() corrompería el token —
    // es un secreto técnico, no texto para render. Mismo criterio que
    // Event::payload y StepExecution::output. Ver design.md.

    /**
     * Único ciclo que sí se previene en v1 (encadenamiento-workflows,
     * design.md) — un Workflow no puede encadenarse a sí mismo. La
     * detección de ciclos de N nodos queda fuera de alcance a propósito.
     */
    public function validateCascadeNotSelf(): void {
        (!empty($this->workflow_definition_id) and !empty($this->id) and (int) $this->workflow_definition_id === (int) $this->id)
            and $this->_error->add(['field' => 'workflow_definition_id', 'message' => 'Un workflow no puede encadenarse a sí mismo']);
    }

    // has_many_and_belongs_to = ['workflow_definition'] — auto-referencia
    // nativa. Requirió un fix de framework en
    // Core_General_Class::__call() (comparar contra $short, el nombre
    // corto ya calculado de get_class($this), en vez de get_class($this)
    // completo con namespace — $classFromCall nunca lo incluye).
    // Confirmado empíricamente con datos reales tras el fix:
    // $definition->workflow_definition('up')   -> el Workflow origen
    // $definition->workflow_definition('down') -> los Workflows dependientes
    // (ver OnWorkflowCompletedReaction).
}
