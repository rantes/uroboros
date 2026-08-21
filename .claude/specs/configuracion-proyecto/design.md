# Diseño técnico — Archivos de Configuración de Proyecto

## Migración y modelo

### `create_project_config_files.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `project_id` | INTEGER | NOT NULL — `belongs_to Project` |
| `filename` | VARCHAR(255) | NOT NULL — ruta relativa, ej. `.env`, `config/database.yaml` |
| `format` | VARCHAR(20) | NOT NULL — lista cerrada: `ini`/`json`/`yaml` |
| `content` | TEXT | NOT NULL — **contenido cifrado**, nunca texto plano |
| `is_secret` | INTEGER | `default=0` — 0/1, puramente UI |

Índice: `Add_Index(['project_id'])`. Único razonable por
`(project_id, filename)` para evitar duplicados accidentales del
mismo archivo — `Add_Index()` no soporta `UNIQUE` (ya confirmado en
sesiones anteriores), así que esto se valida a nivel de aplicación
(`unique` en `$this->validate`, con el criterio compuesto si el
framework lo soporta, o una validación manual si no).

> **Verificar antes de implementar:** si la regla `unique` de
> `$this->validate` soporta unicidad compuesta (dos columnas juntas)
> o solo una columna — no asumido, confirmar contra el código real
> de validación (`_ValidateOnSave()`).

### `app/models/project_config_file.php`

```php
class ProjectConfigFile extends ActiveRecord {
    public ?int    $project_id = null;
    public ?string $filename   = null;
    public ?string $format     = null;
    public ?string $content    = null;  // cifrado en reposo
    public ?int    $is_secret  = null;

    public function _init_(): void {
        $this->validates_presence_of('filename');
        $this->validates_presence_of('format');

        $this->belongs_to = ['project'];

        $this->before_save = ['sanitizeFilename', 'validateFormat', 'encryptContent'];
    }

    // ... hooks, ver más abajo
}
```

**Orden de los hooks importa** — `validateFormat()` debe correr
**antes** de `encryptContent()`, porque valida el contenido en texto
plano (lo que llega del formulario), no el cifrado. Si
`validateFormat()` fallara, el hook debe impedir el guardado — a
confirmar el mecanismo real de cómo un `before_save` puede abortar un
`Save()` en este framework (¿lanzando excepción? ¿poblando
`$this->_error` manualmente? — no asumido, verificar contra un hook
`before_save` real ya existente en el proyecto, ej. los de
`sanitizarCampo()` en otros modelos, que no necesitan abortar nada).

## Cifrado — mecanismo exacto

```php
public function encryptContent(): void {
    $key = getenv('CONFIG_FILES_ENCRYPTION_KEY');
    // o el mecanismo real de lectura de .env.secrets del proyecto —
    // confirmar contra config/db_settings.php cómo se lee
    // $this->_secrets->get() en vez de getenv() directo, no asumido

    $iv  = random_bytes(12); // 96 bits, tamaño recomendado para GCM
    $tag = '';

    $ciphertext = openssl_encrypt(
        $this->content,
        'aes-256-gcm',
        base64_decode($key),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    // Empaquetado: iv + tag + ciphertext, todo junto en base64 —
    // un solo campo TEXT, sin columnas adicionales para iv/tag
    $this->content = base64_encode($iv . $tag . $ciphertext);
}

public function DecryptedContent(): string {
    $key = getenv('CONFIG_FILES_ENCRYPTION_KEY');
    $raw = base64_decode($this->content);

    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, 12, 16); // GCM tag es 16 bytes
    $ciphertext = substr($raw, 28);

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        base64_decode($key),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    $plaintext === false and throw new \Exception('No se pudo descifrar el archivo — clave inválida o dato corrupto.');

    return $plaintext;
}
```

`DecryptedContent()` es un **método**, no la propiedad `content`
directamente — la propiedad del modelo siempre refleja lo que
realmente está en la columna (cifrado), coherente con "las
propiedades del modelo reflejan el tipo/valor real de la columna en
BD". Cualquier vista/acción que necesite el valor real llama
`$configFile->DecryptedContent()` explícitamente.

