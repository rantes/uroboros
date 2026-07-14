# Convenciones de código — Uroboros

## PHP

### Namespaces y archivos

| Capa | Namespace | Carpeta | Nombre de archivo |
|------|-----------|---------|-------------------|
| Controladores | `App\Controllers` | `app/controllers/` | `nombre_controller.php` |
| Modelos | `App\Models` | `app/models/` | `nombre_modelo.php` |
| Helpers | `App\Helpers` | `app/helpers/` | `Nombre_Helper.php` |
| Migraciones | `Migrations` | `migrations/` | `create_nombre_tabla.php` |
| Tests | `tests` | `tests/` | `testNombreClase.php` |
| Commands | `App\Commands` | `app/commands/` | `nombre_command.php` |
| Command Handlers | `App\CommandHandlers` | `app/command_handlers/` | `nombre_command_handler.php` |
| Reactions | `App\Reactions` | `app/reactions/` | `nombre_reaction.php` |
| Buses | `App\Buses` | `app/buses/` | `nombre_bus.php` |
| Configuración runtime | (ninguno — archivo plano, retorna array) | `config/` | `nombre_config.php` (ej. reactions_map.php, junto a host.php, db_settings.php) |
| Fixtures de test | `tests\fixtures` (minúscula, como tests\) | `tests/fixtures/` | `NombreClase.php` — PascalCase idéntico a la clase, igual que el resto de tests/, no snake_case como app/ |

Nota aparte para dejar explícita en el documento, porque no es obvia
para quien lea la tabla por primera vez: config/ es la única
carpeta de esta lista que no participa del autoload por namespace.
Sus archivos se cargan con include/require explícito desde donde
se necesiten (ver App\Buses\EventBus::Dispatch()), no por
resolución automática de clase.

### Clases

```php
// Controlador
class AdminController extends MainController { }

// Modelo
class UserProperty extends ActiveRecord { }

// Migración
class CreateUserProperties extends Migrations { }

// Test
class testUserPropertyModel extends dumboTests { }

// Command — DTO plano, sin lógica, nunca persiste
class PingCommand { }

// Command Handler — único autorizado a crear Events
class PingCommandHandler extends \DumboPHP\Controller { }

// Reaction — nunca crea Events, solo despacha Commands
class OnPingStartedReaction extends \DumboPHP\Controller { }

// Bus — clase concreta, se instancia con `new` en cada punto de uso,
// sin loader mágico ni interfaz
class CommandBus { }
class EventBus { }
```

### Métodos

```php
// Acciones de controlador: camelCase + sufijo Action
public function indexAction(): void { }
public function landingAction(): void { }
public function savepedestrianinAction(): void { }

// Métodos de test: camelCase + sufijo Test
public function modelExistTest(): void { }
public function saveOkTest(): void { }
public function signinActionTest(): void { }

// Hooks de modelo: camelCase descriptivo
public function sanitizeFirstname(): void { }
public function validateRepeated(): void { }
public function encryptPassword(): void { }

// Métodos privados de controlador: _snake_case con prefijo _
private function _list_regs(): void { }
private function _edit_reg(): void { }
private function _prepare_data(): void { }
```

### Bloques de control

**Siempre** usar la sintaxis alternativa con `:`. **Nunca** usar llaves `{}` en estructuras de control.

```php
// CORRECTO
if ($condicion):
    // ...
elseif ($otra):
    // ...
else:
    // ...
endif;

foreach ($items as $item):
    // ...
endforeach;

switch ($valor):
    case 'a':
        // ...
    break;
    case 'b':
        // ...
    break;
    default:
        // ...
    break;
endswitch;

for ($i = 0; $i < $n; $i++):
    // ...
endfor;

while ($condicion):
    // ...
endwhile;

// INCORRECTO — nunca usar llaves en este proyecto
if ($condicion) {
    // ...
}
```

### Operadores de cortocircuito en lugar de if triviales

El proyecto usa operadores de cortocircuito para lógica simple y asignaciones condicionales:

