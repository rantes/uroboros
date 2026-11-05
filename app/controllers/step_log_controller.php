<?php
namespace App\Controllers;

use Exception;
use App\Controllers\MainController;
use App\Helpers\StepLogStore;

/**
 * Lectura de los logs completos de pasos (salida-pasos-en-archivos).
 * Requiere sesión (MainController::before_filter -> requireLogin); ninguna
 * acción está en exceptsBeforeFilter. La ruta del archivo sale SOLO de la
 * fila de BD (output_path) y se valida con StepLogStore::resolve(); el id
 * de la URL es un entero que únicamente selecciona la fila.
 */
class StepLogController extends MainController {
    /** Sobrescribible (tests); por defecto StepLogStore sobre STEP_LOG_BASE_PATH. */
    public ?StepLogStore $logStore = null;

    public function __construct() {
        parent::__construct();
        $this->operationalShell = true;
        $this->helper[]         = 'OperationalShell';
        $this->helper[]         = 'HealthMetrics';
        $this->sectionTitle     = 'Log de paso';
    }

    /** Vista del log (escapado, con aviso de checksum y tope de vista). */
    public function viewAction(): void {
        $store = $this->_store();

        try {
            $step = $this->StepExecution->Find($this->_stepId());

            if ($step->counter() > 0):
                $content = $store->readForView($step->output_path);

                $this->step          = $step;
                $this->stepName      = $this->_stepName($step);
                $this->logContent    = $content;
                $this->logAvailable  = $content !== null;
                $this->checksumOk    = $content === null || $store->verify($step->output_path, $step->output_checksum);
                $this->render        = ['file' => 'step_log/step_log_view.phtml'];

                $this->checksumOk
                    or error_log("StepLogController: el log del paso #{$step->id} NO coincide con su checksum ({$step->output_path})");
            else:
                $this->_code = HTTP_404;
                $this->setResponseCode($this->_code);
                $this->render = ['text' => 'Paso no encontrado.'];
            endif;
        } catch (Exception $e) {
            error_log('StepLogController::view: ' . $e->getMessage());
            $this->_code = HTTP_500;
            $this->setResponseCode($this->_code);
            $this->render = ['text' => 'Ocurrió un error al cargar el log.'];
        }
    }

    /** Descarga del archivo completo como text/plain, nunca interpretado por el navegador. */
    public function downloadAction(): void {
        $this->layout = null;
        $store        = $this->_store();

        try {
            $step = $this->StepExecution->Find($this->_stepId());
            $full = $step->counter() > 0 ? $store->resolve($step->output_path) : null;

            if ($full !== null):
                $content = (string) file_get_contents($full);
                $intact  = $store->verify($step->output_path, $step->output_checksum);

                $this->setHeader('Content-Type: text/plain; charset=utf-8');
                $this->setHeader('Content-Disposition: attachment; filename="step-' . (int) $step->id . '.log"');
                $this->setHeader('X-Content-Type-Options: nosniff');
                $this->setHeader('Cache-Control: no-store');
                $intact or $this->setHeader('X-Checksum-Mismatch: 1');
                $intact or error_log("StepLogController: descarga del paso #{$step->id} con checksum NO coincidente ({$step->output_path})");

                $this->render = ['text' => $content !== '' ? $content : "\n"];
            else:
                $this->_code = HTTP_404;
                $this->setResponseCode($this->_code);
                $this->render = ['text' => 'Archivo no disponible.'];
            endif;
        } catch (Exception $e) {
            error_log('StepLogController::download: ' . $e->getMessage());
            $this->_code = HTTP_500;
            $this->setResponseCode($this->_code);
            $this->render = ['text' => 'Ocurrió un error al descargar el log.'];
        }
    }

    private function _stepId(): int {
        return (int) ($this->params['id'] ?? $this->params[0] ?? 0);
    }

    private function _stepName(object $step): string {
        $definition = $this->WorkflowStepDefinition->Find((int) $step->workflow_step_definition_id);

        return $definition->counter() > 0 ? (string) $definition->name : 'Paso #' . (int) $step->id;
    }

    private function _store(): StepLogStore {
        $this->logStore ??= new StepLogStore();

        return $this->logStore;
    }
}
