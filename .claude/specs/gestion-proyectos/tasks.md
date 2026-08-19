# Tareas de implementación — Gestión de Proyectos

Alcance reducido: solo migraciones. Modelo, controlador y vistas los
resuelve el mecanismo genérico del usuario — no son tareas de este
spec.

## ⚠️ Reabierto — hallazgo crítico de `explorador-eventos`

El "mecanismo genérico" del usuario (`AdminBaseTrait`) generaliza
**Controlador y Vistas**, no el Modelo. `_list_regs()`/`_create_reg()`/
etc. acceden a `$this->{$this->_model_camelized}` — el lazy-load
estándar de DumboPHP, que exige que exista el archivo de modelo real
(`app/models/project.php`, `app/models/group.php`). Sin ese archivo,
la propiedad mágica resuelve a `null` y cualquier acción sobre
`projects`/`groups` falla con `Call to a member function ... on
null`. Confirmado en producción: ambas páginas están rotas con
cualquier verbo HTTP desde que se cerró este spec.

**Corrección pendiente:** restaurar `app/models/project.php` y
`app/models/group.php` como modelos ActiveRecord estándar (con las
validaciones/relaciones de `requirements.md`) — no como el CLI los
generó originalmente (con los bugs ya conocidos de `default{0}` y
`{N}` mutando la variable global), sino escritos/corregidos a mano
igual que se hizo con las migraciones. `app/models/project_group.php`
(la pivote) **no** hace falta como modelo — solo la tabla, dado que
la relación se consulta indirectamente.

- [x] 1. `migrations/create_projects.php` creada — reescrita a mano
      tras el generador (ver hallazgo del Paso 0 abajo).
- [x] 2. `migrations/create_groups.php` creada.
- [x] 3. `migrations/create_project_groups.php` (pivote) creada.
- [x] 4. **Confirmado (heredado de la verificación en `metricas-oem`):**
      `Add_Index()` no soporta `UNIQUE` en ningún driver. La unicidad
      de `(project_id, group_id)` debe validarse en la capa de
      aplicación, no a nivel de BD. Ver nota en `design.md`.
- [x] 5. Las tres migraciones ejecutadas (`dumbo migration up`).
- [x] 6. Las tres tablas verificadas contra la BD real en
      `tests/testTables.php`, vía un helper propio
      (`_assertMigrationMatchesDb()`) que no depende de un modelo
      ActiveRecord — `assertHasFields`/`assertHasFieldTypes` exigen
      un modelo por firma, y este spec deliberadamente no tiene uno.

## Hallazgo del Paso 0 — bug nuevo del generador CLI

`dumbo generate model` con varios campos `string{N}` en un mismo
comando: el modificador de tamaño muta una variable global compartida
por *tipo* de campo, no por campo individual
(`DumboGeneratorClass.php:65`) — el último `{N}` procesado sobreescribe
el límite de todos los campos `string` anteriores del mismo comando.
Se generaron las tres migraciones para obtener el esqueleto y
confirmar nombre de tabla/clase, pero se reescribieron completas a
mano contra `design.md` para corregir esto (además de `id`/
`created_at`/`updated_at`, que el generador tampoco agrega — mismo
hallazgo que ya se vio en `metricas-oem`).

## Modelos — restaurados, no descartados (corrección del hallazgo de arriba)

> **Esta sección estaba mal.** Decía "el mecanismo genérico del
> usuario resuelve esa capa sin clases por entidad" — eso es falso
> para el Modelo, confirmado por el crash real en producción. Los tres
> modelos generados (`project.php`, `group.php`, `project_group.php`)
> se habían eliminado sobre esa premisa incorrecta.

- [x] 7. `app/models/project.php` restaurado — con sanitización
      `htmlentities()` en `before_save` (convención no negociable,
      no estaba en el snippet original de `design.md` pero aplica
      igual) y `validateType()` a mano en `before_save` (confirmado
      que el framework real no tiene regla de validación tipo
      "inclusión en lista" — solo `email`/`numeric`/`unique`/
      `presence_of`). **Verificado funcionando end-to-end**: los 4
      verbos HTTP reales contra `/admin/projects` responden
      correctamente.
- [x] 8. `app/models/group.php` restaurado, mismo patrón. **Bloqueado
      en producción por un bug de framework, no del modelo** — ver
      nota abajo.
