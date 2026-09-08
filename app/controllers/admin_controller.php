<?php
namespace App\Controllers;

use Exception;
use App\Controllers\MainController;
use App\Controllers\AdminBaseTrait;
use App\Controllers\ControllerException;
use App\Commands\ExecuteWorkflowCommand;
use App\Buses\CommandBus;

use DumboPHP\Secrets;

use function DumboPHP\strGenerate;
use function DumboPHP\Singulars;
use function DumboPHP\Camelize;

class AdminController extends MainController {
    use AdminBaseTrait;

    public bool $paginate = true;

    public function __construct() {
        parent::__construct();
        $this->operationalShell = true;
        $this->helper[]  = 'OperationalShell';
        $this->helper[]  = 'HealthMetrics';
        $this->helper[]  = 'ProjectDirectory';
        $this->_readOnlyModels = ['event', 'workflow_execution', 'step_execution'];
        $this->_actions  = [
            'projects',
            'groups',
            'events',
            'workflow_definitions',
            'workflow_step_definitions',
            'workflow_executions',
            'step_executions',
            'project_config_files',
            'project_credentials',
        ];
        $this->noyes = ['no', 'si'];
        $this->statuses = ['Inactivo', 'Activo'];
    }
    /**
     * Set additional behaviors before filter hook
     */
    public function _additional_before_filter(): void {
        $this->_prepare_data();
        switch ($this->_prevAction):
            case 'projects':
                if (in_array($this->params[0] ?? null, ['edit', 'add'])):
                    $this->groups = $this->Group->Find();
                    $this->selectedGroupIds = [];
                    if ($this->params[0] === 'edit' and !empty($this->params[1])):
                        $assigned = $this->ProjectGroup->Find(['conditions' => [['project_id', (int) $this->params[1]]]]);
                        foreach ($assigned as $projectGroup):
                            $this->selectedGroupIds[] = (int) $projectGroup->group_id;
                        endforeach;
                    endif;
                endif;
            break;
            case 'workflow_definitions':
                if (in_array($this->params[0] ?? null, ['edit', 'add'])):
                    $this->projects = $this->Project->Find();

                    // Candidatos de encadenamiento — nunca incluye al
                    // propio registro en edición (Paso 9,
                    // encadenamiento-workflows: ciclo trivial de un
                    // nodo, la única prevención de ciclos de v1).
                    $currentId = ($this->params[0] === 'edit') ? (int) ($this->params[1] ?? 0) : 0;
                    $this->cascadeCandidates = $this->WorkflowDefinition->Find([
                        'conditions' => empty($currentId) ? '' : "`id`<>'{$currentId}'",
                    ]);
                endif;

                // Filtro opcional por Project — mismo patrón que
                // project_config_files/project_credentials, pero acá
                // el parámetro es opcional: "Operaciones" en el
                // sidebar lista todos los workflows sin filtrar,
                // mientras que "Ver workflows" desde un Project llega
                // con project_id en la query string.
                $this->projectId = (int) ($this->params['project_id'] ?? 0);
                empty($this->projectId)
                    or ($this->_listConditions = "`project_id`='{$this->projectId}'");
            break;
            case 'workflow_step_definitions':
                // Colección anidada, no una lista global — cada
                // pantalla de gestión de pasos referencia un único
                // WorkflowDefinition por parámetro (design.md,
                // "Gestión de pasos anidados").
                $this->workflowDefinitionId = (int) ($this->params['workflow_definition_id'] ?? 0);
                $this->_listConditions = "`workflow_definition_id`='{$this->workflowDefinitionId}'";
            break;
            case 'project_config_files':
                // Colección anidada, mismo patrón que
                // workflow_step_definitions — cada pantalla referencia
                // un único Project por parámetro.
                $this->projectId = (int) ($this->params['project_id'] ?? 0);
                $this->_listConditions = "`project_id`='{$this->projectId}'";
            break;
            case 'project_credentials':
                // Colección anidada, mismo patrón que
                // project_config_files — cada pantalla referencia un
                // único Project por parámetro.
                $this->projectId = (int) ($this->params['project_id'] ?? 0);
                $this->_listConditions = "`project_id`='{$this->projectId}'";
            break;
        endswitch;
    }


