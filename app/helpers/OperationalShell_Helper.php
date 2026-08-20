<?php
/**
 * Helper del shell operativo (dashboard-shell).
 *
 * Sin namespace, deliberadamente: DumboPHP\Controller::LoadHelper()
 * (ver DumboPHP/bin/dumbophp.php) solo hace require_once del archivo
 * — no instancia ninguna clase ni inyecta métodos en el controlador.
 * Por eso estas son funciones sueltas, llamadas directas
 * (activeNavItem($this), no $this->activeNavItem()): Controller
 * hereda __call() de Core_General_Class (resolución mágica de
 * relaciones ActiveRecord — Find_by_*, has_many, etc.), así que un
 * método $this->algo() no declarado en la clase cae ahí, no en este
 * helper, y devuelve null en silencio en vez de fallar. Confirmado
 * contra el framework real, no asumido. Mismo patrón (funciones
 * globales sin namespace) ya usado en Menu_Helper.php/Tools_Helper.php
 * de otros proyectos rantes/dumbophp — no hay generador de helpers
 * en el CLI que dicte lo contrario.
 *
 * Como es un archivo sin namespace, las llamadas a funciones sin
 * calificar dentro de una vista .phtml lo encuentran igual: PHP hace
 * fallback al namespace global para llamadas a función no resueltas
 * en el namespace actual.
 */

use App\Controllers\AdminController;
use App\Models\AppUser;
use App\Models\Event;
use App\Models\OemMetric;

/**
 * Sidebar: qué item del nav está activo según la página actual.
 *
 * Corregido — devolvía $controller->_getController_() ('admin' para
 * TODAS las rutas de AdminController), así que todos los links del
 * sidebar se marcaban "activos" simultáneamente, siempre (ver
 * dashboard-shell/tasks.md, hallazgo). Ahora devuelve el modelo
 * activo real ('project', 'event', 'workflow_definition',
 * 'workflow_execution') vía AdminBaseTrait::GetActiveModel() — el
 * accessor público, no $controller->_model directo: _model es
 * `protected`, inaccesible desde esta función externa a la clase
 * (confirmado empíricamente, mismo caso que Controller::$params).
 */
function activeNavItem(AdminController $controller): string {
    return $controller->GetActiveModel();
}

/**
 * Footer: métricas agregadas en la ventana de horas configurada.
 * Events (Nh) es calculable siempre (Event no depende de
 * metricas-oem); command_dispatched/command_failed/reaction_executed/
 * reaction_failed dependen de OemMetric (metricas-oem).
 */
function footerMetrics(int $hoursWindow = 6): array {
    $sinceTimestamp = time() - ($hoursWindow * 3600);
    $sinceHourBucket = (int) (floor($sinceTimestamp / 3600) * 3600);
    $result = [
        'events'             => 0,
        'command_dispatched' => 0,
        'command_failed'     => 0,
        'reaction_executed'  => 0,
        'reaction_failed'    => 0,
    ];

    $eventCount = (new Event())->Find([
        'fields'     => 'COUNT(id) AS total',
        'conditions' => [['created_at', '>=', $sinceTimestamp]],
    ]);
    $result['events'] = (int) $eventCount->total;

    $metricSums = (new OemMetric())->Find([
        'fields'     => 'metric_type, SUM(count) AS total',
        'conditions' => [['hour_bucket', '>=', $sinceHourBucket]],
        'group'      => 'metric_type',
    ]);

    foreach ($metricSums as $row):
        array_key_exists($row->metric_type, $result) and ($result[$row->metric_type] = (int) $row->total);
    endforeach;

    return $result;
}

/**
 * Footer: ¿la última escritura a events fue reciente? Chequeo simple,
 * no un health check real de infraestructura (eso es Health
 * Management, fuera de alcance de este spec).
 */
function oemStatus(int $staleThresholdMinutes = 30): string {
    $status = 'unknown';
    $latest = (int) (new Event())->Find(['fields' => 'MAX(created_at) AS latest'])->latest;

    $latest > 0 and ($status = (time() - $latest) <= ($staleThresholdMinutes * 60) ? 'healthy' : 'stale');

    return $status;
}

/**
 * Topbar: iniciales del usuario en sesión, para el avatar. AppUser
 * expone firstname/lastname (usados en sanitizeFirstname/
 * sanitizeLastname, ver app/models/app_user.php) aunque no estén
 * tipados en la clase. 'U' genérico si no hay sesión o no hay nombre.
 */
function currentUserInitials(): string {
    $initials = 'U';

    if (!empty($_SESSION['user'])):
        $user  = (new AppUser())->Find((int) $_SESSION['user']);
        // substr(), no mb_substr() — la extensión mbstring no está
        // instalada en este servidor (confirmado con
        // function_exists('mb_substr') === false). Solo se toma la
        // primera letra de cada nombre para un avatar de iniciales;
        // substr() nativo cubre el caso real sin depender de una
        // extensión adicional.
        $built = strtoupper(
            (!empty($user->firstname) ? substr($user->firstname, 0, 1) : '')
            . (!empty($user->lastname) ? substr($user->lastname, 0, 1) : '')
        );
        !empty($built) and ($initials = $built);
    endif;

    return $initials;
}
