import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbCard } from '../components/dmb-card/dmb-card.directive.js';
import { DmbDonutChart } from '../components/dmb-donut-chart/dmb-donut-chart.directive.js';
import { DmbMoreOptions } from '../components/dmb-more-options/dmb-more-options.directive.js';
import { DmbMoreOption } from '../components/dmb-more-option/dmb-more-option.directive.js';
import { DmbStatusBadge } from '../components/dmb-status-badge/dmb-status-badge.directive.js';

export class AdminIndex extends DumboApp {
    constructor() {
        super();

        this.components = [
            DmbCard,
            DmbDonutChart,
            DmbMoreOptions,
            DmbMoreOption,
            DmbStatusBadge
        ];
    }

}

const index = new AdminIndex();
index.buildComponents();
