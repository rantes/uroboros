import { DmbEvents, DumboDirective } from "./dumbo.min.js";

export class DmbSelect extends DumboDirective {
    static selector = 'dmb-select';
    static get observedAttributes() {
        return [
            'valid','values', 'dmb-name', 'label', 'dmb-class', 'validate', 'dmb-value'
        ];
    };
    static template = '<label for=""></label><select transclude></select>';
    validations = {
        _required: value => {
            let response = {
                valid: true,
                error: null
            };

            if (typeof value === 'undefined' || value === null || value === '') {
                response.valid = false;
                response.error = '';
            }

            return response;
        }
    };
    isValid = false;
    valueList = [];
    validators = [];
    _errorInputClass = '_error';

    set values(newValues = []) {
        this.valueList = newValues;
        this.buildOptions();
    }

    get values() {
        return this.valueList;
    }

    set value(val) {
        this.querySelector('select').setAttribute('value', val);
        this.querySelector('select').value = val;
        this.dispatchEvent(DmbEvents.inputChanged.event);
        this.dispatchEvent('change');
    }

    init() {
        const select = this.querySelector('select');
        const label = this.querySelector('label');
        let value = null;
        let options = null;
        let option = null;
        let opval = null;

        this.hasAttribute('label') && (label.innerText = this.getAttribute('label'));
        this.hasAttribute('label') && select.setAttribute('aria-label',this.getAttribute('label'));
        this.hasAttribute('dmb-class') && select.setAttribute('class', this.getAttribute('dmb-class'));
        select.setAttribute('name', this.getAttribute('dmb-name') || '');
        this.hasAttribute('validate') && select.setAttribute('validate',this.getAttribute('validate'));
        select.id = this.getAttribute('dmb-id') || this.generateId();
        select.multiple = this.hasAttribute('multiple');
        label.setAttribute('for', select.id);

        if (this.hasAttribute('dmb-value')) {
            value = this.getAttribute('dmb-value').trim();
            value = select.multiple ? JSON.parse(value) : value;
        }

        if (select && this.getAttribute('validate')) {
            this.validators = this.buildValidators(select, this.getAttribute('validate'));
        }

        select.addEventListener('blur', () => {
            // this._runValidators(e.target, this.validators);
            this.setValidation();
        }, {capture: true, passive: true});

        options = [...select.querySelectorAll('option')];
        if(options.length){
            while((option = options.shift())) {
                option.value = (option.getAttribute('value') || '').trim();
                opval = isNaN(option.value) ? option.value : parseInt(option.value);
                value = isNaN(value) ? value : parseInt(value);

                if ((!option.hasAttribute('selected') && opval == value) || (Array.isArray(value) && value.includes(opval))) {
                    option.setAttribute('selected',true);
                    option.selected = true;
                }

            }
        }
    }

    _runValidators(element, validators) {
        let unknownValidator = () => {
            return {valid: false, error: 'Unknown validator type: "' + (validator || {}).key + '"'};
        };
        let options = element.querySelectorAll('option');
        let content = [...options].filter(x=>x.selected).map(x=>x.value.trim() || '');
        let valid = true;
        let validator= null;
        let func = null;
        let result = null;

        !element.multiple && (content = content[0]);
        for (var i = 0, len = validators.length; i < len; i++) {
            validator = validators[i];
            func = this.validations['_' + validator.key] || unknownValidator;

            result = func(content, validator.param);
            if (result.valid !== true) {
                valid = false;
                break;
            }
        }

        if (valid === true) {
            element.parentNode.classList.remove(this._errorInputClass);
        } else {
            element.parentNode.classList.add(this._errorInputClass);
        }
        this.isValid = valid;
        valid? element.setAttribute('valid','') : element.removeAttribute('valid');
    }

    buildValidators(element, validations) {
        let validators = [];
        let validatorList = (validations || '').split(',');
        let keyParam = '';

        for (let i = 0, len = validatorList.length; i < len; i++) {
            keyParam = validatorList[i].split(':');

            if (keyParam[0]) {
                validators.push({
                    key: keyParam[0],
                    param: keyParam.length === 2 ? keyParam[1] : null
                });

                if (keyParam[0] === 'required') {
                    element.parentNode.classList.add('required');
                    element.setAttribute('required',true);
                }
            }
        }

        return validators;
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        const select = this.querySelector('select');
        const label = this.querySelector('label');

        switch(attr) {
        case 'valid':
            this.isValid = (newValue !== null);
            break;
        case 'values':
            if (!oldValue && newValue) {
                this.valueList = JSON.parse(newValue);
                this.buildOptions();
            }
            break;
        case 'dmb-name':
            if (select) select.setAttribute('name', newValue);
            break;
        case 'validate':
            if (select) this.validators = this.buildValidators(select, newValue);
            break;
        case 'dmb-value':
            if (!oldValue && newValue && select) {
                this.buildOptions();
            }
            break;
        case 'label':
            if (label) label.innerText = newValue;
            break;
        }

    }

    buildOptions() {
        let i = 0;
        let total = this.valueList.length || 0;
        let option = null;
        let value = this.getAttribute('dmb-value') || null;
        const select = this.querySelector('select');

        if (select) {
            select.innerHTML = '';
            select.multiple && value && (value = JSON.parse(value));
    
            for (i = 0; i < total; i++) {
                option = document.createElement('option');
                option.value, this.valueList[i].value;
                option.setAttribute('value',this.valueList[i].value);
                option.innerHTML = this.valueList[i].text;
                
                if (this.valueList[i].selected || (option.value.length && option.value == value) || (Array.isArray(value) && value.includes(option.value))) {
                    option.setAttribute('selected',true);
                    option.selected = true;
                }
                select.append(option);
            }
        }

        this.removeAttribute('values');
    }

    resetValidation() {
        let elements = [...this.querySelectorAll(this._errorInputClass)];
        let element = null;

        while ((element = elements.shift())) {
            element.classList.remove(this._errorInputClass);
        }
    }

    setValidation() {
        this._runValidators(this.querySelector('select'), this.validators);
    }


}
