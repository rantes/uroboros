<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class Group extends ActiveRecord {

    public ?string $name = null;

    public function _init_(): void {
        $this->has_many = ['project_groups'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'name', 'message' => 'El nombre es obligatorio'],
            ],
            'unique' => [
                ['field' => 'name', 'message' => 'Ya existe un grupo con ese nombre'],
            ],
        ];

        $this->before_save = ['sanitizeName'];
    }

    public function sanitizeName(): void {
        $this->name = htmlentities(trim((string) $this->name), ENT_QUOTES, 'UTF-8', false);
    }
}
