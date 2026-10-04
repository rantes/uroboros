# Diseño técnico — Duplicar Proyecto

## Alcance

Sin migraciones. Una acción de controlador dedicada que orquesta la
copia completa, reutilizando los mecanismos de cifrado ya construidos
(`ProjectCredential::DecryptedValue()`/`encryptValue()`,
`ProjectConfigFile::DecryptedContent()`/`encryptContent()`).

## `AdminController::duplicateprojectAction()`

```php
public function duplicateprojectAction(): void {
    try {
        $sourceProject = $this->Project->Find((int) ($this->params['id'] ?? 0));

        if ($sourceProject->counter() === 0):
            throw new ControllerException('Proyecto origen no encontrado.', HTTP_404);
        endif;

        $newName = trim($_POST['new_name'] ?? '');
        empty($newName) and throw new ControllerException('Se requiere un nombre para el nuevo proyecto.', HTTP_422);

        $newProject = $this->Project->Niu([
            'name'            => $newName,
            'type'            => $sourceProject->type,
            'repository_url'  => $sourceProject->repository_url,
            // working_directory deliberadamente ausente — queda null
        ]);
        $newProject->Save() or throw new ControllerException((string) $newProject->_error, HTTP_422);

        $this->_duplicateCredentials($sourceProject->id, $newProject->id);
        $this->_duplicateConfigFiles($sourceProject->id, $newProject->id);
        $this->_duplicateWorkflows($sourceProject->id, $newProject->id);

        $this->_response['d']       = $newProject;
        $this->_response['message'] = 'Proyecto duplicado correctamente.';
        $this->_code                = HTTP_201;
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

## `_duplicateCredentials()`

```php
private function _duplicateCredentials(int $sourceProjectId, int $newProjectId): void {
    $credentials = $this->ProjectCredential->Find(['conditions' => [['project_id', $sourceProjectId]]]);

    foreach ($credentials as $credential):
        $copy = $this->ProjectCredential->Niu([
            'project_id' => $newProjectId,
            'name'       => $credential->name,
            'value'      => $credential->DecryptedValue(), // encryptValue() del before_save lo vuelve a cifrar
        ]);
        $copy->Save() or throw new ControllerException("No se pudo duplicar la credencial {$credential->name}.", HTTP_500);
    endforeach;
}
```

## `_duplicateConfigFiles()`

Mismo patrón, usando `DecryptedContent()`/el campo `content` —
confirmar el nombre exacto de los campos reales de
`ProjectConfigFile` (`name`, `format`, `is_secret`, `content`) contra
el modelo real antes de escribir, no asumir desde este documento.

## `_duplicateWorkflows()` — el punto técnico real de este spec

Dos pasadas: primero crear todos los `WorkflowDefinition` nuevos
(sin resolver `workflow_definition_id` todavía, dado que el Workflow
al que podría apuntar quizás ni se ha creado aún en este bucle),
building un mapa `id_viejo => id_nuevo`; luego una segunda pasada que
actualiza `workflow_definition_id` de cada copia usando ese mapa.

```php
private function _duplicateWorkflows(int $sourceProjectId, int $newProjectId): void {
    $sourceWorkflows = $this->WorkflowDefinition->Find(['conditions' => [['project_id', $sourceProjectId]]]);
    $idMap           = [];

    // Pasada 1 — crear todos los Workflows nuevos + sus Steps, sin encadenamiento todavía
    foreach ($sourceWorkflows as $sourceWorkflow):
        $newWorkflow = $this->WorkflowDefinition->Niu([
            'project_id' => $newProjectId,
            'name'       => "{$sourceWorkflow->name} (copia)",
        ]);
        $newWorkflow->Save() or throw new ControllerException("No se pudo duplicar el workflow {$sourceWorkflow->name}.", HTTP_500);
        $idMap[$sourceWorkflow->id] = $newWorkflow->id;

        $this->_duplicateSteps($sourceWorkflow->id, $newWorkflow->id);
    endforeach;

    // Pasada 2 — remapear workflow_definition_id usando el mapa ya completo
    foreach ($sourceWorkflows as $sourceWorkflow):
        if (!empty($sourceWorkflow->workflow_definition_id) and isset($idMap[$sourceWorkflow->workflow_definition_id])):
            $newWorkflow = $this->WorkflowDefinition->Find($idMap[$sourceWorkflow->id]);
            $newWorkflow->workflow_definition_id = $idMap[$sourceWorkflow->workflow_definition_id];
            $newWorkflow->Save() or throw new ControllerException('No se pudo remapear el encadenamiento de workflows duplicados.', HTTP_500);
        endif;
    endforeach;
}
```

> **Verificar antes de implementar**: si `workflow_definition_id`
> del Workflow origen apunta a un Workflow de **otro** Proyecto (no
> del Proyecto que se está duplicando) — ese caso no tiene un
> `id_nuevo` en el mapa (`isset($idMap[...])` sería `false`). Decide
> qué hacer: ¿dejar la copia sin ese encadenamiento (más seguro, no
> asumir una relación cruzada que no se pidió duplicar), o mantenerlo
> apuntando al Workflow del otro Proyecto tal cual (podría tener
> sentido si ese otro Proyecto es compartido, no parte de esta
> duplicación)? No asumido en este documento — repórtalo y decide con
> el usuario si aparece un caso real así.

## `_duplicateSteps()`

Copia directa, sin encadenamiento interno que resolver (los
`WorkflowStepDefinition` no tienen auto-referencia, solo pertenecen a
su `WorkflowDefinition` vía `workflow_definition_id` — ya resuelto al
crearlos dentro del `foreach` de la Pasada 1).

## Vista

Botón/acción "Duplicar" en el menú `dmb-more-options` de cada fila de
`project_list.phtml` — mismo patrón que "Editar"/"Eliminar"/"Ver
workflows" ya usados. Al hacer click, pide el nombre nuevo (un
diálogo simple con un input, o un panel pequeño — confirma qué
mecanismo real ya existe para pedir un solo valor antes de disparar
una acción, no inventes uno nuevo si ya hay un patrón establecido).

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.