```php
// Correcto — idiomático en DumboPHP
empty($this->params[0]) and ($this->params[0] = 'list');
file_exists($path) or die('Archivo no encontrado.');
$replace && ($action = 'REPLACE');

// Evitar para lógica simple
if (empty($this->params[0])) {
    $this->params[0] = 'list';
}
```

### Operadores de asignación en condiciones

El proyecto usa el patrón de asignación con `and` para encadenar operaciones:

```php
// Patrón del proyecto
$data->Save()
    and ($this->_response['d'] = $data)
    and ($this->_response['message'] = 'Creado')
    and ($code = HTTP_201)
or throw new ControllerException((string)$data->_error, $code);

// Asignación condicional
empty($this->params[0]) and ($this->params[0] = 'list');
```

### Type hints

Usar type hints en todos los métodos públicos y protegidos. Para propiedades de modelos, siempre `?string` — el ORM castea internamente:

```php
// Métodos — tipos específicos
public function indexAction(): void { }
public function login(string $email, string $password, int $level): int { }
public function _init_(): void { }
public function beforeEach(): void { }

// Propiedades de modelo — siempre ?string
public ?string $nombre   = null;
public ?string $precio   = null;  // aunque sea numérico en BD
public ?string $status   = null;

// Incorrecto en propiedades de modelo
public int   $precio = 0;   // el ORM trabaja con strings internamente
public float $factor = 0;
public bool  $activo = false;
```

### Manejo de errores en controladores

```php
// Siempre este patrón para acciones JSON
public function miAccionAction(): void {
    $this->layout = null;
    try {
        // lógica
    } catch (ControllerException $e) {
        $this->_code = $e->getCode();
        $this->_response['message'] = $e->getMessage();
    } catch (\Exception $e) {
        $this->_code = HTTP_500;
        $this->_response['message'] = $e->getMessage();
    } finally {
        http_response_code($this->_code);
        $this->respondToAJAX(json_encode($this->_response));
    }
}
```

### Sanitización de datos

```php
// Siempre en hooks before_save del modelo
public function sanitizarCampo(): void {
    $this->campo = htmlentities($this->campo ?? '', ENT_QUOTES, 'UTF-8', false);
}

// En controladores al recibir datos de formulario
$nombre = htmlentities(trim($data[0]), ENT_QUOTES, 'ISO-8859-15', false);
```

### Casting explícito

```php
// Siempre castear IDs y enteros de params
$id = (int) $this->params['id'];
$id = (integer) $this->params[1];

// Castear resultados de COUNT
$total = (int) $this->Model->Find(['fields' => 'COUNT(id) AS total'])->total;
```

---

## JavaScript

### Módulos ES6

Todo el JS usa módulos ES6 con `import`/`export`. No usar `require()` ni scripts globales.

```js
// Importar
import { DumboDirective } from "../../libs/dumbojs/dumbo.min.js";
import { appModel } from '../models/app-model.factory.js';
import { spinalCord } from "../../libs/app/nerve.js";

// Exportar clase
export class DmbMiComponente extends DumboDirective { }

// Exportar singleton
export const miModelo = new MiModeloClass();
```

### Clases de componentes

```js
// PascalCase para clases
export class DmbNombreComponente extends DumboDirective {
    static selector = 'dmb-nombre-componente'; // kebab-case para el selector

    // Campos privados con #
    #_estado = null;
    #_servicio = undefined;

    // init() en lugar de constructor para lógica de inicialización
    init() {
        this.#_estado = this.getAttribute('estado') || 'default';
    }
}
```

### Nombres de archivos JS

```
dmb-nombre-componente.directive.js   // Componente
dmb-nombre-componente.test.js        // Tests del componente
dmb-nombre-componente.html           // Template
dmb-nombre-componente.scss           // Estilos
nombre-seccion.js                    // Archivo de acción (actions/)
nombre-modelo.factory.js             // Factory de modelo
```

### Promesas

Usar `.then()/.catch()` consistente con el resto del proyecto (no `async/await`):

```js
miModelo.getFromServer()
    .then(data => {
        // manejar respuesta
    })
    .catch(err => {
        dialog.close();
        this.#_dialog.error('Error en el servidor.');
    });
```

