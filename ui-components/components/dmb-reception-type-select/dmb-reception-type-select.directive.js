import { DumboDirective } from '../../libs/dumbojs/dumbo.min.js';

export class DmbReceptionTypeSelect extends DumboDirective {
    static selector = 'dmb-reception-type-select';

    #_typeSelect;
    #_serviceTypeSelect;

    init() {
        this.#_typeSelect        = this.querySelector('.reception-type');
        this.#_serviceTypeSelect = this.querySelector('.service-type');

        this.#_serviceTypeSelect.setAttribute('hidden', '');

        this.#_typeSelect.addEventListener('change', (e) => {
            if (e.target.value === 'bill') {
                this.#_serviceTypeSelect.removeAttribute('hidden');
            } else {
                this.#_serviceTypeSelect.setAttribute('hidden', '');
                this.#_serviceTypeSelect.value = '';
            }
        }, { capture: true, passive: true });
    }
}
