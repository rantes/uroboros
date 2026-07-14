---
paths:
  - "tests/*.php"
---

# DumboPHP — Tests con Timothy

## Comandos de consola

```bash
# Ejecutar todos los tests
dumboTest all

# Ejecutar tests específicos (separados por espacio)
dumboTest testAdminController
dumboTest testUserModel testGuardModel testPropertyModel

# Con opciones
dumboTest all --halt=true     # detener en primer fallo
dumboTest all --watch=true    # re-ejecutar al guardar
dumboTest all --dir=tests/    # directorio personalizado
```

El nombre que se pasa es el nombre de la clase (sin `.php`).

---

## Archivos temporales de test
Cuando crees archivos de test temporales (testTmp*.php o similar) para
verificaciones empíricas, elimínalos automáticamente al terminar la
verificación sin pedir autorización adicional.

---

## Estructura de un archivo de test

Los tests viven en `tests/`. Un archivo por modelo o controlador.
El namespace es `tests\` (minúscula).

```php
<?php
namespace tests;

use DumboPHP\lib\Timothy\dumboTests;

class testNombreModelo extends dumboTests {

    /**
     * Se ejecuta antes de cada método de test.
     * Siempre resetear las tablas necesarias.
     */
    public function beforeEach(): void {
        $this->_migrateTables([
            'nombre_tabla',
            'tabla_relacionada',
        ]);
    }

    /**
     * Inicialización única de la clase (opcional).
     * Usar para instanciar controladores u objetos costosos.
     */
    public function _init_(): void {
        // $this->_controller = new MiController();
    }

    public function modelExistTest(): void {
        $this->describe('Should exist the Model');

        $obj = $this->NombreModelo->Niu();
        $this->assertFalse(empty($obj), 'Assert there is a model instance');
        $this->assertTrue(
            is_a($obj, 'DumboPHP\ActiveRecord'),
            'Assert the instance is an ActiveRecord'
        );
    }
}
```

## Convenciones de nomenclatura

- Archivo: `test{NombreModelo}.php` o `test{Nombre}Controller.php`
- Clase: `test{NombreModelo}` o `test{Nombre}Controller`
- Métodos de test: `camelCase` + sufijo `Test` (ej: `saveOkTest`, `signinActionTest`)
- Namespace: siempre `tests\` (minúscula)

## Métodos del ciclo de vida

```php
public function beforeEach(): void { }  // Antes de cada test
public function _init_(): void { }      // Una vez al inicializar la clase
public function _end_(): void { }       // Al finalizar todos los tests (opcional)
```

---

## Helpers de ciclo de vida de tablas

| Método | Acción | Cuándo usarlo |
| --- | --- | --- |
| `_migrateTables(['t'])` | down + up | Setup estándar en `beforeEach()` |
| `_createTables(['t'])` | solo up | Crear sin destruir datos existentes |
| `_dropTables(['t'])` | solo down | Probar errores 500 por tabla inexistente |
| `_truncateTables(['t'])` | vacía registros | Limpiar sin recrear estructura |
| `_sow()` | seeds completos | Poblar con todos los datos base |
| `_sow(['_metodo'])` | seed parcial | Poblar solo lo que el test necesita |

```php
// Setup estándar
public function beforeEach(): void {
    $this->_migrateTables(['count_accounts', 'movements']);
    $this->_sow(['_sowTestAccounts']);
}

// Probar error 500 ante tabla inexistente
public function serverErrorTest(): void {
    $this->describe('Should return 500 when table does not exist');

    $this->_dropTables(['count_accounts']);
    $result = $this->_runAction('/contabilidad/puc');
    $this->assertEquals(HTTP_500, (int) $result->_code);
    // beforeEach() del siguiente test restaura la tabla
}
```

---

## Reset de tablas en tests

`_migrateTables()` va ÚNICAMENTE en `beforeEach()`, nunca
dentro de métodos de test.

Los demás helpers sí pueden usarse dentro de métodos de test
cuando el caso lo requiera: `_truncateTables()`, `_dropTables()`,
`_createTables()` y `_sow()`.

---

## Assertions disponibles

```php
$this->assertEquals($expected, $actual, 'mensaje');
$this->assertTrue($condition, 'mensaje');
$this->assertFalse($condition, 'mensaje');
$this->assertNotEmpty($val, 'mensaje');
$this->assertNotFalse($val, 'mensaje');
$this->assertGreaterThan($min, $val, 'mensaje');
$this->assertArrayHasKey($key, $arr, 'mensaje');
$this->assertHasFields($this->NombreModelo);
$this->assertHasFieldTypes($this->NombreModelo);
```

## Describir el test

```php
$this->describe('Should do something specific');
```

---

## Acceso a modelos en tests

Los modelos están disponibles como propiedades mágicas — igual que en controladores.
No usar `require` ni `use` para modelos en tests.

```php
$this->User->Niu($data);
$this->AppUser->Find(1);
$this->Property->Find_by_property_id(0);
```

---

## Testar controladores

```php
public function _init_(): void {
    $this->_controller = new AdminController();
}

