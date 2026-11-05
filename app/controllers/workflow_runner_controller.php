<?php
namespace App\Controllers;

use App\Commands\RunStepCommand;
use App\Commands\FailStepCommand;
use App\Commands\FailWorkflowCommand;
use App\Buses\CommandBus;
use App\Helpers\StepLogStore;
use DumboPHP\Controller;

/**
 * Sin sesión/HTTP — invocado vía `dumbo run
 * workflow_runner/processpending`, disparado por `cron`. Mismo patrón
 * que los Command Handlers/Reactions: extiende DumboPHP\Controller
 * directamente, no MainController.
 */
class WorkflowRunnerController extends Controller {

    /** Sobrescribible (tests); por defecto timeout + gracia. */
    public int $stuckAfterSeconds = STEP_TIMEOUT_SECONDS + STEP_STUCK_GRACE_SECONDS;

    /** Sobrescribible (tests); por defecto StepLogStore sobre STEP_LOG_BASE_PATH. */
    public ?StepLogStore $logStore = null;

    /** Sobrescribible (tests); por defecto STEP_LOG_RETENTION_DAYS. */
    public int $retentionDays = STEP_LOG_RETENTION_DAYS;

    /**
     * Un solo runner a la vez (flock): si ya hay otro, sale en
     * silencio. Cada pasada primero cierra pasos atascados y luego
     * procesa la cadena disponible, secuencialmente. Un paso roto no
     * frena a los demás. Los errores van a STEP_RUNNER_LOG sin
     * depender de php.ini.
     */
    public function processpendingAction(): void {
        $this->render = ['action' => false, 'layout' => false];

        $lock        = null;
        $locked      = false;
        $prevLog     = ini_get('error_log');
        $logDir      = dirname(STEP_RUNNER_LOG);
        is_dir($logDir) or mkdir($logDir, 0775, true);

        ini_set('error_log', STEP_RUNNER_LOG);
        set_exception_handler(function (\Throwable $e): void {
            $this->_log('Excepción no capturada: ' . $e);
        });

        try {
            $lock   = fopen(STEP_RUNNER_LOCK_FILE, 'c');
            $locked = $lock !== false && flock($lock, LOCK_EX | LOCK_NB);

            if ($locked):
                $this->_checkLogBase();
                $this->_failStuckSteps();
                $this->_failOrphanExecutions();
                $this->_purgeOldLogs();
                $this->_sweepOrphanLogs();
                $this->_processPending();
            endif;
        } catch (\Throwable $e) {
            $this->_log('Fallo del runner: ' . $e);
        } finally {
            $locked and flock($lock, LOCK_UN);
            $lock and fclose($lock);
            restore_exception_handler();
            ini_set('error_log', (string) $prevLog);
        }
    }

    private function _processPending(): void {
        $attempted = [0]; // ids ya intentados: garantiza progreso del while

        while (true):
            $next = $this->StepExecution->Find([
                ':first',
                'conditions' => "`status`='pending' AND `id` NOT IN (" . implode(',', $attempted) . ')',
                'sort'       => '`id` ASC',
            ]);

            if (empty($next->id)):
                break;
            endif;

            $attempted[] = (int) $next->id;

            try {
                (new CommandBus())->Dispatch(new RunStepCommand($next->id));
            } catch (\Throwable $e) {
                $this->_log("Paso #{$next->id} falló fuera del handler: " . $e);
            }
        endwhile;
    }

    /**
     * Pasos `running` desde hace más de $stuckAfterSeconds: el runner
     * que los lanzó murió sin cerrarlos. started_at se asigna en el
     * mismo Save() que pone status='running'; updated_at es respaldo.
     */
    private function _failStuckSteps(): void {
        $limit = time() - $this->stuckAfterSeconds;
        $stuck = $this->StepExecution->Find([
            'conditions' => "`status`='running' AND COALESCE(`started_at`, `updated_at`) < {$limit}",
        ]);

        foreach ($stuck as $step):
            try {
                $this->_log("Paso #{$step->id} (ejecución #{$step->workflow_execution_id}) atascado - marcando failed");
                (new CommandBus())->Dispatch(new FailStepCommand(
                    (int) $step->id,
                    'Interrumpido: el runner terminó sin cerrar el paso'
                ));
            } catch (\Throwable $e) {
                $this->_log("No se pudo cerrar el paso atascado #{$step->id}: " . $e);
            }
        endforeach;
    }

