---
name: dumbophp-architect
description: >
  Actúa como arquitecto de DumboPHP. Usa este skill cuando el usuario pida crear,
  modificar o revisar cualquier artefacto de un proyecto DumboPHP: modelos,
  controladores, vistas, migraciones, seeds, tests (Timothy) o scaffolds.
  También aplica cuando el usuario mencione ActiveRecord, dumboTests, UIBuilder,
  dumbo CLI, o pregunte sobre la estructura de un proyecto DumboPHP.
keywords:
  - dumbophp
  - dumbo
  - activerecord
  - timothy
  - dumboTests
  - scaffold
  - migration
  - mvc
  - controller
  - model
  - phtml
  - uibuilder
---

Eres el arquitecto de referencia de DumboPHP. Tu trabajo es producir código
correcto para este framework desde el primer intento, sin inventar patrones
externos ni introducir dependencias que el framework no necesita.

Antes de escribir cualquier artefacto, lee el código fuente relevante si no
lo tienes en contexto. Los archivos canónicos son:

- `lib/Timothy/dumboTests.php` — clase base de tests, todas las aserciones reales
- `lib/Timothy/testDispatcher.php` — runner de suites
- `lib/DumboGeneratorClass.php` — lógica exacta del generador de scaffold/model/migration
- `lib/Shell.php` — comandos disponibles en el CLI
- `lib/dumbojs/uibuilder.php` — builder de assets UI (sass, directives, factories)

---

## ENTENDIMIENTO DEL FRAMEWORK

DumboPHP es un framework MVC PHP 8.1+ inspirado en Rails. Su filosofía:
**el framework desaparece**. El desarrollador escribe dominio, no plumbing.

### Principios no negociables

1. **Lazy Model Load** — Los modelos se acceden como `$this->ModelName` en el
   controlador. El `__get()` del Controller los instancia automáticamente.
   Nunca `new \App\Models\Product()` dentro de un controlador.

2. **`_init_()` en lugar de `__construct()`** — Tanto en modelos como en
   controladores. Sobreescribir `__construct()` rompe la inicialización del
   framework.

3. **Convención sobre configuración** — No existe router configurable. La URL
   `/product/show/5` mapea a `ProductController::showAction()` con
   `$this->params['id'] = 5`. No crear archivos de rutas.

4. **Sin dependencias en runtime** — El framework funciona standalone. No
   agregar paquetes Composer salvo dependencias de desarrollo.

5. **Propiedades de modelo tipadas según el campo** — Cada propiedad declara
   el tipo PHP que corresponde al tipo de columna en BD: `?string` para
   VARCHAR/TEXT, `?int` para INTEGER/BIGINT, `?float` para FLOAT/DECIMAL,
   `?bool` para booleanos. El scaffold genera el tipo correcto automáticamente.
   Siempre nullable (`?tipo`) con valor por defecto `null`.

---

## ROUTING

```
/controller/action/param1/param2
```

- Defaults en `config/host.php`: `DEF_CONTROLLER` y `DEF_ACTION`.
- El `.htaccess` pasa `?url=controller/action/...` al `index.php`.
- `USE_ALTER_URL` habilita routing alternativo (raro, no usar por defecto).

---

## CONTROLADORES

**Ubicación**: `app/controllers/<singular_snake_case>_controller.php`  
**Clase**: `<CamelCase>Controller extends Controller`  
**Namespace**: `App\Controllers`

```php
<?php
namespace App\Controllers;
use DumboPHP\Controller;

class ProductController extends Controller {

    public function indexAction(): void {
        $this->data  = $this->Product->Find();
        $this->title = 'Lista de productos';
    }

    public function showAction(): void {
        $this->data = $this->Product->Find($this->params['id']);
    }

    public function addeditAction(): void {
        isset($this->params['id'])
            ? ($this->data = $this->Product->Find($this->params['id']))
            : ($this->data = $this->Product->Niu());
    }

    public function createAction(): void {
        $this->noTemplate = ['create'];
        if (isset($_POST['product'])):
            $obj = $this->Product->Niu($_POST['product']);
            $obj->Save() or die($obj->_error);
        endif;
        $this->redirect(INST_URI.'product/index/');
    }

    public function deleteAction(): void {
        $this->noTemplate = ['delete'];
        isset($this->params['id']) and $this->Product->Delete($this->params['id']);
        $this->redirect(INST_URI.'product/index/');
    }
}
```

### Reglas de controladores

- Cada acción: sufijo `Action`. Prefijo libre: `indexAction`, `addEditAction`.
- `$this->params` — parámetros de URL y GET.
- Cualquier `$this->propiedad` asignada en la acción es accesible en la vista.
- `$this->layout` — layout a usar (`null` lo desactiva).
- `$this->noTemplate` — array de nombres de acción sin sufijo que no renderizan vista.
- Redirigir siempre con `$this->redirect(INST_URI.'controller/action/')`.
  Nunca `header()` directo.

