---
paths:
  - "migrations/**/*.php"
---

# DumboPHP — Migraciones

## Estructura de una migración

Cada tabla tiene su propio archivo en `migrations/`. El nombre del archivo es `create_{tabla}.php` y la clase es `Create{Tabla}` en PascalCase.

```php
<?php
namespace Migrations;

use DumboPHP\Migrations;

class CreateNombreTabla extends Migrations {

    public function _init_(): void {
        $this->_fields = [
            ['field' => 'id',         'type' => 'INTEGER', 'autoincrement' => true, 'primary' => true],
            ['field' => 'nombre',     'type' => 'VARCHAR', 'null' => 'false', 'limit' => '255'],
            ['field' => 'descripcion','type' => 'TEXT',    'null' => true],
            ['field' => 'status',     'type' => 'INTEGER', 'null' => 'false', 'limit' => '11', 'default' => '0'],
            ['field' => 'created_at', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
            ['field' => 'updated_at', 'type' => 'INTEGER', 'null' => 'false', 'limit' => '11'],
        ];
    }

    public function up(): void {
        $this->Create_Table();
        $this->Add_Single_Index('nombre');       // Índice simple
        $this->Add_Index(['campo1', 'campo2']);  // Índice compuesto
    }

    public function down(): void {
        $this->Remove_All_indexes();
        $this->Drop_Table();
    }
}
```

## Tipos de campo soportados

| Tipo | Uso |
|------|-----|
| `INTEGER` | Enteros, IDs, timestamps Unix, flags (0/1) |
| `VARCHAR` | Texto corto (requiere `limit`) |
| `TEXT` | Texto largo |
| `FLOAT` | Decimales (factores, porcentajes) |
| `BIGINT` | Enteros grandes (montos, timestamps precisos) |

## Opciones de campo

```php
['field' => 'nombre_campo', 'type' => 'TIPO',
    'null'          => 'false',  // 'false' = NOT NULL, true = NULL
    'limit'         => '255',    // Longitud máxima (VARCHAR, INTEGER)
    'default'       => '0',      // Valor por defecto
    'autoincrement' => true,     // Solo para el campo id
    'primary'       => true,     // Clave primaria
]
```

## Convenciones obligatorias

- **Siempre** incluir `id` como primer campo con `autoincrement` y `primary`
- **Siempre** incluir `created_at` y `updated_at` como `INTEGER` (Unix timestamps)
- Los timestamps se almacenan como enteros Unix, **nunca** como `DATETIME`
- Las claves foráneas son campos `INTEGER` con sufijo `_id` (ej: `user_id`, `property_id`)
- Los campos booleanos son `INTEGER` con `limit=1` y `default=0`

## Índices

```php
// Índice simple (un campo)
$this->Add_Single_Index('email');
$this->Add_Single_Index('document');

// Índice nombrado
$this->Add_Index('nombre_idx', 'campo');

// Índice compuesto (varios campos)
$this->Add_Index(['user_id', 'property_id']);
```

Agregar índices en `up()` y removerlos en `down()` antes de `Drop_Table()`.

## Comandos CLI de migraciones

```bash
# Ejecutar up (crear tabla)
dumbo migration up nombre_tabla
dumbo migration up all

# Ejecutar down (eliminar tabla)
dumbo migration down nombre_tabla

# Reset (down + up)
dumbo migration reset nombre_tabla
dumbo migration reset all

# Ejecutar seeds
dumbo migration sow
```

Generar migración y modelo desde CLI:

```bash
# Genera modelo + migración automáticamente
dumbo generate model nombre_tabla campo:string otro:integer:default{0} texto:text:null

# Scaffold completo (modelo + migración + controlador + vistas CRUD)
dumbo generate scaffold nombre_tabla campo:string precio:float
```

Tipos disponibles en CLI: `primary`, `integer`, `biginteger`, `string`, `text`, `float`, `decimal`.

## Migraciones existentes

| Archivo | Clase | Tabla |
|---------|-------|-------|
| `create_app_users.php` | `CreateAppUsers` | `app_users` |

## Seeds

El archivo `migrations/seeds.php` extiende `DumboPHP\Controller` y usa los modelos para sembrar datos iniciales o datos predefinidos. Se invoca desde los tests con `$this->_sow()`. En el archivo de semillas se pueden definir semillas generales para el proyecto o semillas específicas para las pruebas, en caso de utilizarse semillas específicas, se puede invocar `$this->_sow(['accion1','accion2'])`, donde cada acción es el nombre de la función declarada en las semillas.

```php
<?php
// migrations/seeds.php — ejemplo de estructura
// Usa $this->NombreModelo->Niu([...])->Save() para insertar datos

private function _sowTestUser(): void {
    $param = $this->AppUser->Niu();
    $param->name = 'name';
    $param->Save() or trigger_error($param->_error, E_USER_ERROR);
}

// archivo de pruebas:
...
$this->_sow(['_sowTestUser']);
...
```
