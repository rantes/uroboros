import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';
import { appModel } from '../models/app-model.factory.js';

export class DmbLogin extends DumboDirective {
    static selector = 'dmb-login';

    init() {
        const form = this.querySelector('dmb-form');
        const target = this.getAttribute('action');
        const redirect = this.getAttribute('redirect');

        if (form !== null) {
            form.callback = (formSubmitted) => {
                appModel.url(target);
                appModel.login(formSubmitted.getFormData(), redirect);
            };
        }
    }
}
