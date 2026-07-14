1. SPEC (requisitos)
   └── .claude/specs/{feature}/requirements.md

2. DISEÑO (arquitectura)
   └── .claude/specs/{feature}/design.md

3. TAREAS (plan de implementación)
   └── .claude/specs/{feature}/tasks.md

4. IMPLEMENTACIÓN (código)
   ├── migrations/create_{tabla}.php
   ├── app/models/{modelo}.php
   ├── app/controllers/{controlador}_controller.php
   ├── app/views/{controlador}/{vista}.phtml
   └── tests/test{Nombre}.php

5. VERIFICACIÓN (tests)
   └── Ejecutar tests con Timothy
`````````text

## Checklist por capa al implementar una feature

### 1. Migración (si hay nueva tabla)

- [ ] Crear `migrations/create_{tabla}.php`

- [ ] Incluir `id`, `created_at`, `updated_at` siempre

- [ ] Timestamps como `INTEGER` (Unix), nunca `DATETIME`

- [ ] Agregar índices relevantes en `up()`

- [ ] Implementar `down()` con `Remove_All_indexes()` + `Drop_Table()`

- [ ] Agregar la tabla a `testTables.php` (`migrationsTest` y `relationsTest`)

### 2. Modelo

- [ ] Crear `app/models/{nombre}.php` con namespace `App\Models`

- [ ] Extender `DumboPHP\ActiveRecord`

- [ ] Declarar propiedades públicas para cada columna

- [ ] Implementar `_init_()` con validaciones, hooks y relaciones

- [ ] Sanitizar campos de texto con `htmlentities()` en `before_save`

- [ ] Crear `tests/test{Nombre}Model.php`

### 3. Controlador

- [ ] Crear `app/controllers/{nombre}_controller.php` con namespace `App\Controllers`

- [ ] Extender `MainController` (o `Controller` para background)

- [ ] Definir `$this->_loginLevel` en el constructor

- [ ] Cada acción pública termina en `Action` (ej: `indexAction()`)

- [ ] Usar `ControllerException` para errores controlados

- [ ] Siempre try/catch/finally en acciones que responden JSON

- [ ] Agregar al menú en `Menu_Helper.php` si aplica

- [ ] Crear `tests/test{Nombre}Controller.php`

### 4. Vistas

- [ ] Crear archivos `.phtml` en `app/views/{controlador}/`

- [ ] Usar sintaxis alternativa PHP (`:` / `endif` / `endforeach`)

- [ ] Usar `<?` y `<?=` (short tags)

- [ ] **Consultar `dumbojs-components.md` antes de escribir HTML** — usar solo componentes documentados

- [ ] Formularios con `dmb-simple-form` o `dmb-form` + `dmb-input`/`dmb-select`/`dmb-textarea` + `dmb-button`

- [ ] Listas con `dmb-table` + `dmb-pagination` + `dmb-dock`

- [ ] Acciones de fila con `dmb-more-options` + `dmb-more-option`

- [ ] Paneles con `dmb-panel` + `dmb-close-panel` cargados por `dmb-button-action[behavior=open-panel]`

### 5. Frontend JS (si aplica)

- [ ] Si es sección nueva, crear archivo de acción en `ui-components/actions/`
      con solo los imports de componentes — sin lógica de vista

- [ ] Agregar endpoints en `ui-components/libs/app/configs.js` si aplica

- [ ] Crear componente nuevo con `php ./uibuilder.php generate component dmb-nombre`
      SOLO si encapsula comportamiento JS reutilizable no cubierto por componentes existentes

- [ ] Usar `appModel` para operaciones CRUD estándar desde componentes

- [ ] Usar `spinalCord` para comunicación entre componentes

- [ ] **Nunca** `<script>` ni `onclick` en vistas `.phtml` — viola CSP

- [ ] `dmb-simple-form` maneja respuestas del servidor via `dmb-dialog` automáticamente

## Estructura de un spec

### `requirements.md`

````````markdown

# Nombre de la Feature

## Introducción

Descripción breve del problema que resuelve.

## Requisitos

### Requisito 1

**Historia de usuario:** Como [rol], quiero [acción] para [beneficio].

#### Criterios de aceptación

1. DADO [contexto] CUANDO [acción] ENTONCES [resultado esperado]

2. DADO [contexto] CUANDO [acción] ENTONCES [resultado esperado]

```````text

### `design.md`

``````markdown

# Diseño técnico — Nombre de la Feature

## Arquitectura

### Nuevas tablas / cambios en BD

- Tabla `nombre_tabla`: descripción de campos

### Modelos

- `NombreModelo`: descripción de responsabilidades

### Controladores

- `NombreController`: acciones y endpoints

### Vistas

- `nombre/vista.phtml`: descripción

## Flujo de datos

Descripción del flujo request → controller → model → view
`````text

### `tasks.md`

````markdown

# Tareas de implementación

- [ ] 1. Crear migración `create_nombre_tabla.php`

- [ ] 2. Crear modelo `NombreModelo`

- [ ] 3. Crear tests del modelo `testNombreModelo.php`

- [ ] 4. Crear controlador `NombreController`

- [ ] 5. Crear tests del controlador `testNombreController.php`

- [ ] 6. Crear vistas

- [ ] 7. Agregar al menú si aplica

- [ ] 8. Actualizar `testTables.php`

```text

## Orden de implementación recomendado

1. **Migración** → define la estructura de datos

2. **Tests de migración** → verificar campos y tipos en `testTables.php`

3. **Modelo** → lógica de negocio y validaciones

4. **Tests del modelo** → validaciones, hooks, casos límite

5. **Controlador** → acciones HTTP

6. **Tests del controlador** → flujos de autenticación, respuestas HTTP

7. **Vistas** → interfaz de usuario

8. **JS/Componentes** → interactividad frontend

## Reglas de calidad

- Todo modelo nuevo debe tener su archivo de test

- Todo controlador nuevo debe tener su archivo de test

- Los tests deben cubrir: caso feliz, validaciones fallidas, autenticación

- `beforeEach()` siempre resetea las tablas con `_migrateTables()`

- No hacer `require` de modelos en controladores ni tests (Lazy Load)

- No usar `new NombreModelo()` en controladores (usar `$this->NombreModelo`)

- Sanitizar siempre los inputs de texto con `htmlentities()` antes de guardar

- Usar `ControllerException` con el código HTTP correcto, nunca lanzar `\Exception` directamente para errores de negocio

## Agregar una entidad al CRUD del admin

1. Crear migración + modelo + tests

2. Agregar el nombre en plural a `$this->_actions` en `AdminController::__construct()`

3. Crear vistas `admin/{modelo}_list.phtml` y `admin/{modelo}_addedit.phtml`

4. Agregar entrada al menú en `adminMenu()` en `Menu_Helper.php`

5. Si no debe tener botón "Agregar", añadir a `$this->dockNoAddActions`

6. Si no debe tener carga por lotes, añadir a `$this->excludeBatch`