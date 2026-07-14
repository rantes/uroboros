import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbButton } from '../components/dmb-button/dmb-button.directive.js';
import { DmbButtonAction } from '../components/dmb-button-action/dmb-button-action.directive.js';
import { DmbClosePanel } from '../components/dmb-close-panel/dmb-close-panel.directive.js';
import { DmbContent } from '../components/dmb-content/dmb-content.directive.js';
import { DmbDialog } from '../components/dmb-dialog/dmb-dialog.directive.js';
import { DmbFooter } from '../components/dmb-footer/dmb-footer.directive.js';
import { DmbForm } from '../components/dmb-form/dmb-form.directive.js';
import { DmbInput } from '../components/dmb-input/dmb-input.directive.js';
import { DmbPagination } from '../components/dmb-pagination/dmb-pagination.directive.js';
import { DmbPanel } from '../components/dmb-panel/dmb-panel.directive.js';
import { DmbTable } from '../components/dmb-table/dmb-table.directive.js';
import { DmbSelect } from '../components/dmb-select/dmb-select.directive.js';
import { DmbTextArea } from '../components/dmb-textarea/dmb-text-area.directive.js';
import { DmbView } from '../components/dmb-view/dmb-view.directive.js';
import { DmbHelpIcon } from '../components/dmb-help-icon/dmb-help-icon.directive.js';
import { DmbMenuButton } from '../components/dmb-menu-button/dmb-menu-button.directive.js';
import { DmbDialogService } from '../components/dmb-dialog/dmb-dialog.factory.js';
import { DmbSimpleForm } from '../components/dmb-simple-form/dmb-simple-form.directive.js';
import { DmbBookingCalendar } from '../components/dmb-booking-calendar/dmb-booking-calendar.directive.js';
import { DmbCombobox } from '../components/dmb-combobox/dmb-combobox.directive.js';
import { DmbPageLoader } from '../components/dmb-page-loader/dmb-page-loader.directive.js';
import { DmbBarChart } from '../components/dmb-bar-chart/dmb-bar-chart.directive.js';

class App extends DumboApp {

    constructor() {
        super();

        this.components = [
            DmbButton,
            DmbButtonAction,
            DmbBarChart,
            DmbBookingCalendar,
            DmbCombobox,
            DmbClosePanel,
            DmbContent,
            DmbDialog,
            DmbPageLoader,
            DmbFooter,
            DmbForm,
            DmbInput,
            DmbMenuButton,
            DmbPagination,
            DmbPanel,
            DmbSelect,
            DmbSimpleForm,
            DmbTable,
            DmbTextArea,
            DmbView,
            DmbHelpIcon
        ];
    }
}

export const app = new App();
app.buildComponents();

export const AppDialogs = new DmbDialogService();

// KMD-WEBPUSH — El registro del service worker es único y vive en
// /libs/service-worker.js, cargado por _footer-contents.phtml en todas las
// páginas. Este archivo (app.js) no lo carga ningún controlador, por lo que su
// antiguo registro aquí era código muerto y se eliminó para no duplicarlo.
