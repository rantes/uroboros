import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbSearchPopup extends DumboDirective {
    static selector = 'dmb-search-popup';
    static template = '<dmb-form dmb-name="search" method="post" action="#" class="search" async>' +
                            '<dmb-input class="dmb-input" dmb-name="term" validate="required"></dmb-input>' +
                            '<dmb-button type="submit" class="primary" icon="search"></dmb-button>' +
                        '</dmb-form>';

    init() {
        const form = this.querySelector('dmb-form');
        const panel = document.querySelector(this.getAttribute('panel'));

        form.callback = (formEl) => {
            const search = formEl.getFormData().get('term');
            panel.setAttribute('source', `${this.dataset.action}?term=${search}`);
            panel.open();
        };
    }
}