- [x] 9. `app/models/project_group.php` — **sí hace falta**,
      contradiciendo la nota original de esta tarea.
      `has_many_and_belongs_to` tiene un bug real en
      `Core_General_Class::__call()`: su condición de comparación de
      clase solo puede cumplirse en relaciones auto-referenciadas
      (mismo hallazgo que ya había corregido el usuario al inicio de
      este spec). Para `Project`↔`Group` (clases distintas) no
      filtra nada. Se resolvió con `has_many`/`belongs_to` hacia la
      tabla pivote directamente, lo cual sí exige que
      `App\Models\ProjectGroup` exista como archivo real.
- [x] 10. Verificado con los 4 verbos HTTP reales — ver resultado
       abajo.
- [x] 11. `dumboTest all` — 29 tests, 195 assertions (conteo de
       `test-result.xml`), 0 fallos.

## Resultado real de la verificación empírica

**`/admin/projects` y `/admin/groups` — ambos funcionan completo**,
los 4 verbos HTTP verificados contra el servidor real. Sin regresión.

**Bug de framework resuelto en `DumboPHP` (repo externo, 3 commits en
`master`):**
- `eedcc3a` — backticks en `getColumns()` de `mysql.php`/`sqlite.php`/
  `postgresql.php` (parcial, ver abajo)
- `2b5f729` — backticks en `Paginate()`/`getData()`/
  `_prepareSelectParams()` de `bin/dumbophp.php` (3 ocurrencias del
  mismo patrón, encontradas por verificación empírica, no solo grep)
- `096d50c` — backticks en `validateField()` de `sqlite.php`

Confirmado con `git fetch` + comparación de hash (no solo el mensaje
de `git status`) que `HEAD` de `master` coincide con
`origin/master`, y que la copia servida (`/etc/dumbophp`) tiene las
tres correcciones. `postgresql.php::getColumns()` queda
deliberadamente sin corregir — problema más profundo que backticks
(`SHOW COLUMNS` no es sintaxis válida de PostgreSQL), fuera de
alcance mientras el proyecto no use ese driver.

**`dumboTest all` (Uroboros):** 29 tests, 195 assertions, 0 fallos —
conteo de `test-result.xml`.

## Spec cerrado

Falta solo la vista `admin/project_list.phtml`/`group_list.phtml`
(warning no fatal, no bloquea CRUD) — responsabilidad del mecanismo
genérico de vistas del usuario, no de este spec.

## Verificación final

`dumboTest all` — conteo fresco vía `test-result.xml`: 24 tests, 184
assertions, 0 failures, 0 errors. Cero regresión sobre el resto del
proyecto.

## Vistas — bloqueador de "mínimo utilizable"

- [x] 35-38. Las 4 vistas creadas (`project_list`/`project_addedit`/
      `group_list`/`group_addedit`), patrón estándar.
- [x] 39. `AdminController::saveprojectAction()` implementado —
      simplificado tras verificar el código real (`Niu($data)` sola,
      sin rama `Find()`/`Niu()` separada).
- [x] 40. `$this->dependents = 'destroy';` agregado a `Project` —
      cascada nativa confirmada empíricamente (eliminar proyecto deja
      `project_groups` en 0 filas para ese proyecto).
- [x] 41. Verificado con `DumboChromeDriver`: crear/editar/listar/
      eliminar con clicks reales, sincronización de pivote confirmada
      por SQL directo en cada paso (Alpha+Beta → Beta+Gamma, exacto).
- [x] 42. **`dumboTest all`:** 43 tests, 165 assertions — mismo
      conteo que la línea base, cero regresión.

## ⚠️ Hallazgo mayor — el CRUD admin completo nunca funcionó por navegador

La verificación del Paso 41 destapó **8 bugs reales**, previamente
indetectables porque todo el CRUD admin (de cualquier entidad, no
solo `Project`) solo se había probado por `curl`/`_runAction()`, nunca
con clicks reales de "Agregar"/"Editar"/"Eliminar". Todos bloqueaban
la verificación misma, así que se corrigieron como parte de esta
tarea:

1. `dmb-table thead` con texto blanco sobre blanco (variables CSS
   `--surface-2`/`--information`/`--border-subtle` indefinidas) —
   headers invisibles en **todas** las vistas admin.
2. `<dmb-panel id="panel-form-add-edit-reg">` nunca existía en
   `layout.phtml` — **ningún** botón "Agregar"/"Editar" de
   **ningún** CRUD admin abría panel jamás, en ningún spec.
