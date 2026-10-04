# Diseño técnico — Ejecución Batch por Grupos

## Migración — campo nuevo en la tabla pivote

La relación Proyecto↔Grupo ya existe como tabla pivote (confirmar su
nombre real exacto contra la migración real — `project_groups` según
convención ya usada en otras partes de la sesión, verificar antes de
escribir la migración). Se le agrega:

```php
['field' => 'batch_workflow_definition_id', 'type' => 'INTEGER', 'null' => 'true'],
```

Nullable — un Proyecto puede estar en un Grupo sin tener ningún
Workflow asignado para ejecución batch (Requisito 1.1, "o dejarlo sin
asignar").

## Cambio de esquema — `trigger_type`

`WorkflowExecution`: lista cerrada gana `'batch'`, junto a los ya
existentes (`manual`/`webhook`/`cascade`/`retry`).

## Asignación — editar la vinculación Proyecto↔Grupo

Confirma dónde vive hoy la UI que gestiona qué Proyectos pertenecen a
qué Grupo (`gestion-proyectos`) — ahí se agrega un `dmb-select` con
los `WorkflowDefinition` propios de ese Proyecto específico (filtrado
por `project_id`, no todos los Workflows del sistema), para elegir
cuál corre en ejecución batch de ese Grupo. Vacío/sin selección es un
valor válido.

## `AdminController::executegroupAction()`

```php
public function executegroupAction(): void {
    try {
        $group = $this->ProjectGroup->Find((int) ($this->params['id'] ?? 0));

        if ($group->counter() === 0):
            throw new ControllerException('Grupo no encontrado.', HTTP_404);
        endif;

        $members   = /* resolver los Proyectos miembros + su batch_workflow_definition_id de la tabla pivote */;
        $triggered = [];
        $skipped   = [];

        foreach ($members as $member):
            if (empty($member->batch_workflow_definition_id)):
                $skipped[] = ['project' => $member->project_name, 'reason' => 'Sin workflow asignado para ejecución batch'];
            else:
                (new CommandBus())->Dispatch(new ExecuteWorkflowCommand(
                    (int) $member->batch_workflow_definition_id,
                    'batch'
                ));
                $triggered[] = ['project' => $member->project_name, 'workflow_definition_id' => $member->batch_workflow_definition_id];
            endif;
        endforeach;

        $this->_response['d'] = ['triggered' => $triggered, 'skipped' => $skipped];
        $this->_response['message'] = count($triggered) . ' workflow(s) disparados, ' . count($skipped) . ' proyecto(s) omitidos.';
        $this->_code = HTTP_202;
    } catch (ControllerException $e) {
        $this->_code                = $e->getCode();
        $this->_response['message'] = $e->getMessage();
    } catch (\Exception $e) {
        $this->_code                = HTTP_500;
        $this->_response['message'] = $e->getMessage();
    } finally {
        $this->setResponseCode($this->_code);
        $this->respondToAJAX(json_encode($this->_response));
    }
}
```

> **Verificar antes de implementar**: la firma real de
> `ExecuteWorkflowCommand` — ¿ya acepta `trigger_type` como segundo
> parámetro (dado que `vista-ejecucion` ya lo extendió para
> `'retry'`), o hace falta ajustar su constructor para aceptar
> `'batch'` también? Confirmar contra el código real, no asumir que
> el cambio de `vista-ejecucion` ya cubre este caso nuevo sin
> tocarlo.

## Vista — resultado del disparo batch

Tras "Ejecutar grupo", mostrar el resultado real (disparados vs.
omitidos, con la razón) — no un simple "éxito" genérico. Reutiliza el
mecanismo de diálogo/notificación ya existente para respuestas de
acciones (`dmb-dialog`, ya corregido en rondas anteriores), mostrando
las dos listas con claridad.

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.