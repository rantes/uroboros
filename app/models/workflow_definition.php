<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class WorkflowDefinition extends ActiveRecord {

    public ?string $name          = null;
    public ?string $description   = null;
    public ?int    $project_id    = null;
    public ?int    $status        = null;
    public ?string $webhook_token = null;

    public function _init_(): void {
        $this->belongs_to = ['project'];
        $this->has_many   = ['workflow_step_definitions', 'workflow_executions'];

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

        $this->before_save = ['sanitizeName', 'sanitizeDescription'];
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
}
