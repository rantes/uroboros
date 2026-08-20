<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class WorkflowExecution extends ActiveRecord {

    public ?int    $workflow_definition_id = null;
    public ?string $status                 = null;
    public ?string $trigger_type           = null;
    public ?int    $started_at             = null;
    public ?int    $completed_at           = null;

    public function _init_(): void {
        $this->belongs_to = ['workflow_definition'];
        $this->has_many   = ['step_executions'];

        $this->validate = [
            'presence_of' => [
                ['field' => 'workflow_definition_id', 'message' => 'El workflow es obligatorio'],
                ['field' => 'status',                  'message' => 'El status es obligatorio'],
                ['field' => 'trigger_type',            'message' => 'El trigger_type es obligatorio'],
            ],
        ];

        $this->before_save = ['validateStatus', 'validateTriggerType'];
    }

    /**
     * No existe una regla de validación tipo "inclusión en lista" en
     * $this->validate — mismo hallazgo ya confirmado en
     * Project::validateType(). Lista cerrada aplicada a mano en un
     * hook before_save.
     */
    public function validateStatus(): void {
        $validStatuses = ['pending', 'running', 'completed', 'failed'];

        in_array($this->status, $validStatuses, true)
            or $this->_error->add(['field' => 'status', 'message' => 'El status debe ser uno de: ' . implode(', ', $validStatuses)]);
    }

    public function validateTriggerType(): void {
        $validTriggerTypes = ['manual', 'webhook'];

        in_array($this->trigger_type, $validTriggerTypes, true)
            or $this->_error->add(['field' => 'trigger_type', 'message' => 'El trigger_type debe ser uno de: ' . implode(', ', $validTriggerTypes)]);
    }

    /**
     * Requisito 1 (salud-operativa) — Deployment Success Rate, en
     * porcentaje (0-100, un decimal), para la ventana de días dada.
     * null cuando no hay ninguna ejecución concluida en la ventana —
     * nunca 0% ni división por cero. Ventana filtrada por
     * completed_at, no created_at — ver design.md, "Cambio de
     * criterio".
     */
    public function DeploymentSuccessRate(int $windowDays = 7): ?float {
        $since = time() - ($windowDays * 86400);
        $rate  = null;

        $result = $this->Find([
            'fields'     => "SUM(CASE WHEN `status`='completed' THEN 1 ELSE 0 END) AS completed_count, COUNT(*) AS total_count",
            'conditions' => [
                ['status', 'IN', ['completed', 'failed']],
                ['completed_at', '>=', $since],
            ],
        ]);

        empty($result->total_count)
            or ($rate = round(($result->completed_count / $result->total_count) * 100, 1));

        return $rate;
    }

    /**
     * Lead Time promedio, en segundos, created_at -> completed_at,
     * para la ventana de días dada. null cuando no hay ninguna
     * ejecución completada en la ventana. completed_count viaja en la
     * misma query que el AVG — AVG() de un conjunto vacío es SQL
     * NULL, y contar en la misma fila evita depender de cómo el ORM
     * expone ese NULL al castear.
     */
    public function LeadTime(int $windowDays = 7): ?int {
        $since    = time() - ($windowDays * 86400);
        $leadTime = null;

        $result = $this->Find([
            'fields'     => "AVG(`completed_at` - `created_at`) AS avg_lead_time, COUNT(*) AS completed_count",
            'conditions' => [
                ['status', 'completed'],
                ['completed_at', '>=', $since],
            ],
        ]);

        ((int) $result->completed_count) > 0
            and ($leadTime = (int) round($result->avg_lead_time));

        return $leadTime;
    }
}
