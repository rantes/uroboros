<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class Project extends ActiveRecord {

    public ?string $name           = null;
    public ?string $description    = null;
    public ?string $repository_url = null;
    public ?string $working_directory = null;
    public ?string $type           = null;
    public ?int    $status         = null;

    public function _init_(): void {
        $this->has_many  = ['project_groups'];
        // Confirmado en ActiveRecord::_delete_or_nullify_dependents()
        // (DumboPHP/bin/dumbophp.php): recorre $has_many, resuelve
        // App\Models\ProjectGroup (existe como modelo real) y borra
        // cada fila hija cuando dependents='destroy'. Sin esto el
        // pivote queda huérfano al eliminar un Project.
        $this->dependents = 'destroy';

        $this->validate = [
            'presence_of' => [
                ['field' => 'name', 'message' => 'El nombre es obligatorio'],
            ],
            'unique' => [
                ['field' => 'name', 'message' => 'Ya existe un proyecto con ese nombre'],
            ],
        ];

        $this->before_save = ['sanitizeName', 'sanitizeDescription', 'sanitizeRepositoryUrl', 'sanitizeWorkingDirectory', 'validateType'];
    }

    public function sanitizeName(): void {
        $this->name = htmlentities(trim((string) $this->name), ENT_QUOTES, 'UTF-8', false);
    }

    public function sanitizeDescription(): void {
        empty($this->description) or ($this->description = htmlentities(trim($this->description), ENT_QUOTES, 'UTF-8', false));
    }

    public function sanitizeRepositoryUrl(): void {
        empty($this->repository_url) or ($this->repository_url = htmlentities(trim($this->repository_url), ENT_QUOTES, 'UTF-8', false));
    }

    /**
     * Texto libre normal (mismo criterio que repository_url) — no el
     * criterio especial de command/output (esos son contenido
     * técnico sin sanitizar, ver WorkflowStepDefinition/StepExecution).
     * working_directory es una ruta que un devops escribe en un
     * formulario, se sanitiza igual que el resto de campos de texto.
     */
    public function sanitizeWorkingDirectory(): void {
        empty($this->working_directory) or ($this->working_directory = htmlentities(trim($this->working_directory), ENT_QUOTES, 'UTF-8', false));
    }

    /**
     * No existe una regla de validación tipo "inclusión en lista" en
     * $this->validate — confirmado contra DumboPHP/bin/dumbophp.php
     * (ActiveRecord::_ValidateOnSave() solo implementa
     * email/numeric/unique/presence_of). Lista cerrada aplicada a
     * mano en un hook before_save.
     */
    public function validateType(): void {
        $validTypes = ['backend', 'frontend', 'library', 'mobile'];

        in_array($this->type, $validTypes, true)
            or $this->_error->add(['field' => 'type', 'message' => 'El tipo debe ser uno de: ' . implode(', ', $validTypes)]);
    }
}
