import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbClock } from '../components/dmb-clock/dmb-clock.directive.js';
import {
    DmbButtonAction
} from '../components/dmb-button-action/dmb-button-action.directive.js';
import { DmbClockText } from '../components/dmb-clock-text/dmb-clock-text.directive.js';
import {
    DmbLivingDivisionResidentSelect
} from '../components/dmb-living-division-resident-select/dmb-living-division-resident-select.directive.js';
import { DmbPhotoCamera } from '../components/dmb-photo-camera/dmb-photo-camera.directive.js';
import { DmbUserPhoto } from '../components/dmb-user-photo/dmb-user.photo.directive.js';
import { DmbParkingLot } from '../components/dmb-parking-lot/dmb-parking-lot.directive.js';
import { DmbProperty } from '../components/dmb-property/dmb-property.directive.js';
import { DmbSearchPopup } from '../components/dmb-search-popup/dmb-search-popup.directive.js';
import { DmbDayClockText } from '../components/dmb-day-clock-text/dmb-day-clock-text.directive.js';
import { DmbSimpleForm } from '../components/dmb-simple-form/dmb-simple-form.directive.js';
import { DmbCard } from '../components/dmb-card/dmb-card.directive.js';
import { DmbReceptionTypeSelect } from '../components/dmb-reception-type-select/dmb-reception-type-select.directive.js';
import { DmbIdReader } from '../components/dmb-id-reader/dmb-id-reader.directive.js';
import { GuardaService } from '../components/models/guarda.factory.js';

export class App extends DumboApp {

    constructor() {
        super();

        this.components = [
            DmbClock,
            DmbCard,
            DmbButtonAction,
            DmbClockText,
            DmbIdReader,
            DmbLivingDivisionResidentSelect,
            DmbPhotoCamera,
            DmbUserPhoto,
            DmbParkingLot,
            DmbProperty,
            DmbDayClockText,
            DmbSearchPopup,
            DmbSimpleForm,
            DmbReceptionTypeSelect
        ];
    }
}

const guardApp = new App();
guardApp.buildComponents();

export const guardaService = new GuardaService();
