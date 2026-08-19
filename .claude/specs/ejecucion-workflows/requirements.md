# Ejecución de Workflows

## Introducción

Primer dominio real de **ejecución** de Uroboros — a diferencia de
Proyectos (CRUD plano), este spec sí pasa por el núcleo OEM de punta
a punta: cada paso de un workflow es un ciclo completo
Command→Handler→Event→Bus→Reaction→Command. Le da contenido real a
"Active Operations" y "Event Timeline" del dashboard, hoy en estado
"Próximamente".

**Decisiones de alcance ya confirmadas:**
- Genérico desde el inicio — tipos de step configurables (`build`,
  `deploy`, `rollback`, `migration`, `verification`), no un tipo fijo.
- Disparo manual (botón admin) **y** automático (webhook externo).
- Uroboros solo orquesta — dispara comandos/scripts externos, nunca
  ejecuta la lógica de build/deploy directamente.
- Ejecución síncrona dentro de un proceso de background: el mismo
  proceso que corre el script externo registra su resultado (BD +
  logs) — sin callback externo, sin polling.

## Entidades — confirmadas

**Plantilla:**
- `WorkflowDefinition` — `name`, `description`, `project_id`
  (`belongs_to Project`), `status` (activo/inactivo), `webhook_token`
  (secreto para autenticar el disparo automático)
- `WorkflowStepDefinition` — `workflow_definition_id`, `name`, `type`
  (lista cerrada: `build`/`deploy`/`rollback`/`migration`/
  `verification`), `command` (el script/comando externo), `step_order`
  (nunca `order` — palabra reservada en MySQL, ya nos costó un bug
  real con `groups`)

**Ejecución (tablas de proyección, no el Event Store):**
- `WorkflowExecution` — `workflow_definition_id`, `status` (lista
  cerrada: `pending`/`running`/`completed`/`failed`), `trigger_type`
  (`manual`/`webhook`), `started_at`, `completed_at`
- `StepExecution` — `workflow_execution_id`,
  `workflow_step_definition_id`, `status` (misma lista cerrada),
  `exit_code`, `output` (TEXT), `started_at`, `completed_at`

Mismo patrón que `OemMetric`: estas tablas son proyección/estado
consultable rápido, no la fuente de verdad — eso sigue siendo
`events`.

## Requisitos

### Requisito 1 — Definir un Workflow

**Historia de usuario:** Como administrador, quiero crear una
plantilla de workflow con sus pasos ordenados, para poder ejecutarla
después sobre un proyecto.

#### Criterios de aceptación

1. DADO un nombre y un proyecto, CUANDO se crea un
   `WorkflowDefinition`, ENTONCES queda disponible para agregarle
   pasos.
2. DADO un `WorkflowDefinition`, CUANDO se agregan
   `WorkflowStepDefinition`, ENTONCES cada uno tiene un `type` de la
   lista cerrada, un `command`, y un `step_order` que determina la
   secuencia de ejecución.
3. DADO que la gestión de pasos es una colección anidada (no una
   entidad plana como Proyecto/Grupo), CUANDO se diseñe la UI,
   ENTONCES se evalúa si el mecanismo genérico de CRUD ya usado
   (`AdminBaseTrait`) alcanza para esto, o si hace falta algo
   adicional — **no asumido aquí**, es una decisión de `design.md`.

### Requisito 2 — Disparo manual

**Historia de usuario:** Como administrador, quiero ejecutar un
workflow manualmente desde el admin, para desplegar/construir cuando
yo decida, no solo por evento externo.

#### Criterios de aceptación

1. DADO un `WorkflowDefinition` activo, CUANDO un administrador
   confirma "ejecutar" desde el admin, ENTONCES se despacha
   `ExecuteWorkflowCommand` con `trigger_type = 'manual'`.
2. Esto **no** pasa por el mecanismo genérico de CRUD
   (`_create_reg()` etc.) — es una acción de dominio real que dispara
   un Command, no un registro que se guarda. Requiere una acción de
   controlador dedicada.

### Requisito 3 — Disparo automático por webhook

