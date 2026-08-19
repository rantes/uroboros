# Diseño técnico — Ejecución de Workflows

## Decisión central — separar "decidir qué sigue" de "ejecutar el script externo"

El núcleo OEM tal como está (`CommandBus`/`EventBus`) es **100%
síncrono e in-process** (Decisión 2 de `nucleo-oem`, deliberada). Si
`ExecuteStepCommand` corriera el script externo dentro de ese mismo
ciclo síncrono, el request HTTP que disparó el workflow (manual o
webhook) quedaría bloqueado hasta que el script externo termine —
podrían ser minutos. Eso viola el Requisito 4.3 y contradice todo lo
que ya establecimos sobre PHP efímero y ejecución por lotes.

**Solución: dos Commands distintos, no uno.**

1. **`QueueStepCommand`** — rápido, in-process, dispatcheable
   directamente desde una Reaction. Solo crea la fila `StepExecution`
   (`status = 'pending'`) y el Event `StepQueued`. Nunca ejecuta el
   script externo.
2. **`RunStepCommand`** — el que sí ejecuta el script externo
   (bloqueante, puede tardar). **Solo se despacha desde el
   controlador de background disparado por `cron`**, nunca desde una
   Reaction ni desde un request HTTP. Mismo patrón ya establecido
   (Decisión 7 de `nucleo-oem`: cola en BD + controlador de
   background + `cron`) — `StepExecution` con `status = 'pending'`
   **es** la cola, no se inventa una tabla nueva para eso.

Esto mantiene el ciclo Command→Event→Reaction rápido y síncrono donde
ya lo es, y aísla lo lento (ejecución real) al único lugar del
proyecto donde ya sabemos que es seguro tenerlo.

## Migraciones

### `create_workflow_definitions.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `name` | VARCHAR(255) | NOT NULL, único |
| `description` | TEXT | NULL |
| `project_id` | INTEGER | NOT NULL — `belongs_to Project` |
| `status` | INTEGER | `default=0` — activo/inactivo |
| `webhook_token` | VARCHAR(64) | NOT NULL — secreto para el Requisito 3 |

### `create_workflow_step_definitions.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `workflow_definition_id` | INTEGER | NOT NULL |
| `name` | VARCHAR(255) | NOT NULL |
| `type` | VARCHAR(50) | NOT NULL — lista cerrada, validada en el modelo: `build`/`deploy`/`rollback`/`migration`/`verification`/`custom` (catch-all para pasos que no encajan en una categoría semántica, ej. `rm`/`ln`/limpieza) |
| `command` | TEXT | NOT NULL — script/comando externo, **sin restricción de qué comandos se permiten**. Modelo de confianza: quien configura un `WorkflowStepDefinition` ya tiene acceso de shell al servidor (mismo devops, sin ACL) — no es una superficie de ataque nueva. Si en el futuro hay más de 1-2 personas con acceso al admin, esto debe revisarse — no resuelto ahora, anotado a propósito. |
| `step_order` | INTEGER | NOT NULL — **nunca `order`**, palabra reservada en MySQL |

Índice: `Add_Index(['workflow_definition_id', 'step_order'])`.

### `create_workflow_executions.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `workflow_definition_id` | INTEGER | NOT NULL |
| `status` | VARCHAR(20) | NOT NULL — lista cerrada: `pending`/`running`/`completed`/`failed` |
| `trigger_type` | VARCHAR(20) | NOT NULL — `manual`/`webhook` |
| `started_at` | INTEGER | NULL |
| `completed_at` | INTEGER | NULL |

### `create_step_executions.php`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `workflow_execution_id` | INTEGER | NOT NULL |
| `workflow_step_definition_id` | INTEGER | NOT NULL |
| `status` | VARCHAR(20) | NOT NULL — misma lista cerrada |
| `exit_code` | INTEGER | NULL |
| `output` | TEXT | NULL |
| `started_at` | INTEGER | NULL |
| `completed_at` | INTEGER | NULL |

Índice: `Add_Index(['status', 'workflow_execution_id'])` — **orden
importa**: el controlador de background consulta *solo* por
`status = 'pending'`, sin filtrar por `workflow_execution_id`, así
que el índice debe empezar por `status` para servir esa consulta
directamente. (Corregido — la versión original del documento tenía
el orden invertido, que habría sido óptimo para una consulta que no
existe en este diseño.)

> **Verificar antes de implementar:** ningún nombre de columna de
> arriba debería ser palabra reservada de MySQL, pero dado el
> historial reciente (`groups`, y casi `order`), confirma cada uno
> contra la lista real de reservadas de la versión de MySQL en uso
> antes de crear las migraciones — no asumido solo por revisión
> visual.

## Modelos

