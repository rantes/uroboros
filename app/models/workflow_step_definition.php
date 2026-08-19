<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class WorkflowStepDefinition extends ActiveRecord {

    public ?int    $workflow_definition_id = null;
    public ?string $name                   = null;
    public ?string $type                   = null;
    public ?string $command                = null;
    public ?int    $step_order             = null;

    public function _init_(): void {
        $this->belongs_to = ['workflow_definition'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'workflow_definition_id', 'message' => 'El workflow es obligatorio'],
                ['field' => 'name',                    'message' => 'El nombre es obligatorio'],
                ['field' => 'type',                    'message' => 'El tipo es obligatorio'],
                ['field' => 'command',                  'message' => 'El comando es obligatorio'],
                ['field' => 'step_order',               'message' => 'El orden es obligatorio'],
            ],
        ];

        $this->before_save = ['sanitizeName', 'validateType'];
    }

    public function sanitizeName(): void {
        $this->name = htmlentities(trim((string) $this->name), ENT_QUOTES, 'UTF-8', false);
    }

    // Sin sanitizeCommand(): es el script/comando externo a ejecutar,
    // contenido técnico, no texto para render. htmlentities() lo
    // corrompería. Ver design.md.

    /**
     * No existe una regla de validación tipo "inclusión en lista" en
     * $this->validate — mismo hallazgo ya confirmado en
     * Project::validateType(). Lista cerrada aplicada a mano en un
     * hook before_save.
     */
    public function validateType(): void {
        $validTypes = ['build', 'deploy', 'rollback', 'migration', 'verification', 'custom'];

        in_array($this->type, $validTypes, true)
            or $this->_error->add(['field' => 'type', 'message' => 'El tipo debe ser uno de: ' . implode(', ', $validTypes)]);
    }
}
