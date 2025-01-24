import { DumboDirective } from "./dumbo.min.js";

export class DmbInput extends DumboDirective {
    static selector = 'dmb-input';
    static get observedAttributes() { return ['valid','name', 'validate', 'dmb-name', 'label', 'dmb-value']; }
    static template = '<label for=""></label><input type="text" placeholder="" />';
    isValid = false;
    _errorInputClass = '_error';
    validations = {
        _required: function (value, param, input) {
            let response = {
                valid: true,
                error: null
            };
            param = null;

            if (typeof value === 'undefined' || value === null || value === '' || (input.getAttribute('type') === 'checkbox' && !input.checked)) {
                response.valid = false;
            }

            return response;
        },
        _email: function (value) {
            let response = {
                    valid: true,
                    error: null
                },
                re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))/;

            if (value && !re.test(value)) {
                response.valid = false;
            }

            return response;
        },
        _numeric: function (value) {
            let response = {
                    valid: true,
                    error: null
                },
                re = /^[0-9]\d*/;

            if (value && !re.test(value)) {
                response.valid = false;
            }

            return response;
        },
        _min: function(value, param) {
            let response = {
                valid: true,
                error: null
            };

            if (value && value.length < param) {
                response.valid = false;
            }

            return response;
        },
        _max: function(value, param) {
            let response = {
                valid: true,
                error: null
            };

            if (value && value.length > param) {
                response.valid = false;
            }

            return response;
        }
    };

    set value(val) {
        const input = this.querySelector('input');

        if (this.type === 'checkbox') {
            if(!!val) {
                input.checked = true;
                input.setAttribute('checked', true);
            }
        } else {
            if(input) {
                input.setAttribute('value', val);
                input.value = val;
            }
        }
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        const input = this.querySelector('input');

        switch(attr) {
            case 'valid':
                this.isValid = (newValue !== null);
                break;
            case 'name':
            case 'dmb-name':
                if (input) input.setAttribute('name', newValue);
                break;
            case 'validate':
                this.buildValidators();
                break;
            case 'label':
                if (input) {
                    this.querySelector('label').innerText = newValue;
                    input.setAttribute('aria-label', newValue);
                    input.setAttribute('placeholder', newValue);
                }
                break;
            case 'dmb-value':
                this.value = newValue;
                break;
        }
    }

    buildValidators () {
        let validators = [];
        let validatorList = (this.getAttribute('validate') || '').split(',');
        const input = this.querySelector('input');

        for (let i = 0, len = validatorList.length; i < len; i++) {
            let keyParam = validatorList[i].split(':');

            if (keyParam[0]) {
                validators.push({
                    key: keyParam[0],
                    param: keyParam.length === 2 ? keyParam[1] : null,
                    input
                });

                if (keyParam[0] === 'required' && input) {
                    this.classList.add('required');
                    input.setAttribute('required','required');
                }
            }
        }

        return validators;
    }

    _runValidators(element, validators) {
        const unknownValidator = () => {
            return {valid: false, error: 'Unknown validator type: "' + (validator || {}).key + '"'};
        };
        const type = element.getAttribute('type');
        let content = element.value.trim();
        let valid = true;
        let validator= null;
        let func = null;
        let result = null;

        if (type !== 'file') element.value = content;
        while((validator = validators.shift())) {
            func = this.validations['_' + validator.key] || unknownValidator;

            result = func(content, validator.param, validator.input);
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
        valid? element.setAttribute('valid','true') : element.removeAttribute('valid');
    }

    resetValidation() {
        let elements = this.getElementsByClassName(this._errorInputClass);
        for (let i = 0; elements.length; i++) {
            elements.item(0).classList.remove(this._errorInputClass);
        }
    }

    setValidation() {
        this._runValidators(this.querySelector('input'), this.buildValidators());
    }

    init() {
        const input = this.querySelector('input');
        const labelElement = this.querySelector('label');
        const label = this.getAttribute('label') || null;
        const placeholder = this.getAttribute('placeholder') || label;
        const masked = this.getAttribute('masked') || null;
        const autocomplete = this.getAttribute('autocomplete') || null;
        const classd = this.getAttribute('dmb-class') || null;
        const name = this.getAttribute('dmb-name') || null;
        const validate = this.getAttribute('validate') || null;
        const pattern = this.getAttribute('pattern') || null;
        const value = this.getAttribute('value') || this.getAttribute('dmb-value') || null;
        const step = this.getAttribute('step') || null;
        const type = this.getAttribute('type') || 'text';
        const checked = this.hasAttribute('checked');

        input.id = this.getAttribute('dmb-id') || this.generateId();
        input.setAttribute('type', type);

        if (label) {
            this.querySelector('label').innerText = label;
            input.setAttribute('aria-label', label);
        }
        if (placeholder) {
            input.setAttribute('placeholder', placeholder);
        }
        if (labelElement) {
            labelElement.setAttribute('for', input.id);
        }

        if (type === 'checkbox') {
            this.insertBefore(input, labelElement);
            if (checked) {
                input.setAttribute('checked', 'on');
            }
        }

        if (type === 'hidden') {
            this.querySelector('label').remove();
        }

        if (masked) input.setAttribute('masked', masked);
        if (autocomplete) input.setAttribute('autocomplete', autocomplete);
        if (classd) input.setAttribute('class', classd);
        if (name) input.setAttribute('name', name);
        if (validate) input.setAttribute('validate', validate);
        if (pattern) input.setAttribute('pattern', pattern);
        if (value) input.value = value;
        if (step) input.setAttribute('step', step);

        type === 'file' && this.hasAttribute('accept') && input.setAttribute('accept', this.getAttribute('accept'));

        const maskInputUppercase = (e) => {
            e.target.value = e.target.value.toUpperCase();
        };

        const maskInputAlpha = (e) => {
            const char = e.which || e.keyCode;

            if ((char < 65 || char > 90) && (char < 97 || char > 122)) {
                return false;
            }
        };

        const maskInputNumeric = (e) => {
            const char = e.which || e.keyCode;

            if (char < 48 || char > 57) {
                return false;
            }
        };

        if (this.getAttribute('masked')) {
            switch (this.getAttribute('masked')) {
            case 'alpha':
                input.onkeydown = maskInputAlpha;
                break;
            case 'numeric':
                input.onkeydown = maskInputNumeric;
                break;
            case 'uppercase':
                input.oninput = maskInputUppercase;
                break;
            }
        }

        this.buildValidators();

        input.addEventListener('blur', () => {
            this.setValidation();
        }, true);
    }
}
