# Diseño técnico — Vista de Detalle de Ejecución

## Alcance

Sin migraciones nuevas salvo el valor `'retry'` en la validación
cerrada de `trigger_type`. Una acción de controlador dedicada (vista
compuesta de solo lectura, con dos acciones puntuales que sí disparan
Commands).

## Cambio de esquema — `trigger_type`

`WorkflowExecution`: lista cerrada pasa de `'manual'`/`'webhook'`/
`'cascade'` a incluir también `'retry'`. Sin cambio de columna (ya es
`VARCHAR`), solo el código de validación.

## Acción de controlador

```php
public function workflowexecutiondetailAction(): void {
    $this->layout = null; // o el layout real del shell, según cómo
                            // ya se resolvió esto en otras vistas
    $executionId = (int) ($this->params['id'] ?? 0);

    $this->execution = $this->WorkflowExecution->Find($executionId);
    $this->steps     = $this->StepExecution->Find([
        'conditions' => [['workflow_execution_id', $executionId]],
        'sort'       => '`id` ASC',
    ]);

    // Confirmar en implementación: ¿ordenar por step_order real
    // (requiere join/lookup contra workflow_step_definitions) o por
    // id de StepExecution es equivalente en la práctica (dado que
    // se crean en el mismo orden en que se encolan)? No asumido —
    // verificar antes de dar por bueno el orden mostrado.

    $this->failedStep = null;
    if ($this->execution->status === 'failed'):
        foreach ($this->steps as $step):
            $step->status === 'failed' and ($this->failedStep = $step);
        endforeach;
    endif;

    $this->rollbackWorkflow = null;
    if (!empty($this->failedStep)):
        $projectId = /* resolver vía workflow_definition del execution */;
        // Corregido — WorkflowDefinition no tiene columna `type`,
        // solo WorkflowStepDefinition la tiene. Un Workflow de
        // "rollback" se identifica por tener al menos un paso de
        // ese type, no por un campo propio inexistente.
        $rollbackStep = $this->WorkflowStepDefinition->Find([
            ':first',
            'conditions' => [['type', 'rollback']],
            'join'       => 'INNER JOIN workflow_definitions ON workflow_definitions.id = workflow_step_definitions.workflow_definition_id',
            // condición adicional real: workflow_definitions.project_id = $projectId
        ]);
        $rollbackStep->counter() > 0 and ($this->rollbackWorkflow = $rollbackStep->workflow_definition());
    endif;
    ?>
```

> **Corrección real, encontrada en implementación:** el snippet
> original de este documento filtraba `WorkflowDefinition` por
> `type='rollback'` — esa columna nunca existió en esa tabla (error
> de diseño, no de implementación). Corregido a identificar el
> Workflow de rollback por tener al menos un paso
> (`WorkflowStepDefinition.type = 'rollback'`), que sí es una columna
> real ya existente — sin inventar esquema nuevo, respetando el
> alcance de este documento ("sin migraciones nuevas salvo
> `trigger_type`").

> **Verificar antes de implementar:** el orden real de
> `StepExecution` (¿por `id ASC` alcanza, o hace falta el `step_order`
> real de su `WorkflowStepDefinition`?) — no asumido, confirmar con
> un caso real donde el orden de creación y el `step_order`
> configurado pudieran diferir (no debería pasar en el flujo normal,
> pero no se ha verificado explícitamente).

## Vista — estructura de tabs

Un solo archivo `admin/workflow_execution_detail.phtml`, con las
5 secciones (Summary/Timeline/Tasks/Logs/Artifacts) como paneles que
se muestran/ocultan por JS simple (o el componente de tabs que ya
exista en el sistema de componentes — confirmar si hay uno
documentado en `dumbojs-components.md` antes de construir uno nuevo).

- **Summary**: datos directos de `$this->execution` + sus relaciones.
- **Timeline**: `$this->steps` agrupados por `type` — un `foreach`
  anidado (agrupar en PHP antes de renderizar, no en la vista).
- **Tasks**: `$this->steps` en el orden ya resuelto, checklist plano.
- **Logs**: por cada step, un bloque con encabezado (nombre + estado)
  y su `output` en un `<pre>`/contenedor con fuente `JetBrains Mono`
  (ya corregida en la ronda anterior de diseño) y fondo oscuro, según
  sección `15` de `design-guide.md`.
- **Artifacts**: mismo patrón `_widget-empty-state.phtml` ya usado en
  el dashboard para "Próximamente".

## Resumen de fallo + acciones sugeridas

Bloque visible solo si `$this->failedStep` no es null:

```php
<? if (!empty($this->failedStep)): ?>
<div class="failure-summary">
    <h3>Resumen de fallo</h3>
    <p><?= htmlspecialchars($this->failedStep->name); ?> falló.</p>
    <pre><?= htmlspecialchars(substr($this->failedStep->output, 0, 500)); ?></pre>

    <dmb-button-action behavior="ajax" ...>Ver Logs</dmb-button-action>
    <!-- o un simple cambio de tab por JS, sin behavior=ajax, si el
         componente de tabs ya soporta anclar a un tab específico -->

    <dmb-button-action
        behavior="ajax"
        url="/admin/executeworkflow?id=<?= $this->execution->workflow_definition_id; ?>&trigger_type=retry">
        Reintentar
    </dmb-button-action>

    <? if (!empty($this->rollbackWorkflow)): ?>
    <dmb-button-action
        behavior="ajax"
        url="/admin/executeworkflow?id=<?= $this->rollbackWorkflow->id; ?>">
        Rollback
    </dmb-button-action>
    <? endif; ?>
</div>
<? endif; ?>
```

## Extender `executeworkflowAction()` para aceptar `trigger_type`

**Cuidado de seguridad real, no cosmético**: el parámetro
`trigger_type` que llega por request **no puede aceptarse tal cual**
— si se toma directo de `$this->params`, cualquiera podría forzar
`trigger_type=webhook` desde el botón manual, rompiendo la
trazabilidad real de origen que todo este mecanismo existe para
garantizar.

```php
public function executeworkflowAction(): void {
    $allowedManualTriggerTypes = ['manual', 'retry'];
    $triggerType = $this->params['trigger_type'] ?? 'manual';
    in_array($triggerType, $allowedManualTriggerTypes, true) or ($triggerType = 'manual');

    // resto igual, usando $triggerType en vez de 'manual' hardcodeado
}
```

## Link desde el listado

`admin/workflow_execution_list.phtml`: cada fila obtiene un link/botón
"Ver detalle" hacia `workflowexecutiondetailAction()` — mismo patrón
"Ver pasos"/"Ver archivos"/"Ver workflows" ya usado varias veces esta
sesión. No repetir el hueco de navegación que ya costó una ronda
completa antes.

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.