> **Verificar antes de implementar:**
> 1. Cómo se lee `CONFIG_FILES_ENCRYPTION_KEY` en este framework
>    específico — `getenv()` directo, o el mecanismo de
>    `$this->_secrets->get()` ya usado para `DB_PASSWORD` (más
>    consistente con el resto del proyecto, probablemente el correcto
>    — confirmar).
> 2. Disponibilidad de `openssl_encrypt()`/`openssl_decrypt()` con
>    `aes-256-gcm` en el PHP real del servidor — la extensión
>    `openssl` casi siempre está disponible, pero no asumido sin
>    confirmar.
> 3. Tamaño real del tag de GCM en la versión de OpenSSL del
>    servidor — 16 bytes es el estándar, pero confirmar, no asumido a
>    ciegas.

## Validación de formato (antes de cifrar)

```php
public function validateFormat(): void {
    $isValid = match ($this->format) {
        'ini'   => (parse_ini_string($this->content) !== false),
        'json'  => (json_decode($this->content) !== null || $this->content === 'null'),
        'yaml'  => $this->_validateYaml(),
        default => false,
    };

    $isValid or throw new \Exception("El contenido no es {$this->format} válido.");
}

private function _validateYaml(): bool {
    extension_loaded('yaml')
        or throw new \Exception('La extensión PECL yaml no está instalada en este servidor.');

    return (@yaml_parse($this->content) !== false);
}
```

> **`json_decode() !== null` es una validación imperfecta** — un JSON
> que literalmente es la palabra `null` es válido pero indistinguible
> de un error con esta comparación simple. Usar
> `json_last_error() === JSON_ERROR_NONE` en su lugar, es la forma
> correcta — el snippet de arriba es ilustrativo, no el final.

## Acción de sincronización al disco

Acción dedicada en `AdminController` (no pasa por el CRUD genérico,
mismo patrón que `saveprojectAction()`/`executeworkflowAction()`):

```php
public function syncconfigfilesAction(): void {
    $this->layout = null;
    $code = HTTP_200;

    try {
        $projectId = (int) ($this->params['project_id'] ?? 0);
        $project   = $this->Project->Find($projectId);

        empty($project->working_directory)
            and throw new ControllerException('El proyecto no tiene working_directory configurado.', HTTP_422);

        $files = $this->ProjectConfigFile->Find(['conditions' => [['project_id', $projectId]]]);

        foreach ($files as $file):
            $this->_writeConfigFile($project->working_directory, $file);
        endforeach;

        $this->_response['message'] = 'Archivos sincronizados correctamente.';
    } catch (ControllerException $e) {
        $code = $e->getCode();
        $this->_response['message'] = $e->getMessage();
    } catch (\Exception $e) {
        $code = HTTP_500;
        $this->_response['message'] = $e->getMessage();
    } finally {
        $this->setResponseCode($code);
        $this->respondToAJAX(json_encode($this->_response));
    }
}

private function _writeConfigFile(string $baseDir, ProjectConfigFile $file): void {
    $realBase = realpath($baseDir);
    $target   = $realBase . DIRECTORY_SEPARATOR . $file->filename;
    $realTarget = realpath(dirname($target)) ?: null;

    // Requisito 4.3 — nunca escribir fuera de working_directory
    (str_starts_with($target, $realBase) and ($realTarget === null or str_starts_with($realTarget, $realBase)))
        or throw new \Exception("Nombre de archivo inválido: {$file->filename}");

    is_dir(dirname($target)) or mkdir(dirname($target), 0755, true);

    file_put_contents($target, $file->DecryptedContent())
        or throw new \Exception("No se pudo escribir {$file->filename}");
}
```

> **La verificación de path traversal de arriba es ilustrativa, no
> verificada** — confirmar con casos de prueba reales
> (`../../etc/passwd`, rutas absolutas, symlinks) que efectivamente
> bloquea todos los intentos razonables antes de dar esto por
> resuelto. Seguridad real, no solo "parece que funciona".

## Vistas

CRUD estándar (`project_config_file_list.phtml`/`_addedit.phtml`),
filtrado por `project_id` (mismo mecanismo `$this->_listConditions`
ya usado para `workflow_step_definitions`). Botón "Sincronizar al
disco" en la vista de listado, apunta a `syncconfigfilesAction()`.

`is_secret`: en el listado, si `is_secret = 1`, mostrar el valor como
`••••••••` con un botón "Mostrar" que hace un fetch a una acción que
devuelve `DecryptedContent()` — nunca incluir el contenido descifrado
en el HTML inicial de la página para archivos secretos (evita que
quede en el historial del navegador/logs de acceso sin necesidad).

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.