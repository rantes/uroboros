import {
    DmbEvents,
    DumboDirective
} from "./dumbo.min.js";

export class DmbForm extends DumboDirective {
    static selector = 'dmb-form';
    static template = '<form arial-role="form" transclude></form>';
    static get observedAttributes() { return ['action']; };
    _valids = 0;
    form = null;


    get valids() {
        return this._valids;
    }

    init() {
        let inputs = null;
        let item = null;

        this.form = this.querySelector('form');

        this.form.setAttribute('method', this.getAttribute('method') || 'POST');
        this.form.setAttribute('action', this.getAttribute('action') || '#');
        this.form.setAttribute('autocomplete', this.getAttribute('autocomplete') || 'on');
        this.form.setAttribute('name', this.getAttribute('dmb-name') || '');
        this.form.setAttribute('target', this.getAttribute('target') || '');
        this.form.setAttribute('id', this.getAttribute('dmb-id') || this.generateId());
        this.form.setAttribute('enctype', this.getAttribute('enctype') || 'application/x-www-form-urlencoded');

        inputs = [...this.querySelectorAll('input:not([type="submit"])')];
        if (this.querySelector('input[type="submit"]') === null || inputs.length > 1) {
            while ((item = inputs.shift())) {
                item.addEventListener('keypress', e => {
                    if (13 === e.keyCode) {
                        this.submit();
                    }
                });
            }
        }
    }

    reset() {
        this.form.reset();
    }

    validate(formElements, parentSelector) {
        let item = null;
        let hasInvalids = false;
        let parentItem = null;

        formElements.forEach(element => {
            element.closest(parentSelector).resetValidation();
        });

        this.dispatchEvent(DmbEvents.formBeforeValidate.event);
        while ((item = formElements.shift())) {
            if (item.closest('.novalidate') === null) {
                parentItem = item.closest(parentSelector);
                parentItem.setValidation();

                if (!item.hasAttribute('valid') && !item.hasAttribute('hidden')) {
                    item.reportValidity();
                    item.focus();
                    hasInvalids = true;
                } else {
                    this._valids++;
                }
            }
        }

        this.dispatchEvent(DmbEvents.formAfterValidate.event);

        return !hasInvalids;
    }

    submit() {
        this.dispatchEvent(DmbEvents.formSubmit.event);
        this.dispatchEvent(new Event('submit'));

        const isAsync = this.hasAttribute('async');
        const form = this.querySelector('form');
        const valid = this.validateForm();

        if(valid) {
            if (isAsync) {
                if(typeof this.callback === 'function') {
                    this.callback(this);
                }
                return false;
            } else {
                form.submit();
            }
            return true;
        }

        return false;
    }

    getFormData() {
        return new FormData(this.form);
    }

    validateForm() {
        const inputs = [...this.querySelectorAll('dmb-input[validate] input')];
        const selects = [...this.querySelectorAll('dmb-select[validate] select')];
        const textAreas = [...this.querySelectorAll('dmb-textarea[validate] textarea')];

        let totalvalidations = 0;

        this._valids = 0;
        totalvalidations = this.validate(inputs, 'dmb-input') + this.validate(selects, 'dmb-select') + this.validate(textAreas, 'dmb-textarea');

        return totalvalidations === 3;
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        this.form = this.querySelector('form');

        switch(attr) {
            case 'action':
                if (oldValue) this.form.setAttribute('action', newValue);
            break;
        }
    }
}
