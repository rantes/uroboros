import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbCard } from '../components/dmb-card/dmb-card.directive.js';
import { DmbPushSubscribe } from '../components/dmb-push-subscribe/dmb-push-subscribe.directive.js';
import { DmbStatusBadge } from '../components/dmb-status-badge/dmb-status-badge.directive.js';

export class App extends DumboApp {

    constructor() {
        super();

        this.components = [
            DmbCard,
            DmbPushSubscribe,
            DmbStatusBadge
        ];
    }
}

const userApp = new App();
userApp.buildComponents();
