# Convenciones de código — Komodo

## PHP

### Namespaces y archivos

| Capa | Namespace | Carpeta | Nombre de archivo |
|------|-----------|---------|-------------------|
| Controladores | `App\Controllers` | `app/controllers/` | `nombre_controller.php` |
| Modelos | `App\Models` | `app/models/` | `nombre_modelo.php` |
| Helpers | `App\Helpers` | `app/helpers/` | `Nombre_Helper.php` |
| Migraciones | `Migrations` | `migrations/` | `create_nombre_tabla.php` |
| Tests | `tests` | `tests/` | `testNombreClase.php` |

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

Usar type hints en todos los métodos públicos y protegidos. Las propiedades de modelo **reflejan el tipo real de la columna**: `?string` para texto, `?int` para enteros, IDs, timestamps y flags (0/1), `?float` para decimales. **Nunca `?bool`**: se usa `?int` con 0/1. El `generate model` del framework ya emite el tipo correcto.

```php
// Métodos — tipos específicos
public function indexAction(): void { }
public function login(string $email, string $password, int $level): int { }
public function _init_(): void { }
public function beforeEach(): void { }

// Propiedades de modelo — el tipo de la columna
public ?string $nombre = null;   // VARCHAR / TEXT
public ?int    $status = null;   // INTEGER, ids, timestamps, flags 0/1
public ?float  $factor = null;   // FLOAT / decimales

// Incorrecto
public ?string $status = null;   // si la columna es entero
public ?bool   $activo = null;   // nunca ?bool: ?int con 0/1
```

El tipo es una capa de seguridad contra datos inválidos del POST. Con asignación en bloque (`Niu`, constructor) un valor que no cabe **no lanza excepción**: se registra en `_error` (campo en `errFields()`), la propiedad queda sin asignar y `Save()` devuelve `false`. Reglas (comprobadas en el framework): `""` vale `0` (`0.0` en `?float`); `"5.0"`, `"100.00"`, `" 5"`, `"+5"`, `"007"` y `"1e3"` se aceptan en un `?int`; `"5.7"`, `"abc"`, `"0x1A"`, `".5"` y un entero que desborda 64 bits se rechazan en un `?int`; un arreglo se rechaza en cualquier tipo. Un `NULL` de la base se lee como `0` o `""`. Los mensajes por defecto salen en español y se cambian redeclarando `_typeMessages` en el cuerpo de la clase del modelo (solo las claves `int`, `float` o `string` que cambian).

Límite: la asignación directa de un valor inválido a una propiedad tipada (`$obj->qty = 'abc'`) sigue lanzando `TypeError`; la validación controlada solo cubre la asignación en bloque, que es la ruta del POST.

### Manejo de errores en controladores

```php
// Siempre este patrón para acciones JSON
// (al inicio del archivo: use Exception; — nunca \Exception en el cuerpo)
public function miAccionAction(): void {
    $this->layout = null;
    try {
        // lógica
    } catch (ControllerException $e) {
        $this->_code = $e->getCode();
        $this->_response['message'] = $e->getMessage();
    } catch (Exception $e) {
        $this->_code = HTTP_500;
        $this->_response['message'] = $e->getMessage();
    } finally {
        http_response_code($this->_code);
        $this->respondToAJAX(json_encode($this->_response));
    }
}
```

### Errores fuera de los controladores (semillas, tests, scripts)

**Nunca** `trigger_error(..., E_USER_ERROR)`: está obsoleto desde PHP 8.4, mata el proceso sin que nadie pueda atraparlo y oculta qué falló. Se lanza una excepción y se maneja con `try/catch`.

```php
// Semillas y tests: un Save() fallido lanza, con el mensaje real del modelo
// (al inicio del archivo: use Exception;)
$model->Save() or throw new Exception((string) $model->_error);

// Errores de negocio en un controlador: ControllerException con el código HTTP correcto
$model->Save() or throw new ControllerException((string) $model->_error, $code);

// INCORRECTO — nunca
$model->Save() or trigger_error($model->_error, E_USER_ERROR);
```

Scripts de línea de comandos: un `try/catch` de nivel superior que escriba el mensaje en STDERR y termine con código distinto de cero. La guardia `testErrorHandlingGuard` falla si aparece `E_USER_ERROR` en `app/`, `migrations/` o `tests/`.

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