**`noTemplate` vs. `layout` — dos mecanismos reales e independientes,
verificado contra `dumbophp.php` completo (no solo dentro de
`parseContent()`).** `$this->noTemplate` SÍ tiene efecto real
(`dumbophp.php:2592`, `if (!empty($view) && $renderPage)` — controla
si se incluye la vista propia de la acción). Lo que NO hace es
afectar el layout general, que se controla por separado y
exclusivamente vía `$this->layout` (`dumbophp.php:2600`, bloque
independiente que no consulta `$renderPage` en absoluto). No usar uno
esperando que controle al otro — para suprimir tanto la vista de la
acción como el layout hace falta tocar ambas propiedades.
- El nombre del array POST coincide con el singular snake_case del modelo:
  `$_POST['product']`, `$_POST['user_profile']`.

---

## MODELOS (Active Record)

**Ubicación**: `app/models/<singular_snake_case>.php`  
**Clase**: `<CamelCase> extends ActiveRecord`  
**Namespace**: `App\Models`

El nombre de la tabla en BD se deriva automáticamente: `Product` → `products`,
`UserProfile` → `user_profiles`.

```php
<?php
namespace App\Models;
use DumboPHP\ActiveRecord;

class Product extends ActiveRecord {
    public ?string $name        = null;  // VARCHAR/TEXT  → ?string
    public ?string $description = null;  // TEXT          → ?string
    public ?float  $price       = null;  // FLOAT/DECIMAL → ?float
    public ?int    $stock       = null;  // INTEGER       → ?int
    public ?int    $category_id = null;  // INTEGER (FK)  → ?int

    public function _init_(): void {
        $this->validates_presence_of('name');

        $this->has_many   = ['comments'];
        $this->belongs_to = ['category'];
    }
}
```

### Tipos PHP por tipo de columna

| Tipo en migración      | Tipo PHP de la propiedad |
|------------------------|--------------------------|
| `VARCHAR`, `TEXT`      | `?string`                |
| `INTEGER`, `BIGINT`    | `?int`                   |
| `FLOAT`, `DECIMAL`     | `?float`                 |
| Booleano               | `?bool`                  |

Siempre nullable (`?tipo`) con valor por defecto `null`.

> **Verificado contra el código fuente real** (`DumboGeneratorClass::model()`,
> `/etc/dumbophp/lib/DumboGeneratorClass.php`): el generador **no** produce
> el tipo correcto automáticamente — declara TODOS los campos como
> `public ?string $campo = null;` sin importar el tipo pasado en el CLI
> (`integer`, `float`, etc.). Revisar y retipear a mano (`?int`, `?float`)
> cada propiedad generada según la convención de esta tabla antes de dar
> el modelo por terminado.

### API del Active Record

```php
// Consultas
$this->Product->Find();                          // todos
$this->Product->Find(5);                         // por id → objeto único
$this->Product->Find('1,2,3');                   // por ids múltiples
$this->Product->Find([':first']);                // primero
$this->Product->Find([
    'conditions' => "price > 100 AND stock > 0",
    'sort'       => 'name ASC',
    'limit'      => 10,
    'fields'     => 'products.*, categories.name AS cat_name',
    'join'       => 'INNER JOIN categories ON categories.id = products.category_id',
    'group'      => 'category_id',
]);

// Crear / actualizar
$obj = $this->Product->Niu();           // nuevo vacío
$obj = $this->Product->Niu($_POST['product']); // nuevo con datos
$obj->name  = 'Widget';
$saved = $obj->Save();                  // INSERT o UPDATE según tenga id
$saved or die($obj->_error);

// Update directo sobre tabla (sin cargar objeto)
$this->Product->Update([
    'conditions' => '`id`=5',
    'data'       => ['price' => '12.99', 'stock' => '50'],
]);

// Eliminar
$this->Product->Delete(5);

// Relaciones (desde objeto resultado)
$product   = $this->Product->Find(5);
$comments  = $product->Comments();     // has_many
$category  = $product->Category();     // belongs_to
```

### `id`, `created_at`, `updated_at` — no declararlos en el modelo, pero SÍ pasarlos al generador

`ActiveRecord` ya declara `public ?int $id = 0;`, `created_at` y
`updated_at` en la clase base — el `.php` del modelo nunca debe
redeclararlos.

Pero la MIGRACIÓN sí necesita esos 3 campos explícitos en el comando
`dumbo generate model`/`scaffold` — `Create_Table()` (y cada driver de
BD, ej. `sqlite.php:CreateTable()`) construye la tabla únicamente a
partir de los campos recibidos, sin inyectar ninguno por su cuenta. Sin
pasarlos, la tabla se crea sin esas columnas.

**Bug real confirmado en `DumboGeneratorClass::model()`** (no en
`migration()`): el filtro que debería excluirlos del `.php` generado
está roto —

```php
$noSet = ['id','created_at', 'updated_at'];
...
if (!in_array($field, $noSet)): // $field es un FieldObject, no un string
```

`$field` es un objeto `FieldObject`, no el string plano que trae
`$noSet`. `in_array()` usa comparación floja (`==`); como `FieldObject`
define `__toString()` (devuelve el literal completo del array de
definición, ej. `"['field'=>'id', 'type'=>'INTEGER', ...]"`), esa
cadena nunca coincide con `'id'`/`'created_at'`/`'updated_at'` sueltos
— el filtro nunca excluye nada.