3. Labels del formulario invisibles (mismo patrón de variable CSS sin
   override que el punto 1).
4. Condición de carrera en `DmbDialogService` (`.wrapper` accedido
   antes de que `connectedCallback()` async termine) — **el mismo
   Hallazgo 2 ya documentado en `dumbochromedriver.md`**, no un bug
   nuevo redescubierto desde cero.
5. `dmb-close-panel` con plantilla en camelCase nunca vinculada —
   mismo patrón que el bug ya corregido una vez en `dmb-dialog`.
6. `dmb-more-options`/`dmb-more-option`/`dmb-dock` nunca importados en
   `app.js` — el menú "⋮" (Editar/Eliminar) no existía como
   componente en ningún admin.
7. `dmb-more-option[behavior=ajax]` enviaba `GET` en vez de `DELETE`
   para "Eliminar".
8. `dmb-simple-form[update]` nunca incluía el `id` en la URL del
   `PUT` — `_update_reg()` siempre recibía 404. **Afecta a todas las
   entidades con CRUD genérico** (`workflow_definitions`,
   `workflow_step_definitions`, `groups`), no solo `Project`.

Los 8 se corrigieron y verificaron empíricamente (capturas + consultas
SQL tras cada paso). Dado que esto afecta CRUD ya "cerrado" en otros
specs, vale la pena una pasada de confirmación en `explorador-eventos`
y `ejecucion-workflows` con clicks reales, no solo re-confiar en la
verificación anterior por `curl`.

## Pasada de confirmación — 2 hallazgos más

- [x] Hallazgo 9 (resuelto): `dmb-button-action.scss` usaba
      `--primary-contrast` (variable indefinida) — botón "Ejecutar"
      invisible en `workflow_definitions`. Mismo patrón que los
      hallazgos 1/3 (variables CSS indefinidas). Corregido, una línea.
- [x] Hallazgo 10 (✅ resuelto): eran **dos bugs encadenados**, no
      uno — el diagnóstico preliminar era correcto pero incompleto.
      **Bug A**: el dispatcher del framework evaluaba
      `method_exists($this->page, "{$action}Action")` sin mirar
      `PreventLoad()` primero, así que el preflight de CSRF (que ya
      había armado su propia respuesta `204` vía `before_filter()`)
      caía en el fallback `404 Missing Action`, pisando esa respuesta
      — esto explica el cuerpo concatenado `Missing Action{"d":[]...}`
      visto en el recorrido de confirmación anterior. **Bug B**
      (recién descubierto, enmascarado por el A): `before_filter()`
      forzaba `params[0] = 'list'` para cualquier request de admin sin
      segmento de URL, sin mirar el verbo HTTP — un `POST` de creación
      genérica entraba a `landingAction()` ya forzado a `'list'`, así
      que ejecutaba `_list_regs()` en vez de `_create_reg()`, sin
      error visible (mismo comportamiento ya anotado de pasada en el
      hallazgo de `explorador-eventos` sobre `POST /admin/events`).
      Corregido en el framework (`PreventLoad()` envolviendo todo el
      bloque, no solo la ejecución) y en el proyecto (`'list'` por
      defecto solo en `GET`, accesos a `params[0]` con `??  null`).
      Verificado con clicks reales en `Group` y `WorkflowStepDefinition`,
      y con `curl` en `WorkflowDefinition` (sin botón "Agregar" en su
      vista — hallazgo aparte, ver nota abajo). Cero regresión sobre
      `Project`/Eventos/Ejecutar/Eliminar ya confirmados. `dumboTest
      all`: 43/165, igual a la línea base.

**Pendiente, no bloqueante:** `workflow_definitions` sigue sin botón
"Agregar" en su vista de listado (categoría distinta — vista
incompleta, no bug de mecanismo — ya anotado antes).

**Pendiente de tu decisión:** el fix del framework en
`~/web/DumboPHP` quedó sin commitear (mismo estado en que ya estaba
antes de este cambio) — confirma si lo commiteas tú o le pides al
agente que lo haga (ya estableciste que ese repo commitea directo a
`master`).

**Nota aparte, no bloqueante:** `workflow_definitions` nunca tuvo
botón "Agregar" en su vista de listado — vista incompleta, categoría
distinta a los bugs de mecanismo de arriba, pendiente cuando se
retome ese spec.