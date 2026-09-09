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
        try {
            $this->sectionTitle = 'Inicio';
            $this->adminCRUDAction = '';
            $this->paginate = false;

            // Active Operations — único widget con dominio real hoy
            // (ejecucion-workflows). Risk Indicators y Recomendaciones
            // siguen en _widget-empty-state.phtml hasta que exista el
            // dominio correspondiente — ver dashboard-shell/design.md.
            // Dependency Graph (dependency-graph/design.md) ya tiene
            // dominio real — se deriva de WorkflowDefinition, sin
            // tabla propia.
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

            $dependencyGraph = $this->_dependencyGraphViewData(true);
            $this->dependencyGraphSvg   = $dependencyGraph['svg'];
            $this->dependencyGraphNodes = $dependencyGraph['nodes'];
            $this->dependencyCyclePath  = $dependencyGraph['cyclePath'];
            $this->dependencyCycleLabel = $dependencyGraph['cycleLabel'];
        } catch (\Exception $e) {
            $this->setResponseCode(HTTP_500);
            $this->render = ['text' => 'Ocurrió un error al cargar el Cockpit.'];
        }
    }

    /**
     * Vista de detalle completa del grafo de dependencias entre
     * Proyectos (Requisito 3, dependency-graph/design.md). Solo
     * lectura — las relaciones se configuran donde ya existían, en
     * el formulario de WorkflowDefinition.
     */
    public function dependencygraphAction(): void {
        try {
            $this->sectionTitle = 'Dependency Graph';

            $dependencyGraph = $this->_dependencyGraphViewData(false);

            $this->dependencyGraphSvg   = $dependencyGraph['svg'];
            $this->dependencyGraphNodes = $dependencyGraph['nodes'];
            $this->dependencyCyclePath  = $dependencyGraph['cyclePath'];
            $this->dependencyCycleLabel = $dependencyGraph['cycleLabel'];
        } catch (\Exception $e) {
            $this->setResponseCode(HTTP_500);
            $this->render = ['text' => 'Ocurrió un error al cargar el grafo de dependencias.'];
        }
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

    /**
     * Punto único usado por indexAction() (widget compacto) y
     * dependencygraphAction() (detalle completo) — arma el grafo, la
     * detección de ciclos y el SVG, con el mismo generador para
     * ambos tamaños (dependency-graph/design.md, "un solo método
     * compartido"). svg queda en null cuando no hay ninguna
     * dependencia configurada — Requisito 2.2, estado vacío honesto.
     */
    private function _dependencyGraphViewData(bool $compact): array {
        $graph      = $this->_buildProjectDependencyGraph();
        $cyclePath  = $this->_detectCycle($graph['nodes'], $graph['edges']);
        $svg        = null;
        $cycleLabel = null;

        empty($graph['nodes'])
            or ($svg = $this->_renderDependencyGraphSvg(
                $graph['nodes'],
                $this->_computeLayout($graph['nodes'], $graph['edges']),
                $graph['edges'],
                $cyclePath,
                $compact
            ));

        if (!empty($cyclePath)):
            $cycleNames = [];
            foreach ($cyclePath as $projectId):
                $cycleNames[] = htmlspecialchars((string) ($graph['nodes'][$projectId] ?? $projectId), ENT_QUOTES, 'UTF-8');
            endforeach;
            $cycleNames[] = $cycleNames[0];
            $cycleLabel   = implode(' → ', $cycleNames);
        endif;

        return [
            'nodes'      => $graph['nodes'],
            'edges'      => $graph['edges'],
            'cyclePath'  => $cyclePath,
            'cycleLabel' => $cycleLabel,
            'svg'        => $svg,
        ];
    }

    /**
     * Deriva el grafo de dependencias entre Proyectos a partir de
     * WorkflowDefinition.workflow_definition_id (encadenamiento-workflows)
     * — sin tabla ni modelo propio (Requisito 1, dependency-graph/design.md).
     *
     * Verificación previa (tasks.md, "Verificación previa") — dos
     * hallazgos confirmados contra el framework real antes de
     * escribir este método:
     *
     * 1. 'IS NOT' como operador dentro del array de `conditions` NO
     *    funciona para NULL: ActiveRecord::_buildConditions()
     *    (dumbophp.php) siempre hace `array_shift($condition)` sobre
     *    el valor restante y lo interpola como `'{$value}'` — con
     *    `null` como valor eso genera literalmente
     *    `workflow_definition_id IS NOT ''`, no `IS NOT NULL`. Se
     *    resuelve pasando la condición como string crudo, que
     *    _prepareSelectParams() reenvía tal cual sin pasar por
     *    _buildConditions().
     * 2. Excluir auto-referencias de un Proyecto a sí mismo
     *    (`$childProject->id === $parentProject->id`) es correcto:
     *    un Workflow encadenado a otro Workflow del mismo Proyecto es
     *    orquestación interna, no una dependencia entre Proyectos —
     *    no aporta nada al análisis de impacto que pide el Requisito
     *    1 ("si cambio X, ¿qué se ve afectado?", cambiar un Proyecto
     *    nunca afecta a sí mismo en ese sentido). Se omite del grafo
     *    directamente, sin representarlo como flecha hacia sí mismo.
     */
    private function _buildProjectDependencyGraph(): array {
        $nodes            = [];
        $edges            = [];
        $chainedWorkflows = $this->WorkflowDefinition->Find([
            'conditions' => 'workflow_definition_id IS NOT NULL',
        ]);

        foreach ($chainedWorkflows as $childWorkflow):
            $parentWorkflow = $this->WorkflowDefinition->Find((int) $childWorkflow->workflow_definition_id);
            $childProject   = $childWorkflow->project();
            $parentProject  = $parentWorkflow->project();

            (!empty($childProject->id) and !empty($parentProject->id) and (int) $childProject->id !== (int) $parentProject->id)
                and ($nodes[(int) $parentProject->id] = $parentProject->name)
                and ($nodes[(int) $childProject->id]  = $childProject->name)
                and ($edges["{$parentProject->id}->{$childProject->id}"] = [
                    'from' => (int) $parentProject->id,
                    'to'   => (int) $childProject->id,
                ]);
        endforeach;

        return ['nodes' => $nodes, 'edges' => array_values($edges)];
    }

    /**
     * DFS clásico con pila de recursión (aceptado conscientemente
     * para decenas de Proyectos, no miles — design.md). Retorna la
     * secuencia completa de ids de Proyecto que forman el ciclo (no
     * solo el par de la arista de retorno), para poder señalar en la
     * UI cuáles Proyectos están involucrados — Requisito 1.3.
     */
    private function _detectCycle(array $nodes, array $edges): ?array {
        $adjacency = [];
        $visited   = [];
        $stack     = [];
        $cycle     = null;

        foreach ($edges as $edge):
            $adjacency[$edge['from']][] = $edge['to'];
        endforeach;

        foreach (array_keys($nodes) as $nodeId):
            if (empty($visited[$nodeId])):
                $found = $this->_dfsDetectCycle($nodeId, $adjacency, $visited, $stack, []);
                empty($found) or ($cycle = $found);
            endif;
        endforeach;

        return $cycle;
    }

    private function _dfsDetectCycle(int $nodeId, array $adjacency, array &$visited, array &$stack, array $path): array {
        $found            = [];
        $visited[$nodeId] = true;
        $stack[$nodeId]   = true;
        $path[]           = $nodeId;

        foreach (($adjacency[$nodeId] ?? []) as $neighbor):
            if (!empty($stack[$neighbor])):
                $cycleStart = array_search($neighbor, $path, true);
                $found      = array_slice($path, $cycleStart);
            elseif (empty($visited[$neighbor])):
                $nested = $this->_dfsDetectCycle($neighbor, $adjacency, $visited, $stack, $path);
                empty($nested) or ($found = $nested);
            endif;

            if (!empty($found)):
                break;
            endif;
        endforeach;

        unset($stack[$nodeId]);

        return $found;
    }

    /**
     * Niveles simples (columnas), no force-directed — design.md.
     * Implementado como Kahn's algorithm (orden topológico por
     * indegree) en vez de la relajación ingenua "nivel = 1 + máximo
     * de los padres": esa relajación no converge sobre un ciclo (cada
     * vuelta vuelve a subir el nivel, sin cota) y colgaría el cómputo
     * completo. Con Kahn's, un nodo solo entra a la cola cuando su
     * conteo de entrantes llega a 0 — los nodos de un ciclo nunca
     * llegan a 0, así que el while termina en O(V+E) sin importar
     * cuántos ciclos existan; esos nodos reciben un nivel de
     * fallback al final, sin bloquear el cómputo del resto
     * (tasks.md, tarea 5).
     */
    private function _computeLayout(array $nodes, array $edges): array {
        $adjacency     = [];
        $incomingCount = array_fill_keys(array_keys($nodes), 0);
        $levels        = [];
        $queue         = [];
        $columns       = [];

        foreach ($edges as $edge):
            $adjacency[$edge['from']][]  = $edge['to'];
            $incomingCount[$edge['to']] = $incomingCount[$edge['to']] + 1;
        endforeach;

        foreach ($nodes as $nodeId => $name):
            if ($incomingCount[$nodeId] === 0):
                $levels[$nodeId] = 0;
                $queue[]         = $nodeId;
            endif;
        endforeach;

        while (!empty($queue)):
            $current = array_shift($queue);
            foreach (($adjacency[$current] ?? []) as $neighbor):
                $levels[$neighbor] = max($levels[$neighbor] ?? 0, $levels[$current] + 1);
                $incomingCount[$neighbor]--;
                $incomingCount[$neighbor] === 0 and ($queue[] = $neighbor);
            endforeach;
        endwhile;

        $maxLevel = empty($levels) ? 0 : max($levels);
        foreach (array_keys($nodes) as $nodeId):
            isset($levels[$nodeId]) or ($levels[$nodeId] = $maxLevel + 1);
        endforeach;

        foreach ($levels as $nodeId => $level):
            $columns[$level][] = $nodeId;
        endforeach;
        ksort($columns);

        return $columns;
    }

    /**
     * Generador único de SVG compartido entre el widget compacto del
     * Cockpit y la vista de detalle completa — mismo método, con
     * $compact como parámetro de escala (design.md, "no dos
     * implementaciones distintas"). Colores reales de
     * design-guide.md/main.css: --secondary-surface para nodos
     * normales, --border-outline para líneas, --warning para nodos y
     * aristas que forman parte de un ciclo detectado.
     */
    private function _renderDependencyGraphSvg(array $nodes, array $columns, array $edges, ?array $cyclePath, bool $compact): string {
        $rowHeight     = $compact ? 44 : 84;
        $radius        = $compact ? 9 : 20;
        $fontSize      = $compact ? 9 : 13;
        $cycleNodeIds  = empty($cyclePath) ? [] : array_flip($cyclePath);
        $cycleEdgeKeys = [];
        $positions     = [];
        $maxRows       = 1;
        $edgesMarkup   = '';
        $nodesMarkup   = '';

        // Ancho de columna y margen horizontal derivados del nombre de
        // Proyecto más largo — un ancho fijo (ej. 100px) desborda o
        // solapa las etiquetas de nombres reales largos. 0.62 * fontSize
        // es una aproximación conservadora del ancho promedio de
        // carácter para la tipografía del proyecto (Inter/sans-serif),
        // suficiente para reservar espacio sin medir texto en el
        // servidor (SVG generado en PHP, sin acceso al layout real del
        // navegador).
        $maxLabelLength   = 1;
        foreach ($nodes as $name):
            $maxLabelLength = max($maxLabelLength, strlen((string) $name));
        endforeach;
        $estimatedLabelWidth = $maxLabelLength * $fontSize * 0.62;
        $columnWidth          = max($compact ? 100 : 170, $estimatedLabelWidth + 20);
        $marginX              = max($radius + 12, ($estimatedLabelWidth / 2) + 10);
        // Margen vertical — debe alcanzar para la etiqueta debajo del
        // nodo (radio + separación + alto del texto), no solo el radio
        // del círculo; si no, la etiqueta de la última fila queda
        // recortada contra el borde del viewBox.
        $marginY = $radius + 12 + $fontSize + 6;

        if (!empty($cyclePath)):
            $cycleLength = count($cyclePath);
            for ($i = 0; $i < $cycleLength; $i++):
                $cycleEdgeKeys["{$cyclePath[$i]}->{$cyclePath[($i + 1) % $cycleLength]}"] = true;
            endfor;
        endif;

        foreach ($columns as $level => $nodeIds):
            $maxRows = max($maxRows, count($nodeIds));
            foreach ($nodeIds as $index => $nodeId):
                $positions[$nodeId] = [
                    'x' => $marginX + ($level * $columnWidth),
                    'y' => $marginY + ($index * $rowHeight),
                ];
            endforeach;
        endforeach;

        $width  = $marginX * 2 + (max(0, count($columns) - 1) * $columnWidth);
        $height = $marginY * 2 + (max(0, $maxRows - 1) * $rowHeight);

        foreach ($edges as $edge):
            if (isset($positions[$edge['from']]) and isset($positions[$edge['to']])):
                $from        = $positions[$edge['from']];
                $to          = $positions[$edge['to']];
                $isCycleEdge = isset($cycleEdgeKeys["{$edge['from']}->{$edge['to']}"]);
                $stroke      = $isCycleEdge ? 'var(--warning)' : 'var(--border-outline)';
                $marker      = $isCycleEdge ? 'url(#dep-graph-arrow-warning)' : 'url(#dep-graph-arrow)';

                $edgesMarkup .= sprintf(
                    '<line x1="%d" y1="%d" x2="%d" y2="%d" style="stroke:%s;stroke-width:1.5" marker-end="%s"></line>',
                    $from['x'], $from['y'], $to['x'], $to['y'], $stroke, $marker
                );
            endif;
        endforeach;

        foreach ($nodes as $nodeId => $name):
            if (isset($positions[$nodeId])):
                $point       = $positions[$nodeId];
                $isCycleNode = isset($cycleNodeIds[$nodeId]);
                $fill        = $isCycleNode ? 'var(--warning-hover)' : 'var(--secondary-surface)';
                $stroke      = $isCycleNode ? 'var(--warning)' : 'var(--border-outline)';
                $label       = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');

                $nodesMarkup .= sprintf(
                    '<circle cx="%d" cy="%d" r="%d" style="fill:%s;stroke:%s;stroke-width:1.5"></circle>',
                    $point['x'], $point['y'], $radius, $fill, $stroke
                );
                $nodesMarkup .= sprintf(
                    '<text x="%d" y="%d" style="fill:var(--main-font-color);font-size:%dpx;text-anchor:middle" dominant-baseline="central">%s</text>',
                    $point['x'], $point['y'] + $radius + $fontSize + 2, $fontSize, $label
                );
            endif;
        endforeach;

        return <<<SVG
<svg viewBox="0 0 {$width} {$height}" xmlns="http://www.w3.org/2000/svg" class="dependency-graph-svg" style="width:100%;height:auto;display:block">
    <defs>
        <marker id="dep-graph-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto">
            <path d="M0,0 L10,5 L0,10 z" style="fill:var(--border-outline)"></path>
        </marker>
        <marker id="dep-graph-arrow-warning" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto">
            <path d="M0,0 L10,5 L0,10 z" style="fill:var(--warning)"></path>
        </marker>
    </defs>
    {$edgesMarkup}
    {$nodesMarkup}
</svg>
SVG;
    }
}
