<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class Project extends ActiveRecord {

    public ?string $name           = null;
    public ?string $description    = null;
    public ?string $repository_url = null;
    public ?string $type           = null;
    public ?int    $status         = null;

    public function _init_(): void {
        $this->has_many = ['project_groups'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'name', 'message' => 'El nombre es obligatorio'],
            ],
            'unique' => [
                ['field' => 'name', 'message' => 'Ya existe un proyecto con ese nombre'],
            ],
        ];

        $this->before_save = ['sanitizeName', 'sanitizeDescription', 'sanitizeRepositoryUrl', 'validateType'];
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
