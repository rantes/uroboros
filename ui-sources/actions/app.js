import {
    DumboApp
} from '/libs/dumbo.min.js';
import {
    DmbButton,
    DmbClosePanel,
    DmbContent,
    DmbDialog,
    DmbFooter,
    DmbForm,
    DmbInput,
    DmbPagination,
    DmbPanel,
    DmbSelect,
    DmbTextArea,
    DmbView
} from '/libs/directives.js';
import { DmbLogin } from '../components/dmb-login/dmb-login.directive.js';
import { DmbAddCommand } from '../components/dmb-add-command/dmb-add-command.directive.js';
import { DmbButtonAction } from '../components/dmb-button-action/dmb-button-action.directive.js';
import { DmbEnvInput } from '../components/dmb-env-input/dmb-env-input.directive.js';
import { DmbEnvInputs } from '../components/dmb-env-inputs/dmb-env-inputs.directive.js';
import { DmbSimpleForm } from '../components/dmb-simple-form/dmb-simple-form.directive.js'


class App extends DumboApp {

    constructor() {
        super();

        this.components = [
            DmbAddCommand,
            DmbButton,
            DmbButtonAction,
            DmbClosePanel,
            DmbContent,
            DmbDialog,
            DmbEnvInput,
            DmbEnvInputs,
            DmbFooter,
            DmbForm,
            DmbInput,
            DmbLogin,
            DmbPagination,
            DmbPanel,
            DmbSelect,
            DmbSimpleForm,
            DmbTextArea,
            DmbView
        ];
    }
}

export const app = new App();
app.buildComponents();

if(navigator.serviceWorker) {
    navigator.serviceWorker.register('/sw.js')
    .then(function(registration) {
    /* NOOP */
    });
    navigator.serviceWorker.ready.then(function(registration) {
        /* NOOP */
    });
}