**Procedimiento real a seguir** (verificado y aplicado en el spec
`contratos` de Basilisk):
1. Generar el modelo pasando los 3 campos igual (la migración los
   necesita).
2. Borrar a mano las 3 líneas `public ?string $id/$created_at/$updated_at = null;`
   que el generador deja en el `.php` — quedan mal tipadas y pisan las
   declaraciones correctas heredadas de `ActiveRecord`.
3. No parchear el generador compartido (`/etc/dumbophp`) sin agotar
   antes esta solución en la capa de aplicación — es un bug de un
   framework usado por otros proyectos.

### Hidratación de columnas numéricas NULL — se convierten en `0`, y `Save()` las persiste así

> **Bug real confirmado**, tres sitios redundantes en
> `/etc/dumbophp/bin/dumbophp.php`, todos con la misma coerción
> `empty($valor) ? 0 : $valor` (PHP trata `null` como `empty()`):
>
> - `ActiveRecord::__construct()`, líneas 1053-1059 (loop sobre
>   `$fields` recibidos) y líneas 1060-1067 (loop sobre `$colmeta`,
>   que sí corre cuando el objeto viene de una fila real de BD vía
>   `getData()`, con `in_array(strtoupper($col[1]), $willCast)` como
>   guardia — cualquier tipo numérico: `INT`, `INTEGER`, `BIGINT`,
>   `FLOAT`, etc.).
> - `ActiveRecord::getData()`, líneas 1208-1216 — post-procesamiento
>   adicional para el caso de un solo resultado (`Find($id)` o
>   cualquier `Find()` que matchee exactamente 1 fila), mismo patrón
>   `empty(...) ? 0 : ...` seguido de `1 * $val`.
>
> Consecuencia: **cualquier columna numérica NULL en la BD se
> convierte en `0` en el objeto PHP en el momento de cargarlo con
> `Find()`** — la distinción NULL/0 desaparece por completo al
> hidratar, sin importar el tipo declarado en el modelo (`?int`,
> `?float`).
>
> Esto por sí solo sería solo un problema de lectura, pero se vuelve
> un problema de escritura real por cómo `Save()` decide qué columnas
> incluir en el `UPDATE` (línea 1640):
> ```php
> $field !== $this->pk && isset($this->{$field}) && $field !== 'created_at' && ($data[$field] = $this->{$field});
> ```
> `isset($this->{$field})` es `true` para `0` (a diferencia de
> `null`) — así que **cualquier columna numérica que era NULL en BD,
> una vez hidratada como `0`, queda incluida en el próximo `UPDATE` y
> se persiste como `0` para siempre**, aunque el código nunca haya
> tocado esa columna a propósito.
>
> **Patrón que dispara el bug — exactamente el que documenta este
> mismo archivo más arriba (sección "API del Active Record") y
> `dumbophp-models.md`/`03-active-record.md` como la forma canónica de
> actualizar un registro:**
> ```php
> $obj = $this->Modelo->Find(5);   // columna numérica nullable → 0
> $obj->otro_campo = 'x';
> $obj->Save();                    // persiste la columna nullable como 0
> ```
> Confirmado empíricamente en Basilisk (spec de refactor de modelo de
> datos, `Contract.contract_id` — FK nullable de auto-referencia):
> backfillear `contract_uuid` sobre el contrato piloto (`Find()` +
> mutar `contract_uuid` + `Save()`) dejó `contract_id` en `0` en vez
> de `NULL`, rompiendo la semántica documentada en
> `.claude/rules/modelo-datos.md` ("`null` = contrato original").
>
> **Patrón seguro a usar en su lugar** cuando el objetivo es tocar
> solo una o pocas columnas de una fila ya existente, sin re-hidratar
> ni re-persistir el resto: el `Update()` de nivel de clase (no el de
> instancia recién cargada), que arma el `UPDATE` directo desde
> `$params['data']` sin pasar por el ciclo de hidratación:
> ```php
> $this->Modelo->Update([
>     'conditions' => "`id`='{$id}'",
>     'data'       => ['otro_campo' => 'x'],
> ]);
> ```
> **Ojo:** ese `Update()` no ejecuta `before_save`/`before_update` ni
> validaciones — úsalo solo para el caso puntual de tocar columnas
> puntuales sin disparar el ciclo de vida completo (ej. scripts de
> backfill), no como reemplazo general de `Find()`+`Save()` para
> flujos de negocio normales.
>
> El patrón `Niu($data)->Save()` (construir un objeto NUEVO desde un
> array plano, ej. desde `$_POST`, sin pasar por `Find()`) **no está
> afectado** — el loop de coerción por `$colmeta` sólo corre cuando el
> objeto se construye desde una fila real de BD (`getData()` pasa
> `$colmeta`; `Niu()`/`__construct()` con datos planos no). Es el
> patrón que ya usa `AdminController::saveAction()` en Basilisk, así
> que el CRUD genérico del admin no está expuesto a este bug hoy —
> pero cualquier código nuevo que haga `Find()` + mutar un campo +
> `Save()` sobre un modelo con columnas numéricas nullable sí lo está.
> No parchear el framework compartido sin agotar antes esta solución
> en la capa de aplicación.

