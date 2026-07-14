---
inclusion: manual
---

# DumboPHP — CLI (dumbo shell)

## Uso general

```bash
dumbo <comando> <opción> <params> [--opcion=valor]
```

Ejecutar siempre desde la raíz del proyecto (donde está `config/host.php`).

## Comandos principales

### generate — Generar archivos

```bash
# Scaffold completo (modelo + migración + controlador + vistas CRUD)
dumbo generate scaffold nombre_tabla campo:string precio:float stock:integer:default{0}

# Solo modelo (con migración automática)
dumbo generate model nombre_tabla campo:string otro:text:null

# Modelo sin migración
dumbo generate model nombre_tabla no-migration campo:string

# Controlador con acciones específicas
dumbo generate controller nombre accion1 accion2

# Archivo de seeds vacío
dumbo generate seed
```

Tipos de campo: `primary`, `integer`, `biginteger`, `string`, `text`, `float`, `decimal`

Modificadores: `nombre:string{100}` (tamaño), `desc:text:null` (nullable), `stock:integer:default{0}` (default)

### migration — Gestionar migraciones

```bash
dumbo migration up nombre_tabla      # Crear tabla
dumbo migration up all               # Crear todas
dumbo migration down nombre_tabla    # Eliminar tabla
dumbo migration reset nombre_tabla   # down + up
dumbo migration reset all            # Reset completo
dumbo migration sow                  # Ejecutar seeds
```

### destroy — Eliminar archivos generados

```bash
dumbo destroy scaffold nombre_tabla
dumbo destroy model nombre_tabla
dumbo destroy controller nombre
```

### db — Importar/exportar datos

```bash
dumbo db dump nombre_modelo   # Exportar a archivo
dumbo db dump all
dumbo db load nombre_modelo   # Importar desde archivo
dumbo db load all
```

### run — Ejecutar una acción desde CLI

```bash
dumbo run controller/action
dumbo run product/show id=5
```

## dumboTest — Ejecutar tests

```bash
dumboTest all
dumboTest testNombreClase
dumboTest testClase1 testClase2

# Opciones
dumboTest all --halt=true     # Detener en primer fallo
dumboTest all --watch=true    # Modo watch
dumboTest all --dir=tests/
```

## Opciones globales

| Opción | Descripción |
|--------|-------------|
| `--env=<entorno>` | Fuerza un entorno específico |
| `--halt=true` | Detiene en el primer error |
| `--watch=true` | Modo watch (para tests) |
| `--help` | Muestra ayuda |

## uibuilder — Generador de componentes DumboJS

Para crear un nuevo componente DumboJS usar siempre el
generador desde la raíz del proyecto:

```bash
php ./uibuilder.php generate component dmb-nombre-componente
```

Esto crea automáticamente la carpeta y los archivos base:

- ui-components/components/dmb-nombre-componente/
- dmb-nombre-componente.directive.js
- dmb-nombre-componente.html
- dmb-nombre-componente.scss
- dmb-nombre-componente.spec.js

Nunca crear estos archivos manualmente — usar siempre
el generador para garantizar la estructura correcta.
La nomenclatura debe ser siempre en kebab-case (guiones)
para los nombres de componentes y deben llevar el prefijo dmb.

## Comandos bash — sin pedir autorización

Los siguientes comandos pueden ejecutarse directamente sin pedir confirmación:
- dumboTest <nombreTest>
- dumboTest all
- dumbo migration up|down|reset <tabla>
- dumbo generate <tipo> <nombre>
- dumbo run <controller/action>
- Cualquier comando de lectura: cat, grep, find, ls