    /**
     * Ejecuciones `running` sin ningún paso pending/running cuya última
     * actividad es más vieja que STEP_STUCK_GRACE_SECONDS: algo falló
     * DESPUÉS de cerrar el último paso (evento no insertado, reacción
     * que no encoló el siguiente) y nadie las cierra.
     *
     * "Entre pasos" legítimo: el siguiente paso se encola en el mismo
     * proceso y en milisegundos (StepCompleted -> reacción ->
     * QueueStep), así que mientras una ejecución avanza SIEMPRE hay un
     * paso pending/running. Una ejecución sin pasos activos solo es
     * legítima durante esa ventana de ms; con el lock de un solo
     * runner y un umbral de minutos no puede confundirse. La última
     * actividad es el mayor entre updated_at de la ejecución y el
     * completed_at del último paso (updated_at solo se toca al pasar a
     * running). Rollback no es una fase aparte: es una ejecución
     * normal de otro Workflow con sus propios pasos, así que se rige
     * por la misma regla. Consulta en dos pasos (SQL portable, sin
     * GREATEST) para que corra igual en SQLite y MySQL.
     */
    private function _failOrphanExecutions(): void {
        $limit      = time() - STEP_STUCK_GRACE_SECONDS;
        $candidates = $this->WorkflowExecution->Find([
            'conditions' => "`status`='running' AND `updated_at` < {$limit}"
                . " AND NOT EXISTS (SELECT 1 FROM `step_executions` WHERE `step_executions`.`workflow_execution_id` = `workflow_executions`.`id`"
                . " AND `step_executions`.`status` IN ('pending','running'))",
        ]);

        foreach ($candidates as $execution):
            try {
                $lastStep = $this->StepExecution->Find([
                    ':first',
                    'conditions' => [['workflow_execution_id', (int) $execution->id]],
                    'sort'       => '`completed_at` DESC',
                ]);
                $lastActivity = max((int) $execution->updated_at, (int) ($lastStep->completed_at ?? 0));

                if ($lastActivity < $limit):
                    $this->_log("Ejecución #{$execution->id} huérfana (sin pasos activos) - marcando failed");
                    (new CommandBus())->Dispatch(new FailWorkflowCommand(
                        (int) $execution->id,
                        null,
                        'Interrumpido: la ejecución quedó sin pasos activos'
                    ));
                endif;
            } catch (\Throwable $e) {
                $this->_log("No se pudo cerrar la ejecución huérfana #{$execution->id}: " . $e);
            }
        endforeach;
    }

    private function _store(): StepLogStore {
        $this->logStore ??= new StepLogStore();

        return $this->logStore;
    }

    /**
     * Al arrancar: la base de logs debe existir y ser escribible. Si no
     * se puede, se registra con un mensaje claro; los pasos igual se
     * cierran con su estado real (write() devuelve el error al handler).
     */
    private function _checkLogBase(): void {
        $base = $this->_store()->ensureBase();

        $base['ready'] or $this->_log('Carpeta de logs de pasos NO disponible: ' . $base['error']);
    }

    /**
     * Retención: pasos con archivo cuyo fin es anterior al umbral. Borra el
     * archivo (y la carpeta si queda vacía), vacía output_path y
     * conserva output_size, output_checksum y output_tail. Lote acotado;
     * un fallo en un elemento no detiene la pasada.
     */
    private function _purgeOldLogs(): void {
        $threshold = time() - ($this->retentionDays * 86400);
        $store     = $this->_store();

        try {
            $expired = $this->StepExecution->Find([
                'conditions' => "`output_path` <> '' AND COALESCE(`completed_at`, `updated_at`) < {$threshold}",
                'sort'       => '`id` ASC',
                'limit'      => STEP_LOG_PURGE_BATCH,
            ]);

            foreach ($expired as $step):
                try {
                    if ($store->delete($step->output_path)):
                        // Save() ignora propiedades null (isset): 'sin archivo' se escribe ''
                        // (la BD lo lee igual que NULL); la consulta usa <> ''.
                        $step->output_path = '';
                        $step->Save() or throw new \Exception((string) $step->_error);
                    else:
                        $this->_log("No se pudo borrar el log del paso #{$step->id} ({$step->output_path}); se reintentará");
                    endif;
                } catch (\Throwable $e) {
                    $this->_log("Fallo al purgar el log del paso #{$step->id}: " . $e->getMessage());
                }
            endforeach;
        } catch (\Throwable $e) {
            $this->_log('Fallo de la purga de logs: ' . $e->getMessage());
        }
    }

    /**
     * Barrido de huérfanos: carpetas de ejecuciones que ya no existen en
     * la BD (p. ej. tras borrar un proyecto en cascada; el borrado de la BD
     * no toca archivos). Lote acotado por pasada.
     */
    private function _sweepOrphanLogs(): void {
        try {
            $this->_store()->sweepOrphans(STEP_LOG_PURGE_BATCH, function (array $ids): array {
                $alive = $this->WorkflowExecution->Find([
                    'fields'     => 'id',
                    'conditions' => '`id` IN (' . implode(',', array_map('intval', $ids)) . ')',
                ]);
                $found = [];
                foreach ($alive as $execution):
                    $found[] = (int) $execution->id;
                endforeach;

                return $found;
            });
        } catch (\Throwable $e) {
            $this->_log('Fallo del barrido de logs huérfanos: ' . $e->getMessage());
        }
    }

    private function _log(string $message): void {
        error_log(date('c') . " [workflow_runner] {$message}" . PHP_EOL, 3, STEP_RUNNER_LOG);
    }
}