**Historia de usuario:** Como plataforma externa (ej. un proveedor
Git), quiero notificar a Uroboros de un evento, para que dispare
automáticamente el workflow correspondiente.

#### Criterios de aceptación

1. DADO un endpoint de webhook con el `webhook_token` correcto de un
   `WorkflowDefinition`, CUANDO se invoca, ENTONCES se despacha el
   mismo `ExecuteWorkflowCommand` que el disparo manual, con
   `trigger_type = 'webhook'`.
2. DADO un token inválido o ausente, CUANDO se invoca el webhook,
   ENTONCES se rechaza (`HTTP_401`), sin disparar ningún Command.
3. Este endpoint **no** requiere sesión/CSRF (es de origen externo) —
   mismo patrón que otras acciones públicas ya excluidas de
   `before_filter()` vía `exceptsBeforeFilter`.

### Requisito 4 — Orquestación de la ejecución (núcleo OEM real)

**Historia de usuario:** Como plataforma, quiero que cada paso de un
workflow se ejecute en orden, con su resultado registrado como Event,
para tener trazabilidad completa de qué pasó y por qué.

#### Criterios de aceptación

1. DADO `ExecuteWorkflowCommand`, CUANDO su Handler lo procesa,
   ENTONCES crea `WorkflowExecution` (`status = 'pending'`) y el
   Event `WorkflowStarted`.
2. DADO el Event `WorkflowStarted`, CUANDO su Reaction se dispara,
   ENTONCES despacha `ExecuteStepCommand` para el primer
   `WorkflowStepDefinition` (por `step_order`).
3. DADO `ExecuteStepCommand`, CUANDO su Handler lo procesa, ENTONCES
   — dentro de un proceso de background, no en el request HTTP que
   originó el disparo — ejecuta el `command` externo, espera su
   resultado (síncrono dentro de ese proceso), y crea el Event
   `StepCompleted` o `StepFailed` según el `exit_code`.
4. DADO `StepCompleted`, CUANDO hay un siguiente
   `WorkflowStepDefinition` en la secuencia, ENTONCES su Reaction
   despacha `ExecuteStepCommand` para ese paso. Si no hay más pasos,
   despacha `CompleteWorkflowCommand`.
5. DADO `StepFailed`, CUANDO ocurre, ENTONCES **no se ejecutan más
   pasos** — el workflow se marca `failed`. Sin reintento automático,
   sin rollback automático (un rollback es otro `WorkflowDefinition`
   que se dispara manualmente si hace falta, no un comportamiento
   implícito).
6. DADO cualquier Event de este flujo, CUANDO se procesa, ENTONCES
   actualiza la tabla de proyección correspondiente
   (`WorkflowExecution`/`StepExecution`) además de quedar en `events`
   — el dashboard consulta la proyección, no recalcula desde el
   historial completo.

### Requisito 5 — Visibilidad de ejecuciones

**Historia de usuario:** Como administrador, quiero ver qué
workflows están corriendo y su historial, para saber qué está pasando
en el sistema (Active Operations del dashboard).

#### Criterios de aceptación

1. DADO `WorkflowExecution`/`StepExecution` como tablas de
   proyección, CUANDO se lista, ENTONCES es de **solo lectura** desde
   el admin — mismo criterio que `explorador-eventos`: nunca se edita
   ni se borra una ejecución desde la UI, se genera únicamente por el
   flujo de Commands/Events.

## Fuera de alcance

- Reintentos automáticos de un step fallido.
- Rollback automático — es un workflow distinto, disparado
  explícitamente.
- Ejecución paralela de steps — la secuencia es siempre lineal, por
  `step_order`, un paso a la vez (coherente con "ejecución por lotes
  es secuencial", principio ya establecido en `CLAUDE.md`).
- Notificaciones (email/Slack/etc.) al completar o fallar un workflow
  — dominio aparte.
- Cancelación de una ejecución en curso.
- Autenticación del webhook más allá de un token compartido simple
  (sin HMAC de payload, sin rotación de tokens) — KISS para v1, se
  refuerza si aparece un requisito real de seguridad más estricto.