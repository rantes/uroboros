<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class ProjectCredential extends ActiveRecord {
    use SecretCipherTrait;

    public ?int    $project_id = null;
    public ?string $name       = null;
    public ?string $value      = null; // cifrado en reposo (AES-256-GCM) — nunca texto plano

    public function _init_(): void {
        $this->validate = [
            'presence_of' => [
                ['field' => 'project_id', 'message' => 'El proyecto es obligatorio'],
                ['field' => 'name',       'message' => 'El nombre es obligatorio'],
            ],
        ];

        $this->belongs_to = ['project'];

        // 'value' no puede ir en $this->validate['presence_of']: los
        // before_save corren ANTES de _ValidateOnSave() (mismo orden
        // ya confirmado en ProjectConfigFile), y encryptValue()
        // convierte cualquier string — incluida '' — en un blob
        // cifrado no vacío. Para cuando _ValidateOnSave() revisara
        // 'value', ya nunca lo vería vacío. validateValuePresence()
        // corre antes de encryptValue() para cerrar ese hueco
        // (encontrado empíricamente con testProjectCredentialModel —
        // rejectMissingValueTest fallaba silenciosamente sin esto).
        $this->before_save = [
            'sanitizeName',
            'validateUniqueName',
            'validateValuePresence',
            'encryptValue',
        ];
    }

    public function sanitizeName(): void {
        $this->name = htmlentities(trim((string) $this->name), ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * $this->validate['unique'] solo soporta una columna sola —
     * mismo criterio ya confirmado con
     * ProjectConfigFile::validateUniqueFilename(), aplicado aquí a
     * la combinación project_id+name.
     */
    public function validateUniqueName(): void {
        $thisClass = get_class($this);
        $existing  = (new $thisClass())->Find([
            'fields'     => 'id',
            'conditions' => "`project_id`='" . (int) $this->project_id . "' AND `name`='{$this->name}' AND `id`<>'" . (int) $this->id . "'",
        ]);

        $existing->counter() > 0
            and $this->_error->add(['field' => 'name', 'message' => 'Ya existe una credencial con ese nombre para este proyecto']);
    }

    public function validateValuePresence(): void {
        empty($this->value)
            and $this->_error->add(['field' => 'value', 'message' => 'El valor es obligatorio']);
    }

    /**
     * Mismo mecanismo que ProjectConfigFile::encryptContent() —
     * misma clave CONFIG_FILES_ENCRYPTION_KEY, misma categoría de
     * dato cifrado, sin reinventar.
     */
    public function encryptValue(): void {
        // Idempotente: un Find() + Save() llega aquí con el blob ya cifrado.
        $this->isEncrypted((string) $this->value)
            or ($this->value = $this->encryptSecret((string) $this->value, 'No se pudo cifrar el valor de la credencial.'));
    }

    /**
     * Sin botón "Mostrar": a diferencia de
     * ProjectConfigFile::DecryptedContent() (que sí se expone vía
     * showconfigfilecontentAction()), este método solo se llama
     * desde RunStepCommandHandler — nunca desde una acción del
     * AdminController que responda a un fetch del navegador.
     */
    public function DecryptedValue(): string {
        return $this->decryptSecret((string) $this->value, 'No se pudo descifrar la credencial — clave inválida o dato corrupto.');
    }
}