---

## VISTAS

**Ubicación**: `app/views/<controlador_singular>/<accion>.phtml`  
**Sintaxis**: PHP corto (`<? ?>` y `<?= ?>`)  
**Siempre** sintaxis alternativa en estructuras de control:

```php
<? foreach($this->data as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?= $row->price; ?></td>
        <td>
            <a href="<?=INST_URI;?>product/addedit/<?=$row->id;?>">Editar</a>
            <a href="<?=INST_URI;?>product/delete/<?=$row->id;?>">Eliminar</a>
        </td>
    </tr>
<? endforeach; ?>

<? if (!empty($this->data)): ?>
    <p>Hay registros</p>
<? else: ?>
    <p>Sin resultados</p>
<? endif; ?>
```

### Layout

```php
<!-- app/views/layout.phtml -->
<!doctype html>
<html>
<head>
    <base href="<?=INST_URI;?>">
    <title><?= $this->title ?? 'Mi App'; ?></title>
</head>
<body>
    <?=$this->yield;?>
</body>
</html>
```

### Seguridad en vistas

- Siempre `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` para datos de usuario.
- Campos numéricos que el ORM castea (`id`, `price`) pueden salir sin escapar.
- **Nunca** lógica de negocio en la vista. Datos vienen del controlador.

---

## MIGRACIONES

**Ubicación**: `migrations/create_<tabla_plural>.php`
**Clase**: `Create<TablaPlural> extends Migrations`
**Namespace**: `Migrations`

```php
<?php
namespace Migrations;
use DumboPHP\Migrations;

class CreateProducts extends Migrations {
    public function _init_(): void {
        $this->_fields = [
            ['field'=>'id',          'type'=>'INTEGER', 'null'=>'false', 'limit'=>'11', 'primary'=>true, 'autoincrement'=>true],
            ['field'=>'name',        'type'=>'VARCHAR',  'null'=>'false', 'limit'=>'255', 'default'=>''],
            ['field'=>'description', 'type'=>'TEXT',     'null'=>'true'],
            ['field'=>'price',       'type'=>'FLOAT',    'null'=>'false', 'default'=>'0'],
            ['field'=>'stock',       'type'=>'INTEGER',  'null'=>'false', 'limit'=>'11', 'default'=>'0'],
            ['field'=>'category_id', 'type'=>'INTEGER',  'null'=>'false', 'limit'=>'11', 'default'=>'0'],
        ];
    }

    public function up(): void   { $this->Create_Table(); }
    public function down(): void { $this->Drop_Table(); }
}
```

### Tipos de campo

| CLI          | Migración  | SQL generado    |
|--------------|------------|-----------------|
| `primary`    | `INTEGER`  | `INTEGER(11) PK AUTOINCREMENT` |
| `integer`    | `INTEGER`  | `INTEGER(11)`   |
| `biginteger` | `BIGINT`   | `BIGINT`        |
| `string`     | `VARCHAR`  | `VARCHAR(255)`  |
| `text`       | `TEXT`     | `TEXT`          |
| `float`      | `FLOAT`    | `FLOAT`         |
| `decimal`    | `FLOAT`    | `FLOAT`         |

### Modificadores de campo CLI

```bash
nombre:string{100}           # tamaño personalizado
descripcion:text:null        # permite NULL
stock:integer:default{0}     # valor por defecto
```

### Métodos disponibles en `Migrations`

```php
$this->Create_Table();
$this->Drop_Table();
$this->Add_Column(['field'=>'x', 'type'=>'Y']);
$this->Alter_Column(['field'=>'x', 'type'=>'Y']);
$this->Remove_Column('nombre_columna');
$this->Add_Index('nombre_idx', 'campo');
$this->Add_Single_Index('campo');
$this->Remove_Index('nombre_idx');
$this->Add_Primary_Key('campo');
```

### `Add_Column()` — roto para los tres drivers (mysql/postgresql/sqlite), no usar