Cuatro modelos ActiveRecord estándar (`WorkflowDefinition`,
`WorkflowStepDefinition`, `WorkflowExecution`, `StepExecution`), con
`belongs_to`/`has_many` según las FKs de arriba, sanitización
`htmlentities()` en campos de texto libre (`name`, `description` —
**no** en `command`/`output`, que son contenido técnico/logs, no
texto para render HTML — mismo criterio de excepción documentada ya
usado en `Event.payload`), y validación de listas cerradas
(`type`, `status`, `trigger_type`) a mano en `before_save`, igual que
`Project::validateType()`.

## Convención de `aggregate_type`/`aggregate_id` de los Events

> Decidido durante la implementación (Parte 2), no estaba explícito
> antes — se formaliza aquí para que quede en el spec, no solo en el
> código:

- Eventos de ciclo de vida del workflow (`WorkflowStarted`,
  `WorkflowCompleted`, `WorkflowFailed`) usan
  `aggregate_type = 'WorkflowExecution'`, `aggregate_id` = el id de
  esa `WorkflowExecution`.
- Eventos de paso (`StepQueued`, `StepCompleted`, `StepFailed`) usan
  `aggregate_type = 'StepExecution'`, `aggregate_id` = el id de ese
  `StepExecution`.
- El `payload` de cada Event lleva los IDs cruzados que su Reaction
  correspondiente necesita (ej. `StepCompleted` incluye
  `workflow_execution_id` además del propio `step_execution_id`),
  para evitar queries adicionales donde es barato incluirlos
  directamente.

## Commands y Handlers

```text
ExecuteWorkflowCommand(workflowDefinitionId, triggerType)
    → Handler: crea WorkflowExecution(status=pending), Event WorkflowStarted

QueueStepCommand(workflowExecutionId, stepDefinitionId)
    → Handler: crea StepExecution(status=pending), Event StepQueued

RunStepCommand(stepExecutionId)
    → Handler: SOLO despachado desde el controlador de background.
      Ejecuta el `command` externo (ver "Ejecución real" abajo),
      actualiza StepExecution con exit_code/output/completed_at,
      crea Event StepCompleted o StepFailed según el resultado.

CompleteWorkflowCommand(workflowExecutionId)
    → Handler: actualiza WorkflowExecution(status=completed,
      completed_at), Event WorkflowCompleted

FailWorkflowCommand(workflowExecutionId, failedStepExecutionId)
    → Handler: actualiza WorkflowExecution(status=failed,
      completed_at), Event WorkflowFailed
```

## Reactions

```text
OnWorkflowStartedReaction
    → despacha QueueStepCommand para el primer WorkflowStepDefinition
      (menor step_order)

OnStepQueuedReaction
    → no hace nada por sí sola en v1 — el controlador de background
      es quien recoge las filas `pending`, no una Reaction. (Se
      declara igual, sin lógica, para que quede explícito en
      config/reactions_map.php que StepQueued no dispara nada
      síncrono — evita la pregunta de "¿por qué no reacciona nada?"
      en el futuro.)

OnStepCompletedReaction
    → busca el siguiente WorkflowStepDefinition por step_order dentro
      del mismo WorkflowExecution
    → si existe: despacha QueueStepCommand para ese paso
    → si no existe: despacha CompleteWorkflowCommand

OnStepFailedReaction
    → despacha FailWorkflowCommand — no se encola ningún paso más
```

## Controlador de background — ejecución real

**Corregido tras discusión de escenario real:** procesar un solo paso
por tick de `cron` desperdicia minutos reales en workflows de varios
pasos (con `cron` cada 60s, un workflow de 7 pasos tardaría hasta 7
minutos solo en latencia de sondeo, aunque cada comando individual
tome segundos). El controlador de background debe **seguir en bucle
mientras haya trabajo pendiente**, dentro de la misma invocación —
`cron` solo determina cuánto tarda en *notar* que llegó un workflow
nuevo (hasta un intervalo de latencia inicial), no cuánto tarda en
procesar cada paso subsiguiente una vez que ya está corriendo.

Nuevo controlador (`WorkflowRunnerController extends
DumboPHP\Controller`, sin sesión/HTTP), invocado vía `dumbo run
workflow_runner/processpending`, disparado por `cron`:

```php
public function processpendingAction(): void {
    while (true):
        $next = $this->StepExecution->Find([
            ':first',
            'conditions' => "`status`='pending'",
            'sort'       => '`id` ASC',
        ]);

        if (empty($next->id)):
            break;
        endif;

        (new CommandBus())->Dispatch(new RunStepCommand($next->id));
    endwhile;
}
```

> **Corregido — mi snippet original tenía un bug de sintaxis real:**
> `empty($next->id) and break;` no es válido en PHP — `break` es una
> sentencia de control de flujo, no una expresión, y no puede ir a la
> derecha de `and`. El agente lo detectó al implementar (no compilaba)
> y lo corrigió con `if`/`endif`. Documentado aquí para que quede
> como referencia correcta, no como el error original.

