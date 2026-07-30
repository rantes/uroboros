# Explorador de Eventos

## Introducción

Listado de solo lectura sobre el Event Store (`App\Models\Event`, ya
existente desde `nucleo-oem`) — primer consumo real de "Eventos" en
el sidebar del dashboard, promovido de "próximamente" a link real
(ver `.claude/specs/dashboard-shell/tasks.md`, tarea 14).

**No es un spec de un dominio nuevo.** No se crea ningún modelo,
migración, ni lógica de negocio — el Event Store ya existe. Este spec
únicamente expone su lectura a través del mecanismo genérico de CRUD
ya usado por `AdminController`/`AdminBaseTrait` (el mismo de
`gestion-proyectos`), restringido a solo lectura.

## Requisitos

### Requisito 1 — Listar Eventos

**Historia de usuario:** Como administrador, quiero ver el historial
de Events registrados, para entender qué ha pasado en el sistema
(trazabilidad — "¿por qué está pasando?", principio de UX de la
visión del producto).

#### Criterios de aceptación

1. DADO que existen Events en la tabla `events`, CUANDO un
   administrador autenticado visita `/admin/events`, ENTONCES ve un
   listado con `aggregate_type`, `aggregate_id`, `event_type` y
   `created_at`, ordenado del más reciente al más antiguo.
2. DADO un listado con más registros de los que caben en una página,
   CUANDO se visualiza, ENTONCES usa paginación estándar
   (`Paginate()` + `dmb-pagination`, mismo patrón que cualquier otro
   listado del admin).
3. DADO que no hay sesión activa, CUANDO se intenta acceder, ENTONCES
   se redirige a login — comportamiento estándar, sin excepción en
   `exceptsBeforeFilter`.

### Requisito 2 — Solo lectura, sin excepción

**Historia de usuario:** Como plataforma, quiero que el Event Store
nunca pueda modificarse ni eliminarse desde el admin, para preservar
la garantía de auditoría que es la razón de ser del Event Store
(Requisito 1.2 del núcleo OEM: *"el modelo no debe exponer ningún
método de actualización con sentido de negocio"*).

#### Criterios de aceptación

1. DADO que `AdminBaseTrait` comparte sus métodos de escritura
   (`_create_reg()`/`_update_reg()`/`_delete_reg()`) entre todas las
   entidades del controlador — no hay una versión "por entidad" que
   se pueda omitir —, CUANDO se habilita `events`, ENTONCES el
   modelo `event` se agrega explícitamente a una lista de modelos de
   solo lectura (`$_readOnlyModels`) verificada al inicio de esos tres
   métodos. No basta con "no implementar" nada — hay que bloquear
   activamente.
2. DADO un intento de `POST`/`PUT`/`DELETE` contra `/admin/events`,
   CUANDO llega al controlador, ENTONCES responde `HTTP_405` vía
   `ControllerException` — comportamiento ya estándar del mecanismo
   genérico ante un método no implementado (confirmado por el
   usuario, no es algo que este spec deba construir).

## Fuera de alcance

- Filtros por `event_type`/`aggregate_type` — el MVP es un listado
  simple ordenado por fecha. Si hace falta filtrar, es una extensión
  incremental, no parte de este spec.
- Formato enriquecido de `payload` (pretty-print de JSON, etc.) — se
  muestra como lo renderice por defecto el mecanismo genérico; si no
  es legible, se ajusta en una iteración posterior, no bloquea este
  spec.
- Cualquier vista de detalle de un Event individual (drill-down) — es
  un listado, no una vista de detalle.
- Replay o cualquier acción operativa sobre un Event — el Event Store
  es de solo consulta desde el admin, siempre.