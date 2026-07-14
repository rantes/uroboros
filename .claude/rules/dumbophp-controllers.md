# DumboPHP — Controladores

## Jerarquía de herencia

```
DumboPHP\Controller
    └── App\Controllers\MainController      (base del proyecto)
            └── App\Controllers\IndexController

```

## Estructura mínima de un controlador

```php
<?php
namespace App\Controllers;

use App\Controllers\MainController;
use App\Controllers\ControllerException;

class NombreController extends MainController {

    public function __construct() {
        parent::__construct();
    }

    public function indexAction(): void {
        // Asignar variables a la vista
        $this->titulo = 'Mi sección';
        $this->datos  = $this->MiModelo->Find_by_status(1);
    }
}
```

## Routing

La URL `/{controller}/{action}/{param1}/{param2}` se resuelve así:
- `controller` → clase `{Controller}Controller` en `app/controllers/`
- `action` → método `{action}Action()` en el controlador
- `params` → disponibles en `$this->params` (array indexado o por nombre)

Ejemplos:
- `/admin/index` → `AdminController::indexAction()`
- `/admin/users/edit/5` → `AdminController::landingAction()`, `$this->params = ['edit', '5']`
- `/guarda/pedestrianin/visitorid/3` → `GuardaController::pedestrianinAction()`, `$this->params['visitorid'] = 3`

La URL `/{controller}` redirige a `/controller/index` por defecto.
La URL `/{controller}/{action}?param1=1&param2=3` se resuelve así:
- `controller` → clase `{Controller}Controller` en `app/controllers/`
- `action` → método `{action}Action()` en el controlador
- `?param1=1&param2=3` → disponibles en `$this->params` (array indexado o por nombre), por ejemplo `$this->params['param1'] = 1` y `$this->params['param2'] = 3`.

## Routing — URLs en tests y código

```php
// CORRECTO — parámetros posicionales
$this->_runAction('/admin/guardpassword/5');
$this->_runAction('/guarda/pedestrianin/3');

// INCORRECTO — nunca nombre/valor en la URL
$this->_runAction('/admin/guardpassword/id/5');
$this->_runAction('/guarda/pedestrianin/visitorid/3');
```

Los parámetros con nombre solo existen en query string:

```php
// Query string — sí permite nombre=valor
$this->_runAction('/admin/users?id=5&status=1');
// Acceso: $this->params['id'] y $this->params['status']

// Segmentos de URL — siempre posicionales
$this->_runAction('/admin/users/5');
// Acceso: $this->params[0]
```

El patrón `/controller/action/nombre/valor` no existe en DumboPHP.

## Propiedades del controlador

```php
$this->layout     = 'layout';   // Nombre del layout (false = sin layout)
$this->render     = ['file' => 'admin/user_list.phtml']; // Vista específica
$this->render     = ['layout' => false];                 // Sin layout
$this->render     = ['text' => 'contenido directo'];     // Texto directo
$this->noTemplate = ['logout', 'save']; // Acciones sin vista
$this->params     = [];         // Parámetros de la URL
$this->paginate   = true;       // Activa paginación en el layout
$this->sectionTitle = 'Título'; // Título de la sección en el layout
```

## Respuestas

```php
// Respuesta JSON (AJAX)
$this->setResponseCode(HTTP_200);
$this->respondToAJAX(json_encode($this->_response));

// Redirección — siempre usar redirect(), nunca header() directamente
$this->redirect(INST_URI . 'admin/index');
$this->redirect(INST_URI . 'admin/login');

// Código HTTP
http_response_code(HTTP_201);
```

## Operadores de cortocircuito en controladores

Para asignaciones condicionales simples, usar cortocircuito en lugar de `if`:

```php
// Asignación de default
empty($this->params[0]) and ($this->params[0] = 'list');

// Encadenar operaciones tras Save()
$data->Save()
    and ($this->_response['d'] = $data)
    and ($this->_response['message'] = 'Creado')
    and ($this->_code = HTTP_201)
or throw new ControllerException((string) $data->_error, HTTP_422);
```

## Constantes HTTP disponibles (stub.php)

```php
HTTP_100  // Continue
HTTP_200  // OK
HTTP_201  // Created
HTTP_202  // Accepted
HTTP_204  // No Content
HTTP_300  // Multiple Choices
HTTP_400  // Bad Request
HTTP_401  // Unauthorized
HTTP_403  // Forbidden
HTTP_404  // Not Found
HTTP_405  // Method Not Allowed
HTTP_406  // Not Acceptable
HTTP_422  // Unprocessable Entity
HTTP_500  // Internal Server Error
HTTP_302  // (redirect, definido en el framework)
```

## Manejo de errores

Siempre usar `ControllerException` para errores controlados y capturar en bloque try/catch/finally:

```php
public function miAccionAction(): void {
    $this->layout = null;
    try {
        if (empty($this->params['id'])):
            throw new ControllerException('Parámetros insuficientes', HTTP_400);
        endif;

        $data = $this->MiModelo->Find((int) $this->params['id']);
        $this->_response['d'] = $data;

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

## Estructura `$this->_response`

```php
public array $_response = [
    'd'       => [],      // Datos de respuesta
    'message' => '',      // Mensaje para el cliente
];
```

## Before filter y autenticación

`MainController::before_filter()` se ejecuta antes de cada acción. Gestiona:
- Validación del token CSRF para POST/PUT/DELETE
- Verificación de sesión activa (`requireLogin()`)
- Carga del menú según el controlador

Para excluir acciones del filtro de login:
```php
$this->exceptsBeforeFilter = [
    'actions' => 'login,logout,signin,miAccionPublica',
];
```

## CSRF Token

El token se valida automáticamente en `before_filter()`. Para obtener un token nuevo:
```php
$token = $this->__xsfrToken();
// Se almacena en $_SESSION['xsfr_token']
// El cliente debe enviarlo en el header X-Sf-Token
```

## Acciones especiales heredadas de MainController

- `loginAction()`: Muestra el formulario de login (redirige si ya hay sesión)
- `signinAction()`: Procesa el POST de login, responde JSON
- `logoutAction()`: Destruye la sesión y redirige al login

## Helpers

Los helpers se cargan declarándolos en el constructor:
```php
$this->helper = ['Sessions', 'Menu', 'Tools'];
```

Los helpers viven en `app/helpers/` con el nombre `{Nombre}_Helper.php`.

## Tests de PUT — no requiere inspeccionar el framework

Para simular PUT en tests basta con:

$_SERVER['REQUEST_METHOD']  = 'PUT';
$_SERVER['HTTP_x-sf-token'] = 'token';
$_SESSION['xsfr_token']     = 'token';

$result = $this->_runAction('/controller/action/{id}');