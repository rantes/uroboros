# Uroboros — Contexto del proyecto

Operational Event Mesh construido sobre **DumboPHP** (MVC PHP 8.1+)
y **DumboJS** (Web Components nativos). MySQL vía PDO..

## Principios no negociables

- **DRY / KISS** — abstraer si se repite, solución más simple que funcione
- **Lazy Model Load** — nunca `new NombreModelo()` en controladores ni tests;
  siempre `$this->NombreModelo`
- **`_init_()` en lugar de `__construct()`** — en modelos, controladores y tests
- **Tipos de propiedades de modelo reflejan el tipo real de BD** — `?string`
  para texto, `?int` para enteros/IDs/timestamps/flags, `?float` para
  decimales. El cast explícito es una capa de seguridad contra datos inválidos
  recibidos por POST. Nunca `?bool` — usar `?int` con valores 0/1
- **`id`, `created_at`, `updated_at` son automáticos** — no declararlos en el modelo
- **Routing por convención** — `/{controller}/{action}/{params}`; sin archivos de rutas
- **Sin dependencias Composer en runtime** — verificar si existe en el framework
  o PHP nativo primero
- **Sin motor de plantillas externo** — PHP puro con `<?` y `<?=` en vistas `.phtml`
- **Bloques de control con `:` siempre** — `if/elseif/else/endif`,
  `foreach/endforeach`, nunca llaves `{}`
- **Promesas JS con `.then()/.catch()`** — no `async/await`
- **Sin scripts externos** — nunca Python ni herramientas fuera del stack
  del proyecto para cualquier tarea
- **Nunca `<script>` ni `onclick` en vistas `.phtml`** — viola CSP;
  usar `dmb-button-action` con `behavior`, `panel` y `url`
- **`dmb-simple-form` maneja respuestas automáticamente** — via `dmb-dialog`;
  no agregar JS adicional para esto
- **Campos nuevos en tablas existentes** — modificar la migración directamente
  y ejecutar `dumbo migration reset [tabla]`; nunca `Add_Column`

## Reglas PHP — imports y use

Siempre declarar `use` al inicio del archivo para cualquier clase referenciada.
Nunca usar rutas absolutas con `\` en el cuerpo del código.

Orden obligatorio al inicio de cada archivo PHP:

1. `namespace`
2. `use` de clases del framework (`DumboPHP\...`)
3. `use` de clases del proyecto (`App\...`, `Migrations\...`)
4. Definición de la clase

```php
// CORRECTO
use App\Helpers\StorageHandler;

$handler = new StorageHandler('');

// INCORRECTO — nunca rutas absolutas en el cuerpo
$handler = new \App\Helpers\StorageHandler('');
```

## Generación de archivos — usar siempre el CLI primero

Antes de escribir cualquier modelo, migración, controlador o vista,
ejecutar `dumbo generate`. Ahorra tokens y garantiza la estructura correcta:

```bash
dumbo generate scaffold nombre_tabla campo:string precio:float stock:integer:default{0}
dumbo generate model nombre_tabla campo:string otro:text:null
dumbo generate controller nombre accion1 accion2
```

Tipos: `primary`, `integer`, `biginteger`, `string`, `text`, `float`, `decimal`

Modificadores: `campo:string{100}` · `campo:text:null` · `campo:integer:default{0}`

## Tests de controladores — patrón de aserción

`_runAction()` retorna el objeto controlador. Usar siempre `$result->_code`
para verificar el código HTTP. Nunca `http_response_code()` — `ob_get_clean()`
lo limpia y retorna `false`.

```php
// CORRECTO
$result = $this->_runAction('/controller/action');
$this->assertEquals(HTTP_401, (int) $result->_code);

// INCORRECTO
$code = http_response_code();
```

## Flujo SDD para nuevas features

@.claude/rules/sdd-workflow.md

## Referencias de desarrollo

- Controladores: @.claude/rules/dumbophp-controllers.md
- Modelos y Active Record: @.claude/rules/dumbophp-models.md
- CLI completo: @.claude/rules/cli.md
- Convenciones y anti-patrones: @.claude/rules/code-conventions.md

<!-- Las siguientes reglas cargan automáticamente por paths:
     - Vistas (.phtml)      → .claude/rules/views.md
     - Frontend (DumboJS)   → .claude/rules/dumbojs-components.md
     - Migraciones          → .claude/rules/migrations.md
     - Tests                → .claude/rules/testing.md
-->

## Archivos temporales de test

Cuando crees archivos de test temporales (testTmp*.php o similar) para
verificaciones empíricas, elimínalos automáticamente al terminar la
verificación sin pedir autorización adicional.
