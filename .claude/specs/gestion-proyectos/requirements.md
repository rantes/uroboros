# Gestión de Proyectos

## Introducción

Primer dominio real de negocio de Uroboros, sobre el dominio
"Project Management" de la visión (*"Manage software systems. Examples:
Backend API, Frontend, Shared Libraries, Mobile Applications."*).

**Decisión de alcance confirmada:** Proyectos es CRUD administrativo
plano — modelo ActiveRecord + controlador estándar, **sin pasar por el
núcleo OEM**. El núcleo (Command→Handler→Event→Bus→Reaction) queda
reservado para dominios de ejecución/operación (Workflow Execution,
Health Management), no para gestión de datos de referencia. Esto es
intencional, no una omisión: ver "Fuera de alcance".

**Enfoque de implementación:** *(sección retirada — el supuesto de
`dumbo generate scaffold` + Modelo/Controlador/Vistas por entidad ya
no aplica. El proyecto cuenta con un mecanismo genérico propio que
resuelve Modelo+Controlador+Vistas sin clase por entidad. Pendiente
de definir qué necesita documentarse aquí una vez se entienda ese
mecanismo — ver conversación.)*

## Campos confirmados

| Campo | Tipo | Notas |
| --- | --- | --- |
| `name` | `?string` | Requerido, único |
| `description` | `?string` | Opcional (TEXT en BD) |
| `repository_url` | `?string` | Opcional — URL del repositorio (Git) |
| `type` | `?string` | Categoría libre por ahora (ej. "backend", "frontend", "library", "mobile" — sin tabla de catálogo separada en v1, se valida como lista cerrada en el modelo) |
| `status` | `?int` | `0`/`1` — activo/archivado (nunca `?bool`, por convención del proyecto) |

Sin `project_group_id` en `projects` — la relación con `ProjectGroup`
es muchos-a-muchos (`has_many_and_belongs_to`), vive en una tabla
pivote, no en una columna de `projects`. Ver Requisito 6.

**`owner`/responsable — descartado, no solo diferido.** Sin ACL, un
campo de propietario no protege ni habilita nada, es dato decorativo.
Nota aparte, fuera del alcance de este spec: si tiene sentido que
Uroboros siga siendo multiusuario sin ACL es una pregunta sobre
`AppUser`/autenticación, no sobre Proyectos — se resuelve en una
conversación aparte, después de este spec (confirmado).

## Requisitos

### Requisito 1 — Listar Proyectos

**Historia de usuario:** Como administrador, quiero ver la lista de
todos los proyectos registrados, para tener visibilidad de qué
sistemas de software gestiona Uroboros.

#### Criterios de aceptación

1. DADO que existen proyectos registrados, CUANDO un administrador
   autenticado visita `/admin/proyectos`, ENTONCES ve una tabla con
   `name`, `type` y `status` de cada proyecto.
2. DADO que no hay sesión activa, CUANDO se intenta acceder a
   `/admin/proyectos`, ENTONCES se redirige a login (comportamiento
   estándar de `MainController::before_filter()`, sin excepción
   agregada a `exceptsBeforeFilter`).
3. DADO un listado con más registros de los que caben en una página,
   CUANDO se visualiza, ENTONCES usa `Paginate()` + `dmb-pagination`
   (patrón estándar de `views.md`).

### Requisito 2 — Crear Proyecto

**Historia de usuario:** Como administrador, quiero registrar un
nuevo proyecto con sus datos básicos, para empezar a gestionarlo en
Uroboros.

#### Criterios de aceptación

1. DADO un formulario con `name` y `type` válidos, CUANDO se envía,
   ENTONCES se crea el registro y responde `HTTP_201`.
2. DADO un `name` vacío, CUANDO se envía el formulario, ENTONCES la
   validación falla y no se crea el registro (`presence_of`).
3. DADO un `name` ya usado por otro proyecto, CUANDO se envía,
   ENTONCES la validación falla por duplicado (`unique`).
4. DADO un `type` fuera de la lista cerrada de categorías válidas,
   CUANDO se envía, ENTONCES la validación falla.
5. DADO `repository_url`/`description` vacíos, CUANDO se envía el
   formulario, ENTONCES el proyecto se crea sin error (son opcionales).

### Requisito 3 — Editar Proyecto

**Historia de usuario:** Como administrador, quiero modificar los
datos de un proyecto existente, para mantener su información
actualizada.

#### Criterios de aceptación

1. DADO un proyecto existente, CUANDO se envían cambios válidos vía
   `PUT`, ENTONCES se actualiza y responde `HTTP_200`.
2. DADO un `id` de proyecto inexistente, CUANDO se intenta editar,
   ENTONCES lanza `ControllerException` con `HTTP_400`, capturada por
   el bloque try/catch/finally estándar de cada acción (patrón ya
   documentado en `dumbophp-controllers.md` — confirmado, no una
   convención nueva de este spec).
3. Las mismas validaciones del Requisito 2 aplican a la edición.

### Requisito 4 — Eliminar Proyecto

**Historia de usuario:** Como administrador, quiero eliminar un
proyecto que ya no se gestiona, para mantener limpio el listado.

#### Criterios de aceptación

1. DADO un proyecto existente sin dependientes, CUANDO se elimina,
   ENTONCES el registro desaparece y responde `HTTP_200`/`HTTP_204`.
2. DADO que en el futuro otros dominios (Entornos, Workflows)
   referencien a un Proyecto por `belongs_to`, CUANDO se intente
   eliminar un proyecto con dependientes, ENTONCES el comportamiento
   (bloquear vs. cascada) debe decidirse en ese spec futuro — hoy no
   hay dependientes reales, así que `Delete()` simple es suficiente.

### Requisito 5 — Gestionar Grupos

**Historia de usuario:** Como administrador, quiero crear y mantener
grupos, para organizar mis proyectos, pudiendo tener el mismo
proyecto en más de un grupo a la vez.

> Nombre de entidad corregido: `Group` (tabla `groups`) — no
> `ProjectGroup`, para no chocar con el nombre de la tabla pivote
> `project_groups` que declara la relación muchos-a-muchos (ver
> Requisito 6).

#### Criterios de aceptación

1. DADO un nombre de grupo válido, CUANDO se crea, ENTONCES queda
   disponible para asignarse a uno o más proyectos.
2. DADO un grupo existente, CUANDO se edita su nombre, ENTONCES se
   actualiza.
3. DADO un grupo con proyectos asignados, CUANDO se elimina, ENTONCES
   solo se eliminan las filas de la tabla pivote que lo referencian —
   los proyectos afectados no se eliminan ni quedan en estado
   inválido, simplemente dejan de pertenecer a ese grupo (siguen
   perteneciendo a cualquier otro grupo que ya tuvieran).
4. `ProjectGroup` es una entidad plana — sin jerarquía entre grupos
   (un grupo no puede pertenecer a otro grupo). Si se necesita
   jerarquía en el futuro, es un cambio de spec explícito, no una
   extensión implícita de este modelo.

### Requisito 6 — Asignar uno o más Grupos a un Proyecto

**Historia de usuario:** Como administrador, quiero asignar (o
quitar) uno o más grupos de un proyecto al crearlo o editarlo, para
que un mismo proyecto pueda organizarse bajo varios criterios a la
vez (ej. "Móvil" y "Cliente X" simultáneamente).

#### Criterios de aceptación

1. DADO el formulario de creación/edición de Proyecto, CUANDO se
   completa, ENTONCES incluye un `dmb-select multiple` con los grupos
   existentes (ninguno seleccionado es válido).
2. DADO un proyecto sin ningún grupo asignado, CUANDO se lista,
   ENTONCES no falla — cero grupos es un estado válido, no un error.
3. DADO un proyecto asignado a dos o más grupos, CUANDO se elimina uno
   de esos grupos (Requisito 5.3), ENTONCES el proyecto conserva su
   relación con los grupos restantes.
4. **Pendiente de verificación contra el framework real** (no asumido
   aquí, ver `design.md`): si `has_many_and_belongs_to` de
   `DumboPHP\ActiveRecord` soporta join entre tablas distintas
   (`projects` ↔ `groups` vía pivote `project_groups`) o solo
   auto-referencia dentro de la misma tabla. El esquema físico
   (tabla pivote `project_groups` con `belongs_to` explícito hacia
   ambos lados) funciona independientemente de la respuesta — lo que
   cambia es únicamente cómo se declara la relación en cada modelo.

## Fuera de alcance de este spec

- Cualquier integración con el núcleo OEM — Proyectos no dispara
  Commands ni genera Events. Consecuencia aceptada explícitamente: la
  creación/edición/eliminación de un proyecto no aparece en "Recent
  Events" del dashboard operativo, porque no hay Event que la
  represente. Si en el futuro se decide que sí debe ser visible ahí,
  es una decisión nueva y explícita, no algo que este spec resuelva
  por defecto.
- Relación con Entornos, Workflows, Dependencias o Salud — Proyectos
  es una entidad standalone en v1; las relaciones (`has_many`) se
  agregan cuando existan esos dominios, sin migrar `create_projects.php`
  de forma disruptiva (solo se agregan índices/FKs nuevos si aplica).
- Dashboard / vistas de "Operational Health" — eso es un spec propio,
  distinto.
- Jerarquía entre Grupos, o entre Proyectos — ver Requisito 5.4.