---

## HTML / Vistas

### Short tags PHP

```php
<?  // Apertura (sin "php")
<?= // Echo
?>  // Cierre
```

### Atributos de componentes DumboJS

```html
<!-- kebab-case para atributos personalizados -->
<dmb-input
    label="Nombre"
    dmb-name="modelo[campo]"
    dmb-value="<?= $this->data->campo; ?>"
    validate>
</dmb-input>

<!-- IDs con kebab-case -->
<dmb-panel id="panel-form-add-edit-reg" class="dmb-panel"></dmb-panel>
```

---

## SCSS

Los archivos de estilos de componentes usan SCSS. El archivo principal es `ui-components/styles.scss` que importa los componentes. Los estilos base están en `ui-components/base-sass/`.

```scss
// Nombre de archivo: dmb-nombre-componente.scss
// Selector raíz = el tag del componente
dmb-nombre-componente {
    display: block;

    .mi-elemento {
        // estilos
    }
}
```

---

## Comentarios y documentación

```php
/**
 * Descripción del método.
 *
 * @param string $param Descripción
 * @return void
 */
public function miMetodo(string $param): void { }

// Comentario de línea para lógica no obvia
$this->_code = HTTP_401; // Unauthorized — credenciales inválidas
```

```js
/**
 * Descripción del método.
 * @param {string} param - Descripción
 * @returns {Promise}
 */
miMetodo(param) { }
```

---

## Git

- Commits en español o inglés, descriptivos
- Una feature = una rama
- No commitear `.env` ni `.env.secrets` (están en `.gitignore`)
- No commitear `tmp/`, `vendor/` ni `app/webroot/dist/`

---

## Sin métodos ni atributos estáticos

Jamás usar métodos estáticos ni atributos estáticos propios.
Solo se permiten recursos estáticos nativos del lenguaje PHP:
`ClasePHP::CONSTANTE`, `PDO::ATTR_*`, etc.
Siempre usar instancias de objetos.

```php
// MAL
$setting = Setting::get();

// BIEN
$setting = $this->Setting->Find([':first']);
```

## Retorno único — código óptimo

Evitar múltiples `return` en un método. Mantener un flujo de
retorno único usando índices calculados cuando sea posible.

```php
// MAL
if ($a) return 'x';
if ($b) return 'y';
return 'z';

// BIEN
$values = ['z', 'y', 'x'];
return $values[(int)$a + (int)$b];
```

## Control de flujo — return vs throw vs if positivo

### Regla

- `throw` → cuando la condición es un error que debe
  detener la ejecución (fail fast real)
- `if` positivo → cuando la condición define cuándo
  ejecutar algo (sin return silencioso)
- `return` anticipado → solo aceptable cuando hay
  múltiples condiciones de error con throw previas
  y continuar sería anidar excesivamente

### Anti-patrones — nunca hacer esto

```php
// MAL — return silencioso en lugar de if positivo
public function validateSomething(): void {
    if (empty($this->id)):
        return;
    endif;
    // lógica...
}

// MAL — return silencioso en controlador
public function someAction(): void {
    if (!$this->someCondition()):
        return;
    endif;
    // lógica...
}
```

### Correcto

```php
// BIEN — if positivo (la condición define cuándo ejecutar)
public function validateSomething(): void {
    if (!empty($this->id)):
        // lógica...
    endif;
}

// BIEN — fail fast real con throw
public function validateSomething(): void {
    if (empty($this->name)):
        throw new \Exception('Nombre requerido');
    endif;
    // lógica continúa con garantía
}

// BIEN — return aceptable después de múltiples throws
public function someAction(): void {
    if (empty($input)):
        throw new ControllerException('...', HTTP_400);
    endif;
    if (!$this->condition()):
        throw new ControllerException('...', HTTP_422);
    endif;
    // aquí un return temprano es aceptable
    // si evita anidamiento excesivo
}
```

### Aplica a

- Modelos (hooks before_save, validaciones)
- Controladores (acciones, métodos privados)
- Traits, helpers y servicios
- Todo el proyecto sin excepción
