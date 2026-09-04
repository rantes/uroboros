# Diseño técnico — Credenciales de Proyecto

## Migración y modelo

### `create_project_credentials.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `project_id` | INTEGER | NOT NULL — `belongs_to Project` |
| `name` | VARCHAR(100) | NOT NULL — identificador corto, usado en `{{credential:name}}` |
| `value` | TEXT | NOT NULL — **cifrado**, nunca texto plano |

Índice: `Add_Index(['project_id'])`. Único por `(project_id, name)` —
mismo criterio y misma limitación ya conocida que
`ProjectConfigFile` (`Add_Index()` no soporta `UNIQUE` real,
validación a nivel de aplicación).

### `app/models/project_credential.php`

Reutiliza el mismo patrón de cifrado ya construido y probado en
`ProjectConfigFile` — mismo `CONFIG_FILES_ENCRYPTION_KEY`, mismo
mecanismo `openssl_encrypt`/`openssl_decrypt` AES-256-GCM. **No
reinventar el cifrado, extraer a un trait o método compartido si el
duplicado entre ambos modelos resulta significativo** — decisión de
implementación, no bloqueante.

```php
class ProjectCredential extends ActiveRecord {
    public ?int    $project_id = null;
    public ?string $name       = null;
    public ?string $value      = null; // cifrado en reposo

    public function _init_(): void {
        $this->validates_presence_of('name');
        $this->belongs_to = ['project'];
        $this->before_save = ['sanitizeName', 'validateUniqueName', 'encryptValue'];
    }

    public function DecryptedValue(): string {
        // mismo mecanismo que ProjectConfigFile::DecryptedContent()
    }
}
```

**Sin método de "mostrar"** expuesto por ningún endpoint público —
`DecryptedValue()` se usa únicamente desde `RunStepCommandHandler` y
desde la lógica de enmascarado, nunca desde una acción que responda a
un fetch de la UI (a diferencia de `ProjectConfigFile`, que sí tiene
el botón "Mostrar").

## Sustitución del placeholder — `RunStepCommandHandler`

Antes del `cd` + `exec()` ya existente (de `working_directory`):

```php
private function _substituteCredentials(string $command, int $projectId): array {
    $credentials = $this->ProjectCredential->Find(['conditions' => [['project_id', $projectId]]]);
    $missing     = [];

    foreach ($credentials as $credential):
        $placeholder = '{{credential:' . $credential->name . '}}';
        str_contains($command, $placeholder)
            and ($command = str_replace($placeholder, $credential->DecryptedValue(), $command));
    endforeach;

    // Requisito 2.2 — cualquier placeholder que quedó sin sustituir
    // es una credencial referenciada que no existe para este proyecto
    preg_match_all('/\{\{credential:([a-zA-Z0-9_]+)\}\}/', $command, $matches);
    $missing = $matches[1];

    return ['command' => $command, 'missing' => $missing];
}
```

> **Verificar antes de implementar:** el orden de aplicación de este
> paso respecto al de `working_directory` (¿primero credenciales,
> luego `cd`? ¿o al revés?) — no debería importar funcionalmente
> (operan sobre partes distintas del string), pero confirmarlo con un
> caso real que combine ambos, no asumirlo.

Si `missing` no está vacío, el step falla limpio (mismo patrón ya
usado para `working_directory` ausente): `exit_code = 1`, mensaje
claro listando qué credencial(es) faltan, **nunca** ejecuta el
comando con el placeholder literal todavía presente.

## Enmascarado de `output` antes de guardar

En el mismo `RunStepCommandHandler`, después de `exec()`, antes de
persistir `StepExecution.output`:

```php
private function _maskCredentials(string $output, int $projectId): string {
    $credentials = $this->ProjectCredential->Find(['conditions' => [['project_id', $projectId]]]);

    foreach ($credentials as $credential):
        $output = str_replace($credential->DecryptedValue(), '***', $output);
    endforeach;

    return $output;
}
```

Se aplica a **todas** las credenciales del proyecto, no solo a la(s)
referenciada(s) en el `command` de ese step específico — mismo
criterio que GitHub Actions (enmascara cualquier secreto registrado,
sin importar si ese step lo usó directamente).

> **Cuidado de rendimiento, no bloqueante en v1:** con muchas
> credenciales por proyecto, esto es una pasada de `str_replace` por
> cada una sobre el `output` completo — aceptable a la escala actual
> del proyecto (pocos devops, pocos proyectos), no optimizado más
> allá de eso.

## Vistas

CRUD estándar (`project_credential_list.phtml`/`_addedit.phtml`),
filtrado por `project_id` (mismo mecanismo `$this->_listConditions`
ya usado para `workflow_step_definitions`/`project_config_files`).
**El listado nunca muestra el valor** — ni oculto-revelable como
`ProjectConfigFile.is_secret`, directamente ausente de la respuesta
del servidor (no solo ocultado en CSS/JS). Solo se muestra `name` y
metadatos (`created_at`/`updated_at`).

Link desde `admin/project_list.phtml` hacia las credenciales de cada
proyecto — mismo patrón "Ver pasos"/"Ver archivos" ya usado, para no
repetir el hueco de navegación que ya nos costó una ronda completa
antes en esta sesión.

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.