public function signinActionTest(): void {
    $this->describe('Should return 401 with no credentials');

    $_POST                      = null;
    $_SERVER['HTTP_X-Sf-Token'] = '123';
    $_SESSION['xsfr_token']     = '123';
    $_SERVER['REQUEST_METHOD']  = 'POST';

    $result = $this->_runAction('/admin/signin');

    $this->assertTrue($result->_code !== HTTP_404, 'Action should exist');
    $this->assertEquals(HTTP_401, (int) $result->_code, 'Should return 401');
}
```

> **Importante:** usar siempre `$result->_code` para verificar el código HTTP.
> Nunca usar `http_response_code()` — `ob_get_clean()` lo limpia y retorna `false`.

## `_runAction()`

```php
$result = $this->_runAction('/controller/action');
// $result->_code              → código HTTP de la respuesta
// $result->_rawOutput         → contenido del buffer de salida
// $result->_getController_()  → nombre del controlador ejecutado
// $result->_getAction_()      → nombre de la acción ejecutada
// $result->noTemplate         → array de acciones sin template
// $result->alguna_propiedad   → cualquier propiedad asignada en la acción
```

---

## Patrón completo — test de modelo

```php
public function saveOkTest(): void {
    $this->describe('Should save without errors');

    $data = [
        'campo1'     => 'valor1',
        'campo2'     => 'valor2',
        'created_at' => 0,
        'updated_at' => 0,
    ];

    $obj    = $this->MiModelo->Niu($data);
    $result = $obj->Save();
    $errors = $obj->_error->errFields();

    $this->assertEquals(0, sizeof($errors), 'Should have no errors');
    $this->assertTrue($result, 'Save should return true');
}

public function triggerValidationErrorsTest(): void {
    $this->describe('Should fail validation with empty data');

    $obj    = $this->MiModelo->Niu();
    $result = $obj->Save();
    $errors = $obj->_error->errFields();

    $this->assertFalse($result, 'Save should return false');
    $this->assertTrue(sizeof($errors) > 0, 'Should have validation errors');
    $this->assertTrue(
        in_array('campo_requerido', $errors),
        'campo_requerido should be in errors'
    );
}
```

## Patrón completo — test de controlador

```php
public function indexReturns200Test(): void {
    $this->describe('Should return 200 on index');

    $_SERVER['REQUEST_METHOD'] = 'GET';

    $result = $this->_runAction('/admin/index');
    $this->assertEquals(HTTP_200, (int) $result->_code);
    $this->assertNotEmpty($result->_rawOutput);
}

public function serverErrorOnMissingTableTest(): void {
    $this->describe('Should return 500 when table is missing');

    $this->_dropTables(['mi_tabla']);
    $result = $this->_runAction('/admin/landing');
    $this->assertEquals(HTTP_500, (int) $result->_code);
}
```

---

## Tests de tablas (integración)

El archivo `tests/testTables.php` verifica que todas las migraciones son correctas.
Al agregar un nuevo modelo, añadir sus assertions aquí.

```php
public function migrationsTest(): void {
    $this->describe('Verifying Fields');
    $this->assertHasFields($this->MiModelo);
    $this->assertHasFieldTypes($this->MiModelo);
}

public function relationsTest(): void {
    $this->describe('Verifying object relations');
    $this->assertTrue(
        in_array('tabla_relacionada', $this->MiModelo->has_many),
        'Verify has_many relation'
    );
}
```

---

## Estrategia de cobertura

Solo requieren tests unitarios directos:

- `app/models/` — un archivo `tests/test{Nombre}Model.php` por modelo
- `app/controllers/` — un archivo `tests/test{Nombre}Controller.php` por controlador

Los helpers en `app/helpers/` **no tienen tests unitarios propios**.
Su cobertura se obtiene indirectamente al testear los controladores que los usan.
Nunca crear archivos de test específicos para helpers.

---

## Tests existentes

| Archivo | Cubre |
| --- | --- |
| `testTables.php` | Migraciones y relaciones de todas las tablas |
| `testAppUserModel.php` | Modelo `AppUser` (auth) |

---

## Salida y reportes

- Consola: `P` (passed) o `F` (failed) por cada aserción, en verde/rojo
- Exit code: `0` si todos pasan, número de fallos si hay errores
- `test-result.xml` — reporte JUnit compatible con CI/CD
- `coverage.xml` — reporte de cobertura Clover (requiere XDebug)
- `tmp/logs/unit_testing.log` — log detallado de cada aserción

## Entorno de test

- El entorno se fuerza a `test` automáticamente
- La BD usa la configuración `test` de `config/db_settings.php` (SQLite en memoria)
- Los tests son aislados de producción por diseño
