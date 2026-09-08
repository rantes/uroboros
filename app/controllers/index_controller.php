<?php
namespace App\Controllers;

use App\Controllers\MainController;

class IndexController extends MainController {

    public function __construct() {
        parent::__construct();
        $this->operationalShell = true;
        $this->helper[] = 'OperationalShell';
        $this->helper[] = 'HealthMetrics';
    }

    public function indexAction(): void {
        $this->sectionTitle = 'Inicio';
        $this->adminCRUDAction = '';
        $this->paginate = false;

        // Active Operations — único widget con dominio real hoy
        // (ejecucion-workflows). El resto del grid (Operational
        // Health, Risk Indicators, Dependency Graph, Recomendaciones)
        // sigue en _widget-empty-state.phtml hasta que exista el
        // dominio correspondiente — ver dashboard-shell/design.md.
        $this->activeExecutions = $this->WorkflowExecution->Find([
            'conditions' => [
                ['status', 'IN', ['pending', 'running']],
            ],
            'sort'  => '`id` DESC',
            'limit' => 10,
        ]);

        // Render inicial server-side con el default (o el window de
        // un link/bookmark directo) — el cambio posterior de ventana
        // ya no recarga la página, ver healthmetricsAction().
        $this->healthWindowDays = (int) ($this->params['window'] ?? 7);
        in_array($this->healthWindowDays, [7, 30, 90], true) or ($this->healthWindowDays = 7);

        $this->deploymentSuccessRate = $this->WorkflowExecution->DeploymentSuccessRate($this->healthWindowDays);
        $this->leadTime               = formatLeadTime($this->WorkflowExecution->LeadTime($this->healthWindowDays));
    }

    /**
     * Endpoint AJAX (Requisito 3.3) — recalcula ambas métricas para
     * la ventana elegida sin recargar la página completa. Sin layout,
     * responde JSON — mismo patrón que executeworkflowAction()
     * (AdminController).
     */
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
}
