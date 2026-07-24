<?php
namespace App\Models;

use DumboPHP\ActiveRecord;

class OemMetric extends ActiveRecord {

    public ?string $metric_type = null;
    public ?int    $hour_bucket = null;
    public ?int    $count       = null;

    public function _init_(): void {
        $this->validate = [
            'presence_of' => [
                ['field' => 'metric_type', 'message' => 'El metric_type es obligatorio'],
                ['field' => 'hour_bucket', 'message' => 'El hour_bucket es obligatorio'],
            ],
        ];
    }

    /**
     * Incrementa en 1 el contador de la hora actual para $metricType.
     *
     * Find()/Niu()/Save() — nunca SQL crudo, el proyecto no toca la
     * capa de BD directamente. Esto reabre, en teoría, una ventana de
     * carrera (dos procesos leen el mismo count y ambos escriben el
     * mismo valor incrementado, se pierde un incremento); se acepta
     * porque este contador es un pulso operativo aproximado para el
     * footer del dashboard, no una fuente de auditoría exacta (para
     * eso está `events`), y porque "ejecución por lotes es
     * secuencial, nunca paralela a nivel de proceso del SO" (ver
     * CLAUDE.md) elimina el escenario de colisión real. Ver
     * .claude/specs/metricas-oem/design.md.
     */
    public function Increment(string $metricType): void {
        $hourBucket = (int) (floor(time() / 3600) * 3600);
        $existing   = $this->Find([
            'conditions' => [
                ['metric_type', $metricType],
                ['hour_bucket', $hourBucket],
            ],
        ]);

        if ($existing->counter() === 0):
            $existing = $this->Niu([
                'metric_type' => $metricType,
                'hour_bucket' => $hourBucket,
                'count'       => 1,
            ]);
        else:
            $existing->count = $existing->count + 1;
        endif;

        $existing->Save();
    }
}