    /**
     * Disparo manual de un WorkflowDefinition (Requisito 2). No pasa
     * por AdminBaseTrait — es una acción de dominio real (despacha un
     * Command), no un registro que se guarda. Responde 202: la
     * ejecución sigue en background, procesada por
     * WorkflowRunnerController vía cron. Ver design.md.
     */
    public function executeworkflowAction(): void {
        $this->layout = null;

        try {
            // Lista blanca explícita — trigger_type nunca se toma
            // crudo del request. Sin esto, cualquiera podría forzar
            // trigger_type=webhook desde este botón manual y
            // falsificar el origen real de un disparo, rompiendo la
            // trazabilidad que trigger_type existe para garantizar
            // (vista-ejecucion/design.md, "Extender
            // executeworkflowAction() para aceptar trigger_type").
            $workflowDefinitionId  = (int) ($this->params['id'] ?? 0);
            $allowedManualTriggers = ['manual', 'retry'];
            $triggerType           = $this->params['trigger_type'] ?? 'manual';
            in_array($triggerType, $allowedManualTriggers, true) or ($triggerType = 'manual');

            empty($workflowDefinitionId)
                and throw new ControllerException('workflow_definition_id requerido', HTTP_400);

            (new CommandBus())->Dispatch(
                new ExecuteWorkflowCommand($workflowDefinitionId, $triggerType)
            );

            $this->_code = HTTP_202;
            $this->_response['message'] = 'En cola, se procesa en el próximo ciclo (hasta 1 minuto)';
        } catch (ControllerException $e) {
            $this->_code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $this->_code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($this->_code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }

    /**
     * Vista de detalle de una WorkflowExecution — de solo lectura
     * salvo las acciones puntuales (Reintentar/Rollback), que
     * despachan Commands vía executeworkflowAction(), nunca editan el
     * registro directo. 'workflowexecutiondetail' NO está en
     * $this->_actions deliberadamente, mismo criterio que
     * executeworkflowAction()/saveprojectAction(). Ver
     * .claude/specs/vista-ejecucion/design.md.
     *
     * No se toca $this->layout — hereda el shell admin por defecto
     * (MainController::$layout = 'layout'), igual que
     * workflow_execution_list/workflow_step_definition_list. El
     * layout=null de otras acciones de este controlador es solo para
     * las que responden JSON puro (executeworkflow, saveproject,
     * healthmetrics, syncconfigfiles) — esta renderiza una página real
     * dentro del shell, no un endpoint de API.
     */
    public function workflowexecutiondetailAction(): void {
        // Convención de controladores (code-conventions.md, "Toda
        // acción dentro de try/catch") — acción de página completa,
        // así que el catch responde con $this->render = ['text' =>
        // ...] + setResponseCode(), nunca respondToAJAX().
        try {
            // Orden real de los pasos — verificado con datos reales
            // (workflow_definition_id=14, ver reporte): StepExecution.id
            // ASC coincide con step_order en el flujo normal, por
            // construcción del chain OnWorkflowStartedReaction ->
            // OnStepCompletedReaction (un StepExecution se crea solo
            // cuando el anterior completa). Pero workflow_step_definitions
            // y step_executions también tienen CRUD genérico en
            // $this->_actions — un StepExecution podría, en teoría,
            // insertarse fuera de ese orden por esa vía. Se ordena por el
            // step_order real (join), no por la coincidencia observada,
            // para no depender de un supuesto fuera del flujo normal.
            $executionId = (int) ($this->params['id'] ?? 0);

            $this->execution = $this->WorkflowExecution->Find($executionId);

            // Guard — sin esto, un id inexistente (ej. una ejecución de
            // prueba ya borrada) no fallaba: Find() de un belongs_to sobre
            // un registro vacío cae en el bug de __call() en dumbophp.php
            // (foreign key vacía -> condición '1=1' sin filtro) y termina
            // renderizando datos "fantasma" de OTRO registro real
            // cualquiera, en vez de un 404 real. if positivo (no
            // return anticipado) envuelve el resto de la lógica —
            // code-conventions.md, "Control de flujo — return vs throw
            // vs if positivo".
            if ($this->execution->counter() > 0):
                // formatLeadTime() ya existe (HealthMetrics_Helper.php,
                // cargado en el constructor de este controlador) — reutilizado
                // tal cual en vez de duplicar el formateo de segundos.
                $this->duration = null;
                (!empty($this->execution->started_at) and !empty($this->execution->completed_at))
                    and ($this->duration = formatLeadTime((int) $this->execution->completed_at - (int) $this->execution->started_at));

                $this->steps      = $this->StepExecution->Find([
                    'fields'     => 'step_executions.*',
                    'join'       => 'INNER JOIN workflow_step_definitions ON workflow_step_definitions.id = step_executions.workflow_step_definition_id',
                    'conditions' => "step_executions.workflow_execution_id = '{$executionId}'",
                    'sort'       => 'workflow_step_definitions.step_order ASC',
                ]);

                // Timeline agrupa por type — se agrupa en PHP, nunca en la
                // vista (design.md).
                $this->stepsByType    = [];
                $this->completedCount = 0;
                $this->failedCount    = 0;
                foreach ($this->steps as $step):
                    $this->stepsByType[$step->workflow_step_definition()->type][] = $step;
                    $step->status === 'completed' and $this->completedCount++;
                    $step->status === 'failed' and $this->failedCount++;
                endforeach;

                $this->failedStep = null;
                if ($this->execution->status === 'failed'):
                    foreach ($this->steps as $step):
                        $step->status === 'failed' and ($this->failedStep = $step);
                    endforeach;
                endif;

                // Rollback — Requisito 3.2 (vista-ejecucion) pide filtrar
                // WorkflowDefinition por type='rollback', pero esa columna NO
                // existe en workflow_definitions (confirmado contra la
                // migración real) — agregarla violaría el alcance explícito
                // de design.md ("Sin migraciones nuevas salvo trigger_type").
                // workflow_step_definitions sí tiene type='rollback' ya
                // real y usado — un Workflow de rollback se identifica por
                // tener al menos un paso de ese tipo, sin inventar columnas
                // nuevas.
                $this->rollbackWorkflow = null;
                if (!empty($this->failedStep)):
                    $projectId         = (int) $this->execution->workflow_definition()->project_id;
                    $rollbackCandidate = $this->WorkflowDefinition->Find([
                        ':first',
                        'fields'     => 'workflow_definitions.*',
                        'join'       => 'INNER JOIN workflow_step_definitions ON workflow_step_definitions.workflow_definition_id = workflow_definitions.id',
                        'conditions' => "workflow_definitions.project_id = '{$projectId}' AND workflow_step_definitions.type = 'rollback'",
                    ]);
                    $rollbackCandidate->counter() > 0 and ($this->rollbackWorkflow = $rollbackCandidate);
                endif;

                $this->render = ['file' => 'admin/workflow_execution_detail.phtml'];
            else:
                $this->setResponseCode(HTTP_404);
                $this->render = ['text' => 'Ejecución no encontrada.'];
            endif;
        } catch (\Exception $e) {
            $this->setResponseCode(HTTP_500);
            $this->render = ['text' => 'Ocurrió un error al cargar la ejecución.'];
        }
    }

    /**
     * Acción dedicada para Proyecto — el CRUD genérico de
     * AdminBaseTrait no puede sincronizar el pivote project_groups
     * desde un dmb-select multiple (Find()/Save() no conocen esa
     * columna). 'saveproject' NO está en $this->_actions
     * deliberadamente: _prepare_data() nunca debe interceptarla. Ver
     * design.md, "Asignación de Grupos — acción dedicada".
     *
     * Confirmado contra AdminBaseTrait::_create_reg()/_update_reg()
     * (código real, no el snippet ilustrativo de design.md): ambos
     * simplemente llaman Niu($array) con el array completo del POST
     * (incluyendo 'id' cuando existe) y dejan que Save() detecte
     * INSERT/UPDATE según la presencia de $this->id. No hay ningún
     * Find()+asignación campo a campo — por eso aquí tampoco hace
     * falta.
     */
    public function saveprojectAction(): void {
        $this->layout = null;
        $code = HTTP_200;

        try {
            $data = $_POST['project'] ?? [];
            empty($data) and throw new ControllerException('Datos de proyecto requeridos', HTTP_422);

            $groupIds = $data['groups'] ?? [];
            unset($data['groups']);
            !empty($data['id']) and ($data['id'] = (int) $data['id']);

            $project = $this->Project->Niu($data);

            // working_directory relativo — hallazgo real de
            // producción (.claude/specs/gestion-proyectos/tasks.md,
            // "Hallazgo real de producción"): resuelto contra el cwd
            // del proceso de cron, un valor relativo puede terminar
            // apuntando al `.git` más cercano hacia arriba en el
            // árbol — que puede ser el propio repositorio de
            // Uroboros. Se valida aquí, al guardar, no solo en
            // RunStepCommandHandler (que se mantiene como red de
            // seguridad adicional ante un directorio que cambia de
            // estado entre el guardado y la ejecución real).
            if (!empty($project->working_directory)):
                str_starts_with($project->working_directory, '/')
                    or throw new ControllerException('El directorio de trabajo debe ser una ruta absoluta (debe iniciar con /).', HTTP_422);

                ensureWritableProjectDirectory($project->working_directory);
                is_dir($project->working_directory)
                    or throw new ControllerException("No se pudo crear el directorio de trabajo: {$project->working_directory}", HTTP_500);

                is_writable($project->working_directory)
                    or throw new ControllerException("El directorio de trabajo existe pero no tiene permisos de escritura ({$project->working_directory}). Ajusta los permisos (ej. chown/chmod) e intenta de nuevo.", HTTP_422);
            endif;

            $project->Save()
                or throw new ControllerException((string) $project->_error, HTTP_422);

            $this->_importExistingEnvFiles($project);

            // Sync simple del pivote: borra todas las filas existentes
            // de este proyecto y recrea desde la selección actual.
            // Más simple que diffear altas/bajas — la escala (pocos
            // grupos por proyecto) no justifica un diff real.
            $existing = $this->ProjectGroup->Find(['conditions' => [['project_id', $project->id]]]);
            foreach ($existing as $projectGroup):
                $projectGroup->Delete();
            endforeach;

            foreach ($groupIds as $groupId):
                $newProjectGroup = $this->ProjectGroup->Niu([
                    'project_id' => $project->id,
                    'group_id'   => (int) $groupId,
                ]);
                $newProjectGroup->Save()
                    or throw new ControllerException((string) $newProjectGroup->_error, HTTP_422);
            endforeach;

            $this->_response['d'] = $project;
            $this->_response['message'] = 'Proyecto guardado satisfactoriamente';
        } catch (ControllerException $e) {
            $code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }

    /**
     * Botón "Sincronizar al disco" — escribe los archivos de
     * configuración descifrados de un Proyecto en su
     * working_directory real. 'syncconfigfiles' NO está en
     * $this->_actions deliberadamente, mismo criterio que
     * executeworkflowAction()/saveprojectAction(). Ver design.md,
     * "Acción de sincronización al disco".
     */
    public function syncconfigfilesAction(): void {
        $this->layout = null;
        $this->_code = HTTP_200;

        try {
            $projectId = (int) ($this->params['id'] ?? 0);
            $project   = $this->Project->Find($projectId);

            empty($project->working_directory)
                and throw new ControllerException('El proyecto no tiene working_directory configurado.', HTTP_422);

            ensureWritableProjectDirectory($project->working_directory);
            is_dir($project->working_directory)
                or throw new ControllerException("No se pudo crear el directorio de trabajo: {$project->working_directory}", HTTP_500);

            $files = $this->ProjectConfigFile->Find(['conditions' => [['project_id', $projectId]]]);

            foreach ($files as $file):
                $this->_writeConfigFile($project->working_directory, $file);
            endforeach;

            $this->_response['message'] = 'Archivos sincronizados correctamente.';
        } catch (ControllerException $e) {
            $this->_code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $this->_code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($this->_code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }

    /**
     * Requisito 4.3 — nunca escribir fuera de working_directory.
     * Validación por segmentos, no realpath()+str_starts_with()
     * (el snippet ilustrativo de design.md tiene un fallo real: la
     * comparación de prefijos de string sin separador de directorio
     * deja pasar un directorio hermano cuyo nombre empieza igual,
     * ej. working_directory=/home/proj1 vs. resultado
     * /home/proj1-evil/file). Rechazar cualquier segmento '..' o '.'
     * y cualquier ruta absoluta garantiza por construcción que el
     * resultado nunca sale de $baseDir, sin depender de que la ruta
     * ya exista en disco. Verificado con casos reales en Fase 5.
     */
    private function _isSafeRelativePath(string $filename): bool {
        if ($filename === '' or str_contains($filename, "\0")):
            return false;
        endif;
        if (str_starts_with($filename, '/') or preg_match('#^[A-Za-z]:[\\\\/]#', $filename)):
            return false;
        endif;

        $isSafe = true;
        foreach (explode('/', str_replace('\\', '/', $filename)) as $segment):
            in_array($segment, ['', '.', '..'], true) and ($isSafe = false);
        endforeach;

        return $isSafe;
    }

    /**
     * Hallazgo E2E post-cierre: un working_directory ya vinculado
     * puede traer su propio .env/.env.secrets (clonado antes de que
     * Uroboros gestionara el archivo) — se importa una sola vez, sin
     * pisar lo que ya esté rastreado. Ver design.md,
     * "Auto-importación de .env/.env.secrets existentes".
     */
    private function _importExistingEnvFiles($project): void {
        $envFileNames = ['.env', '.env.secrets'];

        if (!empty($project->working_directory) and is_dir($project->working_directory)):
            foreach ($envFileNames as $filename):
                $targetPath = $project->working_directory . DIRECTORY_SEPARATOR . $filename;

                if (is_file($targetPath)):
                    $alreadyTracked = $this->ProjectConfigFile->Find([
                        'conditions' => [['project_id', $project->id], ['filename', $filename]],
                    ]);

                    if ($alreadyTracked->counter() === 0):
                        $newFile = $this->ProjectConfigFile->Niu([
                            'project_id' => $project->id,
                            'filename'   => $filename,
                            'format'     => 'ini',
                            'content'    => file_get_contents($targetPath),
                            'is_secret'  => ($filename === '.env.secrets') ? 1 : 0,
                        ]);
                        $newFile->Save(); // si falla validación de formato, se omite silenciosamente — no bloquea el guardado del Project
                    endif;
                endif;
            endforeach;
        endif;
    }

    private function _writeConfigFile(string $baseDir, $file): void {
        $this->_isSafeRelativePath($file->filename)
            or throw new Exception("Nombre de archivo inválido: {$file->filename}");

        $target = rtrim($baseDir, '/') . '/' . $file->filename;

        ensureWritableProjectDirectory(dirname($target));

        file_put_contents($target, $file->DecryptedContent()) !== false
            or throw new Exception("No se pudo escribir {$file->filename}");
    }

    /**
     * Fetch de "Mostrar" (dmb-reveal-secret) para archivos is_secret —
     * el contenido descifrado nunca va en el HTML inicial del listado.
     */
    public function showconfigfilecontentAction(): void {
        $this->layout = null;
        $this->_code = HTTP_200;

        try {
            $id   = (int) ($this->params['id'] ?? 0);
            $file = $this->ProjectConfigFile->Find($id);

            empty($file->id)
                and throw new ControllerException('Archivo no encontrado.', HTTP_404);

            $this->_response['d'] = ['content' => $file->DecryptedContent()];
        } catch (ControllerException $e) {
            $this->_code = $e->getCode();
            $this->_response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $this->_code = HTTP_500;
            $this->_response['message'] = $e->getMessage();
        } finally {
            $this->setResponseCode($this->_code);
            $this->respondToAJAX(json_encode($this->_response));
        }
    }

}
