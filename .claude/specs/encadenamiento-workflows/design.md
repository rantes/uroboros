# Diseño técnico — Encadenamiento de Workflows

## Cambios de esquema

### `workflow_definitions` — columna nueva

| Campo | Tipo | Notas |
| --- | --- | --- |
| `workflow_definition_id` | INTEGER | NULL — auto-referencia a `workflow_definitions.id` |

Requiere `dumbo migration reset workflow_definitions` (agregar
columna a tabla existente, según convención ya establecida del
proyecto — nunca `Add_Column` a mano, la migración se reescribe y se
resetea).

### `workflow_executions.trigger_type` — valor nuevo en la lista cerrada

Pasa de `'manual'`/`'webhook'` a `'manual'`/`'webhook'`/`'cascade'`.
Actualizar la validación cerrada en el modelo `WorkflowExecution` — no
requiere cambio de esquema (ya es `VARCHAR`), solo el código de
validación.

## Modelo — `WorkflowDefinition`

**Resuelto de verdad — el bug de framework fue corregido por el
usuario y confirmado empíricamente.** La ronda anterior encontró un
bug estructural real en `Core_General_Class::__call()`:
`$classFromCall == get_class($this)` nunca podía ser verdadera para
ningún modelo del proyecto, porque `Camelize()` nunca antepone el
namespace (`'WorkflowDefinition'`) mientras `get_class($this)` en PHP
siempre lo incluye (`'App\Models\WorkflowDefinition'`).

**Fix aplicado por el usuario en DumboPHP**: comparar contra `$short`
(el nombre corto de clase ya calculado más arriba en el mismo método,
vía `end(explode('\\', get_class($this)))`) en vez de
`get_class($this)` completo. Confirmado con el mismo escenario exacto
de la ronda anterior (Workflow A, Workflow B dependiente de A,
Workflow E sin relación):

```
$b->workflow_definition('up')   → count=1, [A]   ✓
$a->workflow_definition('down') → count=1, [B]   ✓
```

Ambas llamadas filtran exactamente el registro correcto — antes
devolvían la tabla completa sin filtrar.

**Solución final:**

```php
$this->has_many_and_belongs_to = ['workflow_definition'];
```

Navegación nativa del framework, sin query manual:
- `$definition->workflow_definition('up')` → el Workflow del que
  depende.
- `$definition->workflow_definition('down')` → todos los Workflows
  que dependen de este.

## Reaction — `OnWorkflowCompletedReaction`

```php
class OnWorkflowCompletedReaction extends \DumboPHP\Controller {
    public function Handle(Event $event): void {
        $payload = json_decode($event->payload, true);
        $completedDefinition = $this->WorkflowDefinition->Find($payload['workflow_definition_id']);

        $chained = $completedDefinition->workflow_definition('down');

        foreach ($chained as $definition):
            (new CommandBus())->Dispatch(
                new ExecuteWorkflowCommand($definition->id, 'cascade')
            );
        endforeach;
    }
}
```

Confirmado (de correcciones anteriores, sigue vigente): el type hint
correcto es `Handle(Event $event)` genérico, el payload se lee con
`json_decode($event->payload, true)`, y el payload de
`WorkflowCompleted` ya incluía `workflow_definition_id` sin cambios
en `ejecucion-workflows`.

## Nota histórica — tres rondas de corrección, todas con evidencia real

Vale la pena dejar registrado el recorrido completo de este campo
específico, como referencia de que "verificar antes de asumir" no es
solo una frase — costó tres rondas reales llegar a la solución
correcta:
1. `belongs_to` con alias — no funciona, el alias es invisible para
   `__call()`.
2. `has_many_and_belongs_to` sin corregir el framework — parecía
   razonable por lectura de código, falló empíricamente (bug de
   namespace).
3. `has_many_and_belongs_to` con el framework corregido — funciona,
   confirmado con los mismos conteos exactos que expusieron el bug.

Registrar en `config/reactions_map.php`:
```php
'WorkflowCompleted' => [OnWorkflowCompletedReaction::class],
```

## Riesgo de ciclos — documentado, no mitigado en código

Si `A.workflow_definition_id = B.id` y
`B.workflow_definition_id = A.id`, cada ejecución exitosa
de uno dispara al otro indefinidamente. **No hay detección de ciclos
en v1** — decisión ya confirmada. Mitigación operativa real: revisar
la configuración de encadenamiento con cuidado al crearla; si ocurre
un loop, se corta manualmente quitando la referencia de cualquiera de
los dos Workflows involucrados (o deshabilitando el `cron`
temporalmente).

## UI — selector en el formulario

`admin/workflow_definition_addedit.phtml`: `dmb-select` nuevo,
`dmb-name="workflow_definition[workflow_definition_id]"`,
poblado con todos los `WorkflowDefinition` existentes (nombre +
proyecto al que pertenece, para que sea identificable — ej. "Deploy
Backend (Proyecto: Backend API)"), opción vacía ("Ninguno") por
defecto.

> **Cuidado con la propia identidad**: al editar un Workflow, no
> debería poder seleccionarse a sí mismo como origen (ciclo trivial
> de un solo nodo) — esto sí vale la pena prevenirlo en la UI/
> validación, es gratis (una condición simple), a diferencia de la
> detección de ciclos general de N nodos que si se descarta.

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.