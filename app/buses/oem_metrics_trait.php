<?php
namespace App\Buses;

trait OemMetricsTrait {
    private function _incrementMetric(string $type): void {
        try {
            $this->OemMetric->Increment($type);
        } catch (\Exception $e) {
            // Silencioso a propósito — Requisito 2.1: un fallo al
            // contar nunca debe interrumpir el flujo real.
        }
    }
}
