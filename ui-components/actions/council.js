import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbCard } from '../components/dmb-card/dmb-card.directive.js';
import { DmbNotification } from '../components/dmb-notification/dmb-notification.directive.js';
import { DmbStatusBadge } from '../components/dmb-status-badge/dmb-status-badge.directive.js';

export class CouncilIndex extends DumboApp {
    constructor() {
        super();

        this.components = [
            DmbCard,
            DmbNotification,
            DmbStatusBadge
        ];
    }

}

const council = new CouncilIndex();
council.buildComponents();