> **Bug real confirmado** en `Migrations::Add_Column()`
> (`/etc/dumbophp/bin/dumbophp.php:2770-2779`):
>
> ```php
> protected function Add_Column(array $params): void {
>     $query = DB->driver->validateField($this->_table, $params['field']);
>     $res   = DB->driver->validateField($query, $params['field']);
>
>     if ($res < 1) {
>         $query = DB->driver->AddColumn($this->_table, $params);
>         $this->_runQuery($query);
>     }
> }
> ```
>
> Llama `validateField($table, $field)` con **2 argumentos**. Los tres
> drivers reales declaran la firma con **3** argumentos obligatorios,
> sin default para el tercero:
> - `/etc/dumbophp/lib/db_drivers/mysql.php:206`
> - `/etc/dumbophp/lib/db_drivers/postgresql.php:315`
> - `/etc/dumbophp/lib/db_drivers/sqlite.php:276`
>
> las tres como `public function validateField(string $table, string $field, string $schema): string`.
> Cualquier llamada a `Add_Column()`, contra cualquier driver, lanza
> `ArgumentCountError: Too few arguments to function
> ...::validateField(), 2 passed ... and exactly 3 expected` de forma
> inmediata — **no es específico de MySQL**, se confirmó contra los
> tres archivos fuente, ninguno tiene valor por defecto para
> `$schema`. Confirmado empíricamente además contra MySQL real
> (proyecto Basilisk, `dumbo migration up products`, 2026-08-27).
>
> Aun si se corrigiera el conteo de argumentos, la segunda línea
> (`$res = DB->driver->validateField($query, ...)`) sigue siendo
> incorrecta: pasa el string SQL ya construido (`$query`) como si
> fuera el nombre de tabla, en vez de ejecutar la query con
> `_fetchQuery()` y leer el conteo del resultado (compárese con el
> patrón correcto que sí usa `Add_Single_Index()`/`Add_Index()` unas
> líneas más abajo en el mismo archivo, que sí llaman `_fetchQuery()`
> y leen `$indexres[0]['...']`). El bug no es solo un argumento
> faltante, la lógica de la función está rota de raíz.
>
> **Consecuencia práctica:** ninguna migración de este framework puede
> usar `Add_Column()` para evolucionar una tabla existente — ni contra
> una BD real (crashea igual) ni en tests (`_migrateTables()` también
> ejecuta `up()`, mismo crash).
>
> **Si el ambiente es puro desarrollo, sin datos reales que
> preservar** (caso Basilisk, spec de refactor de modelo de datos): el
> camino más simple es agregar el campo nuevo directamente al array de
> `$this->_fields` (nunca como `Add_Column()` aparte) y correr
> `dumbo migration reset <tabla>`/`reset all` — recrea la tabla desde
> cero con el esquema final, sin pasar por `Add_Column()` en absoluto.
> Es la opción correcta mientras no haya nada que preservar.
>
> **Si la tabla ya tiene datos reales que no se pueden perder** (un
> ambiente que no se puede simplemente resetear), `Add_Column()` sigue
> sin poder usarse — el workaround real a seguir:
> 1. `up()` se queda solo con `Create_Table()` — correcto para
>    SQLite/tests, que siempre recrean la tabla desde cero
>    (`_migrateTables()` hace down+up), así que toma el campo nuevo
>    directamente de `$this->_fields`.
> 2. Dejar el intento real de `Add_Column()` **comentado, no
>    borrado**, dentro de `up()`, con una nota explicando el bug y
>    remitiendo a este archivo.
> 3. Crear un archivo companion `migrations/sql/<descripción>.sql`
>    junto a la migración, con el `ALTER TABLE` exacto a correr contra
>    el ambiente real que no se puede resetear — instrucciones
>    reproducibles, aunque sea un paso manual.
> 4. No parchear el framework compartido (`/etc/dumbophp`) sin agotar
>    antes esta solución en la capa de aplicación — mismo criterio que
>    el bug del generador de arriba.

---

## SEEDS

**Ubicación**: `migrations/seeds.php`
**Clase**: `Seeds extends Controller`
**Namespace**: `Migrations`

```php
<?php
namespace Migrations;
use DumboPHP\Controller;

class Seeds extends Controller {
    private function _sowProduct() {
        $product = $this->Product->Find_by_name('Sample Product');
        if ($product->counter() === 0):
            $product = $this->Product->Niu();
            $product->name        = 'Sample Product';
            $product->price       = '9.99';
            $product->Save();
        endif;
    }
    private function _sowProductTest() {
        $cat = $this->Category->Niu();
        $cat->name = 'Electronics';
        $cat->Save();

        $product = $this->Product->Niu();
        $product->name        = 'Sample Product';
        $product->price       = '9.99';
        $product->category_id = $cat->id;
        $product->Save();
    }
    public function sow(?array $actions = []): void {
        $defaults = [
            '_sowProduct'
        ];
        $actions = empty($actions) ? $defaults : $actions;
        foreach ($actions as $action):
            $this->{$action}();
        endforeach;
    }
}
```

---

## CLI (dumbo)

```bash
# Proyecto nuevo
dumbo create <nombre-proyecto>

# Scaffold completo (modelo + migración + controlador + vistas CRUD)
dumbo generate scaffold products name:string price:float stock:integer:default{0}

# Solo modelo con migración
dumbo generate model user_profiles first_name:string email:string{100} bio:text:null

# Controlador con acciones
dumbo generate controller dashboard stats reports

# Seeds vacío
dumbo generate seed

# Migraciones
dumbo migration up <tabla>
dumbo migration down <tabla>
dumbo migration reset <tabla>
dumbo migration up all
dumbo migration sow

# Destroy
dumbo destroy scaffold <tabla>
dumbo destroy model <tabla>
dumbo destroy controller <nombre>

# DB
dumbo db dump <modelo>
dumbo db load <modelo>

# Ejecutar acción desde CLI
dumbo run product/index
dumbo run product/show id=5

# Opciones globales
--env=test  --halt=true  --standalone=true  --watch=true
```

---

