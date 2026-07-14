import {
    DumboApp
} from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbCard } from '../components/dmb-card/dmb-card.directive.js';
import { DmbDonutChart } from '../components/dmb-donut-chart/dmb-donut-chart.directive.js';
import { DmbDock } from '../components/dmb-dock/dmb-dock.directive.js';
import { DmbTreeView } from '../components/dmb-tree-view/dmb-tree-view.directive.js';

export class Contabilidad extends DumboApp {
    constructor() {
        super();

        this.components = [
            DmbCard,
            DmbDonutChart,
            DmbDock,
            DmbTreeView
        ];
    }
}

const contabilidad = new Contabilidad();
contabilidad.buildComponents();
