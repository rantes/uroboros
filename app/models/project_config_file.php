<?php
namespace App\Models;

use DumboPHP\ActiveRecord;
use DumboPHP\Secrets;

class ProjectConfigFile extends ActiveRecord {
    public ?int    $project_id = null;
    public ?string $filename   = null;
    public ?string $format     = null;
    public ?string $content    = null; // cifrado en reposo (AES-256-GCM) — nunca texto plano
    public ?int    $is_secret  = null;

    public function _init_(): void {
        $this->validate = [
            'presence_of' => [
                ['field' => 'project_id', 'message' => 'El proyecto es obligatorio'],
                ['field' => 'filename',   'message' => 'El nombre de archivo es obligatorio'],
                ['field' => 'format',     'message' => 'El formato es obligatorio'],
                ['field' => 'content',    'message' => 'El contenido es obligatorio'],
            ],
        ];

        $this->belongs_to = ['project'];

        // Orden verificado contra ActiveRecord::Save() (before_save
        // corre ANTES de _ValidateOnSave(), y se detiene en el primer
        // hook que active $this->_error) — nunca invertir
        // validateFormat() y encryptContent(): validar el contenido ya
        // cifrado siempre "parece" inválido para cualquier formato.
        $this->before_save = [
            'sanitizeFilename',
            'validateFormatType',
            'validateUniqueFilename',
            'validateFormat',
            'encryptContent',
        ];
    }

    public function sanitizeFilename(): void {
        $this->filename = htmlentities(trim((string) $this->filename), ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * No existe una regla de validación tipo "inclusión en lista" en
     * $this->validate — mismo criterio que Project::validateType().
     */
    public function validateFormatType(): void {
        $validFormats = ['ini', 'json', 'yaml'];

        in_array($this->format, $validFormats, true)
            or $this->_error->add(['field' => 'format', 'message' => 'El formato debe ser uno de: ' . implode(', ', $validFormats)]);
    }

    /**
     * $this->validate['unique'] solo soporta una columna sola
     * (confirmado contra ActiveRecord::_ValidateOnSave() —
     * DumboPHP/bin/dumbophp.php) — para la combinación
     * project_id+filename hace falta una validación manual. Mismo
     * patrón que usa el propio framework internamente para 'unique':
     * instanciar una copia nueva de la clase para no mutar $this.
     */
    public function validateUniqueFilename(): void {
        $thisClass = get_class($this);
        $existing  = (new $thisClass())->Find([
            'fields'     => 'id',
            'conditions' => "`project_id`='" . (int) $this->project_id . "' AND `filename`='{$this->filename}' AND `id`<>'" . (int) $this->id . "'",
        ]);

        $existing->counter() > 0
            and $this->_error->add(['field' => 'filename', 'message' => 'Ya existe un archivo con ese nombre para este proyecto']);
    }

    /**
     * Valida el contenido en texto plano contra el formato declarado.
     * Aborta el Save() poblando $this->_error (confirmado contra
     * ActiveRecord::Save() — antes de $this->_error, este antes de
     * ambos era un throw sin capturar, que hubiera devuelto 500
     * genérico en vez de un 422 con el campo correcto).
     */
    public function validateFormat(): void {
        $isValid = match ($this->format) {
            'ini'   => (@parse_ini_string((string) $this->content) !== false),
            'json'  => $this->_validateJson((string) $this->content),
            'yaml'  => $this->_validateYaml((string) $this->content),
            default => false,
        };

        $isValid or $this->_error->add(['field' => 'content', 'message' => "El contenido no es {$this->format} válido."]);
    }

    private function _validateJson(string $content): bool {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * La extensión faltante es un problema de infraestructura del
     * servidor, no de validación de datos del usuario — se deja
     * propagar como excepción (Save() no la captura, sube hasta el
     * try/catch del controlador → HTTP 500), nunca un fallback de
     * parseo alternativo ni un fallo silencioso (Requisito 2.3).
     */
    private function _validateYaml(string $content): bool {
        extension_loaded('yaml')
            or throw new \Exception('La extensión PECL yaml no está instalada en este servidor.');

        return (@yaml_parse($content) !== false);
    }

    /**
     * Clave leída con DumboPHP\Secrets (mismo mecanismo que
     * DB_USERNAME/DB_PASSWORD en config/db_settings.php) — nunca
     * getenv() directo. $this->_secrets no existe como propiedad
     * mágica fuera de Connection (confirmado contra
     * DumboPHP/bin/dumbophp.php), así que se instancia directo, igual
     * que el propio framework hace con Connection::$_secrets.
     */
    public function encryptContent(): void {
        $key = (new Secrets())->get('CONFIG_FILES_ENCRYPTION_KEY');

        empty($key)
            and throw new \Exception('CONFIG_FILES_ENCRYPTION_KEY no está configurada.');

        $iv  = random_bytes(12); // 96 bits, tamaño recomendado para GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            (string) $this->content,
            'aes-256-gcm',
            base64_decode($key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        $ciphertext === false
            and throw new \Exception('No se pudo cifrar el contenido del archivo.');

        // Empaquetado: iv + tag + ciphertext, todo junto en base64 —
        // un solo campo TEXT, sin columnas adicionales para iv/tag.
        $this->content = base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Método, no la propiedad content directamente — la propiedad del
     * modelo siempre refleja lo que realmente está en la columna
     * (cifrado). Cualquier vista/acción que necesite el valor real
     * llama DecryptedContent() explícitamente. Nombre PascalCase, como
     * WorkflowExecution::DeploymentSuccessRate()/LeadTime() — API
     * pública del modelo, no un hook de ciclo de vida.
     */
    public function DecryptedContent(): string {
        $key = (new Secrets())->get('CONFIG_FILES_ENCRYPTION_KEY');

        empty($key)
            and throw new \Exception('CONFIG_FILES_ENCRYPTION_KEY no está configurada.');

        $raw = base64_decode((string) $this->content);

        $iv         = substr($raw, 0, 12);
        $tag        = substr($raw, 12, 16); // GCM tag real: 16 bytes (verificado)
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            base64_decode($key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        $plaintext === false
            and throw new \Exception('No se pudo descifrar el archivo — clave inválida o dato corrupto.');

        return $plaintext;
    }
}