## TESTING CON TIMOTHY

### Estructura del test

**Ubicación**: `tests/test<NombreClase>.php`
**Clase**: `test<NombreClase> extends dumboTests`
**Namespace**: `tests`

Los métodos de test deben terminar en `Test`.

```php
<?php
namespace tests;
use DumboPHP\lib\Timothy\dumboTests;

class testProductModel extends dumboTests {

    // Una vez al inicio de la suite
    public function _init_(): void {
        $this->_migrateTables(['products', 'categories']);
        $this->_sow(['_product_test']);
    }

    // Antes de CADA test (automático: resetSuperglobals + limpia _spyCalls)
    public function beforeEach(): void { }

    // Una vez al final
    public function _end_(): void { }

    public function createProductTest(): void {
        $this->describe('Debe crear un producto correctamente');
        $product = $this->Product->Niu();
        $product->name  = 'Widget';
        $product->price = '9.99';
        $this->assertTrue($product->Save());
        $this->assertNotEmpty($product->id);
    }

    public function findProductTest(): void {
        $this->describe('Find() retorna resultados');
        $products = $this->Product->Find();
        $this->assertNotEmpty($products);
        $this->assertGreaterThan(0, count($products));
    }

    public function validationTest(): void {
        $this->describe('Save() falla sin campos requeridos');
        $product = $this->Product->Niu();
        $this->assertFalse($product->Save());
    }

    public function controllerActionTest(): void {
        $this->describe('La acción index renderiza');
        $page = $this->_runAction('product/index');
        $this->assertNotEmpty($page->_rawOutput);
    }
}
```

### Aserciones

| Método | Descripción |
|--------|-------------|
| `assertEquals($a, $b, $msg?)` | Compara con `===` |
| `assertTrue($val, $msg?)` | Exactamente `true` |
| `assertFalse($val, $msg?)` | Exactamente `false` |
| `assertNotEmpty($val)` | No vacío |
| `assertNotFalse($val)` | No `false` |
| `assertGreaterThan($min, $val)` | `$val > $min` |
| `assertArrayHasKey($key, $arr)` | Clave existe en array |
| `assertHasFields($model)` | Modelo tiene campos de migración |
| `assertHasFieldTypes($model)` | Tipos coinciden con la BD |
| `assertMethodHasBeenCalled($method, $times?)` | Método llamado N veces |
| `assertMethodCalledWith($method, $args)` | Método llamado con args exactos |
| `describe($msg)` | Documenta el test en el log |

### Helpers de test

```php
// Resetear tablas (down + up)
$this->_migrateTables(['products', 'categories']);

// Solo crear tablas (up)
$this->_createTables(['products']);

// Solo eliminar tablas (down)
$this->_dropTables(['products']);

// Truncar sin recrear
$this->_truncateTables(['products']);

// Ejecutar seeds
$this->_sow();
$this->_sow(['only_users']); // opcional: acciones específicas

// Simular petición HTTP
$page = $this->_runAction('product/index');
$page = $this->_runAction('product/show?id=1');
$this->assertNotEmpty($page->_rawOutput);

// Invocar método privado/protegido
$result = $this->invokeMethod($this->Product, '_validateEmail', ['test@example.com']);

// Modificar sysConfig en tests
$this->setSysconfigValue($this->Product, 'some_key', 'test_value');
```

### Spies, stubs y mocks

```php
// Spy: registra llamadas, preserva comportamiento original
$obj = $this->Product->Find(1);
$this->spyOn($obj);
$obj->Save();
$this->assertMethodHasBeenCalled('Save', 1);

// Spy en método específico
$this->spyOn($obj, 'Save');

// Stub: reemplaza retorno de un método, registra llamadas
$this->stubMethod($obj, 'Save', true);

// Mock: objeto duck-typed sin clase real
$mock = $this->createMock('Mailer', ['send' => true]);
$mock->send(['to' => 'a@b.com']);
$this->assertMethodHasBeenCalled('send', 1);
$this->assertMethodCalledWith('send', [['to' => 'a@b.com']]);
```

### testDispatcher (runner automático)

El dispatcher:
1. Llama `_init_()` una vez al inicio.
2. Antes de cada test: `resetSuperglobals()` + limpia `$_spyCalls` + `beforeEach()`.
3. Ejecuta el test.
4. Al final: `_end_()` + `_summary()`.

### Ejecutar tests

```bash
dumboTest all
dumboTest testProductModel
dumboTest testProductModel testProductController testCategoryModel
dumboTest all --halt=true
dumboTest all --dir=tests/suites/
dumboTest all --watch=true
```

Genera: `test-result.xml` (JUnit), `coverage.xml` (Clover), `tmp/logs/unit_testing.log`.

### Entorno de test

- `$GLOBALS['env'] = 'test'` forzado automáticamente.
- `config/db_settings.php` debe tener entrada `test` con SQLite en memoria:
  ```php
  'test' => ['driver' => 'sqlite', 'schema' => 'memory']
  ```

---

## UIBUILDER (dumbojs)

**Ubicación**: `lib/dumbojs/uibuilder.php`
Clase PHP que compila assets del frontend. Ejecutada por el CLI o desde el propio framework.

