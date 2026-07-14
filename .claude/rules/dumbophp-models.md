# DumboPHP — Modelos (Active Record)

## Patrón base

Todos los modelos extienden `DumboPHP\ActiveRecord`. Las propiedades públicas de la clase corresponden directamente a columnas de la tabla en BD. El nombre de la tabla se deriva automáticamente del nombre de la clase en snake_case plural (ej: `UserProperty` → `user_properties`).

```php
<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class NombreModelo extends ActiveRecord {
    // Propiedades = columnas de BD
    // El tipo debe reflejar el tipo real de la columna en BD.
    // El cast explícito es una capa de seguridad — valores no numéricos
    // recibidos por POST se convierten a 0 en lugar de guardarse como basura.
    public ?string $campo_texto   = null;  // VARCHAR, TEXT
    public ?int    $campo_entero  = null;  // INTEGER, BIGINT, flags, timestamps
    public ?float  $campo_decimal = null;  // FLOAT, DECIMAL

    public function _init_(): void {
        // Aquí se configura todo el comportamiento del modelo
    }
}
```

> **Importante**: el tipo de cada propiedad debe reflejar el tipo real de la
> columna en BD. El cast explícito es una capa de seguridad — valores no
> numéricos recibidos por POST se convierten a 0 en lugar de guardarse como
> basura. Usar `?string` para texto, `?int` para enteros/IDs/timestamps/flags
> y `?float` para decimales. Nunca `?bool` — usar `?int` con valores 0/1.

## Método `_init_()`

Se ejecuta después del constructor. Es el lugar donde se declaran validaciones, hooks y relaciones. **Nunca usar el constructor directamente.**

## Validaciones

```php
// Forma corta (idiomática en DumboPHP)
$this->validates_presence_of('nombre');
$this->validates_presence_of('email');

// Forma extendida con mensaje personalizado
$this->validate = [
    'presence_of' => [
        ['field' => 'nombre', 'message' => 'El nombre es obligatorio'],
        ['field' => 'email',  'message' => 'El email es obligatorio'],
    ],
    'email' => [
        ['field' => 'email', 'message' => 'El email no es válido'],
    ],
    'unique' => [
        ['field' => 'email', 'message' => 'El email ya está registrado'],
    ],
];
```

## Hooks de ciclo de vida

```php
$this->before_save   = ['metodoAntesDeSave'];   // Antes de INSERT y UPDATE
$this->before_insert = ['metodoAntesDeInsert']; // Solo antes de INSERT
$this->before_delete = ['metodoAntesDeDelete']; // Antes de DELETE
```

Los hooks son nombres de métodos públicos de la misma clase:

```php
public function metodoAntesDeSave(): void {
    $this->campo = htmlentities($this->campo, ENT_QUOTES, 'UTF-8', false);
}
```

## Relaciones

```php
$this->has_many              = ['nombre_tabla_relacionada'];
$this->belongs_to            = ['nombre_tabla_padre'];
$this->has_many_and_belongs_to = ['nombre_tabla_pivote'];
$this->dependents            = 'destroy'; // Elimina dependientes en cascada
```

## API de consulta

```php
// Crear instancia nueva (sin guardar)
$obj = $this->NombreModelo->Niu();
$obj = $this->NombreModelo->Niu(['campo' => 'valor']);

// Buscar por ID
$obj = $this->NombreModelo->Find(1);

// Buscar por campo específico (método mágico)
$obj = $this->NombreModelo->Find_by_email('user@example.com');
$obj = $this->NombreModelo->Find_by_status(1);

// Buscar con condiciones
$result = $this->NombreModelo->Find([
    'conditions' => "`status`=1 AND `level`=7",
]);

// Condiciones como array (AND implícito)
$result = $this->NombreModelo->Find([
    'conditions' => [
        ['campo1', 'valor1'],
        ['campo2', '>=', 'valor2'],
        ['campo3', 'BETWEEN', $inicio, $fin],
    ],
]);

// Opciones adicionales
$result = $this->NombreModelo->Find([
    'fields'     => 'id, nombre, COUNT(id) AS total',
    'conditions' => '`status`=1',
    'sort'       => '`nombre` ASC',
    'group'      => '`status`',
    'limit'      => 10,
]);

// Primer resultado
$obj = $this->NombreModelo->Find([':first', 'conditions' => '`status`=1']);

// Paginación
$data = $this->NombreModelo->Paginate($this->fullUrl(), ['conditions' => $conditions]);

// También se permiten atajos a búsquedas por un campo específico
$data = $this->NombreModelo->Find_by_campo_en_la_tabla('valor');

// De acuerdo a la relación establecida, se puede hacer recorrido por medio de esa relación
$data = $this->Modelo1->Find_by_id(1);

// Recorrer al modelo2 que está definida en $belongs_to
$anotherData = $data->modelo2()->col;

// Recorrer al modelo3 que está definida en $has_many
$anotehrData1 = $data->modelo3()->col;
```

## Guardar y eliminar

```php
// Primera forma
$obj = $this->NombreModelo->Niu(['campo' => 'valor']);
// Segunda forma. También puede usarse Niu() sin parámetros y establecer los valores accediendo directamente en el objeto
$obj = $this->NombreModelo->Niu();
$obj->campo = 'valor';

$ok  = $obj->Save();   // true/false

if (!$ok):
    $errors = $obj->_error->errFields(); // array de nombres de campo con error
    $msg    = (string) $obj->_error;     // mensaje de error como string
endif;

$obj->Delete(); // true/false
```

## Contar resultados

```php
$result->counter(); // número de registros en el resultado
$result->count();   // alias de counter()
```

## Modelos del dominio existentes

| Clase | Tabla | Descripción |
| --- | --- | --- |
| `AppUser` | `app_users` | Usuario con autenticación |

## Acceso a modelos

Los modelos se cargan por **Lazy Load**. Están disponibles como propiedades mágicas en controladores y en archivos de test usando el nombre de la clase en PascalCase:

```php
// En un controlador:
$this->User->Find(1);
$this->Property->Find_by_property_id(0);
$this->Booking->Niu(['facility_id' => 1]);

// En un test:
$this->AppUser->Niu($data);
$this->TrackingVisitor->Find(['conditions' => 'status=1']);
```

**No es necesario hacer `require` ni `use` de los modelos en controladores o tests.**

En los propios modelos sí es necesario instanciar directamente cuando se necesita otro modelo:

```php
// Dentro de un método de modelo:
$other = new OtroModelo();
$result = $other->Find($this->id);
```
