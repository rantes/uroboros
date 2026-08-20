<?php
/**
 * Helper de Salud Operativa (Health Management). Funciones globales
 * sin namespace — mismo patrón que OperationalShell_Helper.php
 * (Controller::LoadHelper() solo hace require_once del archivo, no
 * instancia clase ni inyecta métodos).
 *
 * Corregido tras revisión de código: la lógica de agregación sobre
 * WorkflowExecution ya no vive aquí — un helper que consulta un
 * modelo directamente es la capa equivocada (code-conventions.md).
 * Ver WorkflowExecution::DeploymentSuccessRate()/LeadTime() en
 * app/models/workflow_execution.php. Este archivo solo formatea —
 * no toca ningún modelo.
 */

/**
 * Requisito 2.3 — unidad legible según la magnitud. También decide
 * el mensaje de estado vacío (Requisito 2.2) cuando $seconds es
 * null — la vista no necesita un if/else aparte para Lead Time.
 */
function formatLeadTime(?int $seconds): string {
    $formatted = 'Sin datos en este período';

    if ($seconds !== null):
        if ($seconds < 60):
            $formatted = "{$seconds}s";
        elseif ($seconds < 3600):
            $formatted = round($seconds / 60, 1) . 'm';
        else:
            $formatted = round($seconds / 3600, 1) . 'h';
        endif;
    endif;

    return $formatted;
}