### Estructura de archivos esperada

```
ui-sources/
  styles.scss              ← SCSS raíz (importado primero)
  base-sass/               ← partials SCSS base
  components/
    <name>.directive.js    ← directivas (no *.spec.js)
    <name>.factory.js      ← factories (no *.spec.js)
    <name>.spec.js         ← specs Jasmine
    <name>.scss            ← estilos de componente
  libs/
    dumbo.min.js
    dmb-components.min.js
    dmb-factories.min.js
    dmb-styles.css
    jasmine.js / jasmine-html.js / jasmine-boot.js / jasmine.css
```

### Métodos de UIBuilder

| Método | Acción |
|--------|--------|
| `sass()` | Compila SCSS → `app/webroot/css/styles.css` |
| `setlibs()` | Copia `ui-sources/libs/` → `app/webroot/libs/` |
| `buildDirectives()` | Concatena `*.directive.js` → `app/webroot/js/components.min.js` |
| `buildFactories()` | Concatena `*.factory.js` con resolución de dependencias → `app/webroot/js/factories.min.js` |
| `setspecs()` | Concatena `*.spec.js` → `app/webroot/js/specs.min.js` |
| `buildUI()` | `setlibs` + `sass` + `buildFactories` + `buildDirectives` |
| `setTestPage()` | `buildUI` + `setspecs` → genera `app/webroot/test.html` inline |
| `testUI()` | Ejecuta Jasmine specs en Chrome headless, loguea resultado |
| `watchUI()` | Watcher: reconstruye y re-testea al detectar cambios en archivos |

### Notas de implementación

- `buildFactories()` resuelve orden de carga: si una factory `extends` otra,
  la clase padre se incluye primero (inspecciona `class X extends Y` vía regex).
- `_cleanJS()` elimina comentarios `//` y `/* */` y colapsa whitespace.
- `_readFiles($path, $pattern, $goUnder)` busca en primer y segundo nivel de
  subdirectorios (`$goUnder = true` por defecto).
- El log de UIBuilder va a `tmp/logs/dumbo_ui_unit_testing.log`.

---

## CONVENCIONES DE CÓDIGO

### Operadores de cortocircuito para lógica simple

```php
// Correcto
empty($this->tblName) and $this->setNames($params[0]);
file_exists($path) or die('Archivo no encontrado.');
$replace && ($action = 'REPLACE');

// Evitar para if simples
if (empty($this->tblName)) { $this->setNames($params[0]); }
```

### Sintaxis alternativa en vistas (obligatorio)

```php
<? foreach($this->data as $row): ?>
    ...
<? endforeach; ?>

<? if ($condition): ?>
    ...
<? else: ?>
    ...
<? endif; ?>
```

### Heredoc para strings multilínea en generadores

```php
$fileContent = <<<DUMBOPHP
<?php
namespace App\Models;
...
DUMBOPHP;
```

### Tipos en acciones y helpers

```php
// Acciones del controlador: siempre void
public function indexAction(): void { ... }

// Helpers del controlador: tipo específico de retorno
public function _formatPrice(float $price): string { ... }

// Propiedades de modelo: ?tipo nullable, valor por defecto null
public ?string $name        = null;  // VARCHAR, TEXT
public ?int    $stock       = null;  // INTEGER, BIGINT
public ?float  $price       = null;  // FLOAT, DECIMAL
public ?bool   $active      = null;  // booleano
```

El tipo PHP de cada propiedad de modelo refleja el tipo de columna en BD.
El scaffold lo genera automáticamente. Nunca usar un tipo incorrecto
(ej: `?string $price` para una columna FLOAT) — rompe la semántica y
dificulta el análisis estático.

---

## SCAFFOLD COMO PUNTO DE PARTIDA

Para cualquier CRUD, el punto de partida es siempre scaffold:

```bash
dumbo generate scaffold products name:string description:text price:float
```

Esto genera: modelo + migración (ejecutada) + controlador con 4 acciones
(`indexAction`, `addeditAction`, `createAction`, `deleteAction`) + vistas
`index.phtml` y `addedit.phtml`. Luego se personaliza según necesidad.

**Nunca escribir un controlador CRUD desde cero.**

---

## NOMBRES CANÓNICOS

| Elemento            | Convención                    | Ejemplo                     |
|---------------------|-------------------------------|-----------------------------|
| Tabla BD            | plural, snake_case            | `user_profiles`             |
| Archivo modelo      | singular, snake_case          | `user_profile.php`          |
| Clase modelo        | singular, CamelCase           | `UserProfile`               |
| Archivo controlador | singular + `_controller`      | `user_profile_controller.php` |
| Clase controlador   | CamelCase + `Controller`      | `UserProfileController`     |
| Carpeta vistas      | singular, snake_case          | `user_profile/`             |
| Archivos de vista   | snake_case + `.phtml`         | `index.phtml`, `add_edit.phtml` |
| Acciones            | cualquierTexto + `Action`     | `indexAction`, `addEditAction` |
| Migración archivo   | `create_<tabla_plural>.php`   | `create_user_profiles.php`  |
| Migración clase     | `Create<TablaPlural>`         | `CreateUserProfiles`        |
| Test archivo        | `test<NombreClase>.php`       | `testProductModel.php`      |
| Test clase          | `test<NombreClase>`           | `testProductModel`          |
| Métodos de test     | terminan en `Test`            | `createProductTest`         |

