# Diseño técnico — Salud Operativa

## Alcance

Sin migraciones, sin modelos nuevos, sin Commands/Events/Reactions.
Solo un helper con las dos queries de agregación, un endpoint que
recalcula al cambiar la ventana, y el widget del dashboard.

## Cálculo — métodos en `WorkflowExecution`, no en un helper

**Corregido tras revisión de código — dos violaciones reales de
`code-conventions.md`:**

1. **La lógica de query no va en un helper.** Un helper que consulta
   un modelo directamente es la capa equivocada — si el cálculo
   necesita el modelo, el método vive *en* el modelo
   (`WorkflowExecution::DeploymentSuccessRate()`/`LeadTime()`), el
   controlador lo llama vía `$this->WorkflowExecution->...()` (lazy-load
   nativo, sin parámetros artificiales), y pasa el resultado a la
   vista. Esto además elimina de raíz la pregunta que había quedado
   sin resolver sobre "cómo accede un helper al modelo" — ya no hace
   falta que acceda, el controlador nunca deja de tener su propio
   lazy-load.
2. **Un solo `return` por método** (`code-conventions.md`: *"Evitar
   múltiples `return` en un método... usando índices calculados
   cuando sea posible"*) — las tres funciones originales tenían 2-4
   salidas cada una.

```php
// app/models/workflow_execution.php — métodos agregados

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
```

`formatLeadTime()` sí se queda como función global de helper (es
formato puro, no toca ningún modelo) — pero también corregida a un
solo `return`:

```php
// app/helpers/HealthMetrics_Helper.php — solo la función de formato

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
```

```php
// AdminController::healthmetricsAction() actualizado

public function healthmetricsAction(): void {
    $this->layout = null;
    $windowDays = (int) ($this->params['window'] ?? 7);
    in_array($windowDays, [7, 30, 90], true) or ($windowDays = 7);

    $this->_response['d'] = [
        'success_rate' => $this->WorkflowExecution->DeploymentSuccessRate($windowDays),
        'lead_time'    => formatLeadTime($this->WorkflowExecution->LeadTime($windowDays)),
    ];
    $this->setResponseCode(HTTP_200);
    $this->respondToAJAX(json_encode($this->_response));
}
```

## Widget del dashboard

Reemplaza el `_widget-empty-state.phtml` de "Operational Health" en
`admin/index.phtml` — mismo criterio ya establecido: si no hay datos
en la ventana, mensaje específico ("Sin datos en este período"), no
el genérico de "Próximamente" (ese es solo para dominios que no
existen; este ya existe).

## Fuera de alcance de este documento

Ver "Fuera de alcance" en `requirements.md`.