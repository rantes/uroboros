import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbCard } from '../components/dmb-card/dmb-card.directive.js';
import { DmbButtonAction } from '../components/dmb-button-action/dmb-button-action.directive.js';
import { DmbSearch } from '../components/dmb-search/dmb-search.directive.js';
import { DmbSimpleForm } from '../components/dmb-simple-form/dmb-simple-form.directive.js';
import { DmbDock } from '../components/dmb-dock/dmb-dock.directive.js';

export class AdminListItems extends DumboApp {
    constructor() {
        super();

        this.components = [
            DmbCard,
            DmbSearch,
            DmbSimpleForm,
            DmbDock
        ];
    }

}

const adminListItems = new AdminListItems();
adminListItems.buildComponents();