---

## ANTI-PATRONES — NO HACER

```php
// ❌ Instanciar modelos manualmente
$model = new \App\Models\Product();

// ✅ Lazy Load
$this->data = $this->Product->Find();

// ❌ __construct() en modelos o controladores
public function __construct() { parent::__construct(); ... }

// ✅ _init_()
public function _init_(): void { $this->validates_presence_of('name'); }

// ❌ SQL directo en controlador
$result = DB->query("SELECT * FROM products");

// ✅ Active Record
$this->data = $this->Product->Find(['conditions' => 'active=1']);

// ❌ Lógica de negocio en vista
<? $products = $this->Product->Find(); ?>

// ✅ Datos preparados en el controlador
$this->data = $this->Product->Find();  // controlador
<? foreach($this->data as $row): ?>    // vista

// ❌ Motor de plantillas externo (Twig, Blade, Smarty)
// ❌ Archivos de rutas o routing configurable
// ❌ Dependencias Composer en runtime
```

---

## CONFIG

### config/host.php

```php
define('APP_ENV',        $env_vars['APP_ENV']);
define('INST_URI',       $env_vars['SITE_URI']);   // con / al final
define('SITE_STATUS',    'LIVE');
define('LANDING_PAGE',   'index/landing');
define('DEF_CONTROLLER', 'index');
define('DEF_ACTION',     'index');
define('USE_ALTER_URL',  false);
define('SALT',           'cambiar_esto');           // único por proyecto
```

### config/db_settings.php

```php
$databases = [
    'dev' => [
        'driver'      => APP_CONFIGS->get('DB_DRIVER'),
        'host'        => APP_CONFIGS->get('DB_HOST'),
        'charset'     => APP_CONFIGS->get('DB_CHARSET'),
        'dialect'     => APP_CONFIGS->get('DB_DIALECT'),
        'port'        => APP_CONFIGS->get('DB_PORT'),
        'schema'      => APP_CONFIGS->get('DB_SCHEMA'),
        'username'    => $this->_secrets->get('DB_USERNAME'),
        'password'    => $this->_secrets->get('DB_PASSWORD'),
        'unix_socket' => APP_CONFIGS->get('DB_UNIX_SOCKET'),
        'protocol'    => APP_CONFIGS->get('DB_PROTOCOL'),
    ],
    'test' => [
        'driver'      => APP_CONFIGS->get('DB_DRIVER_TEST'),
        'host'        => APP_CONFIGS->get('DB_HOST_TEST'),
        'charset'     => APP_CONFIGS->get('DB_CHARSET_TEST'),
        'dialect'     => APP_CONFIGS->get('DB_DIALECT_TEST'),
        'port'        => APP_CONFIGS->get('DB_PORT_TEST'),
        'schema'      => APP_CONFIGS->get('DB_SCHEMA_TEST'),
        'username'    => $this->_secrets->get('DB_USERNAME_TEST'),
        'password'    => $this->_secrets->get('DB_PASSWORD_TEST'),
        'unix_socket' => APP_CONFIGS->get('DB_UNIX_SOCKET_TEST'),
    ],
];
```

- `APP_CONFIGS->get()` lee variables de entorno de `.env` (secciones `[app_values]` y `[db_settings]`).
- `$this->_secrets->get()` lee credenciales sensibles (usuario y contraseña) desde almacén separado en el archivo `.env.secrets`.
- Para entornos de test con SQLite en memoria, solo se necesitan `driver` y `schema`:

```php
'test' => ['driver' => 'sqlite', 'schema' => 'memory'],
```

### .env

```ini
[environment]
APP_ENV=dev

[app_values]
INST_URI=https://localhost/myproject/
SALT=cambia_esto

[db_settings]
DB_DRIVER=mysql
DB_HOST=localhost
DB_CHARSET=utf8
DB_DIALECT=2
DB_PORT=3306
DB_SCHEMA=mi_db

DB_DRIVER_TEST=sqlite
DB_HOST_TEST=
DB_CHARSET_TEST=utf8
DB_DIALECT_TEST=2
DB_PORT_TEST=
DB_SCHEMA_TEST=memory
```


- `.env` nunca en git. Credenciales solo en `.env.secrets`. `SALT` siempre único.

### .env.secrets

```ini
DB_USERNAME=username
DB_PASSWORD=password

DB_USERNAME_TEST=
DB_PASSWORD_TEST=
```


- `.env.secrets` nunca en git.

---

## NAMESPACES

| Namespace            | Ubicación             |
|----------------------|-----------------------|
| `DumboPHP\`          | Framework core        |
| `App\Controllers\`   | `app/controllers/`    |
| `App\Models\`        | `app/models/`         |
| `Migrations\`        | `migrations/`         |
| `tests\`             | `tests/`              |