Sigue siendo estrictamente secuencial — nunca dos `RunStepCommand` a
la vez, un `StepExecution` a la vez, coherente con "ejecución por
lotes es secuencial" (`CLAUDE.md`). El bucle termina naturalmente
cuando no queda nada `pending` (incluye lo que encoló la propia
corrida — si al procesar el paso 1 se encola el paso 2, el mismo
`while` lo recoge sin esperar al siguiente `cron`).

> **Intervalo de `cron` — decisión del usuario, no asumida aquí.**
> Determina solo la latencia de *arranque* (cuánto tarda en notar un
> workflow nuevo), no la latencia entre pasos consecutivos (eso ya lo
> resuelve el `while`). Confirmar antes de implementar.

**UX del disparo (Requisito 2):** la respuesta `202` debe comunicar
honestamente la granularidad real — algo como "en cola, se procesa en
el próximo ciclo" en vez de prometer un timestamp exacto que `cron`
no puede garantizar al segundo.

## Ejecución real del script externo

Dentro de `RunStepCommandHandler`, usar `exec()`/`shell_exec()` nativo
de PHP (aprovechamiento de lo existente — nada de librerías de
Composer para esto):

```php
exec($stepDefinition->command . ' 2>&1', $outputLines, $exitCode);
```

> **Riesgos aceptados para v1, documentados a propósito — decisión
> confirmada, se revisan después, no bloquean este spec:**
> - Sin timeout — un script externo colgado bloquea el `cron` de
>   background indefinidamente.
> - Permisos del usuario que corre `cron` deben poder ejecutar los
>   comandos configurados — no se valida en este spec.
> - Sin límite de tamaño de `output` — un script muy verboso podría
>   generar un TEXT enorme. No se trunca en v1.

## Disparo manual (Requisito 2)

Nueva acción de controlador (no pasa por `AdminBaseTrait`):

```php
public function executeAction(): void {
    // ... valida sesión (estándar), obtiene workflow_definition_id de params
    (new CommandBus())->Dispatch(
        new ExecuteWorkflowCommand($workflowDefinitionId, 'manual')
    );
    // responde 202 Accepted — la ejecución sigue en background
}
```

Botón en la vista: `dmb-button-action` con `behavior="ajax"` apuntando
a esta acción — no `_create_reg()` genérico.

## Disparo por webhook (Requisito 3)

Nuevo controlador público (`WebhookController`), con
`exceptsBeforeFilter` para toda su superficie (sin sesión/CSRF, es
origen externo):

```php
public function triggerAction(): void {
    $definition = $this->WorkflowDefinition->Find([
        ':first',
        'conditions' => [['webhook_token', $this->params['token'] ?? '']],
    ]);

    empty($definition)
        and throw new ControllerException('Token inválido', HTTP_401);

    (new CommandBus())->Dispatch(
        new ExecuteWorkflowCommand($definition->id, 'webhook')
    );
}
```

## Gestión de pasos anidados (Requisito 1.3) — confirmado

**Decisión tomada:** opción 1 — `WorkflowStepDefinition` como entidad
CRUD plana dentro del mecanismo genérico (`AdminBaseTrait`), agregando
`'workflow_step_definitions'` a `$this->_actions`, gestionada en
pantalla separada referenciando `workflow_definition_id` por
parámetro. Simple, coherente con "empezar simple, generalizar cuando
haga falta". Si la UX resulta insuficiente en el uso real, la opción 2
(componente DumboJS de edición anidada) queda como extensión futura,
no como algo que este spec deba resolver ahora.

## Patrón — proyecto encadenado (fire-and-forget)

No es un tipo de step nuevo ni un mecanismo especial — es un
`WorkflowStepDefinition` normal (`type = 'custom'`) cuyo `command` es
un `curl` al webhook del otro proyecto:

```bash
curl -X POST https://uroboros.rantes.local/webhooks/trigger?token=...
```

Confirmado por el usuario: **fire-and-forget** — el step se marca
`completed` en cuanto el `curl` recibe `202` (el proyecto encadenado
*empezó*, no *terminó*). Sin retroalimentación entre workflows. La
condición "solo se activa si el padre terminó bien" ya se cumple
gratis con el diseño existente: si cualquier step anterior falla,
`OnStepFailedReaction` detiene la cadena y este step (el `curl`) nunca
se encola.

## Herramientas de verificación — cuál usar según el tipo de acción

No todo se prueba con `curl`. Referencia para este spec y los que
sigan:

| Tipo de acción | Herramienta correcta |
| --- | --- |
| Endpoint público sin sesión (`WebhookController`) | `curl` directo |
| Acción de admin detrás de sesión/CSRF (disparo manual, click real) | **DumboChromeDriver** (`.claude/rules/dumbochromedriver.md`) — `curl` simulando sesión a mano es frágil |
| Acción de background sin ruta HTTP (`WorkflowRunnerController`) | CLI: `dumbo run controlador/accion param=val` — nunca `curl` contra una URL que no existe |

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.