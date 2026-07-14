import { DmbEvents, DumboDirective } from "../../libs/dumbojs/dumbo.min.js";

/**
 * @dmbdoc directive
 * @name DMB.directive:DmbCombobox
 *
 * @description
 * Combobox: input de texto visible + dropdown de opciones filtradas.
 * Un <select> nativo oculto actúa como carrier del valor para el FormData
 * y la validación (mismo sistema que dmb-select). CSP-safe.
 *
 * @example
<dmb-combobox
    label="Cuenta PUC"
    dmb-name="concept[bancos]"
    dmb-value="42"
    validate="required"
    placeholder="Buscar cuenta...">
    <option value="">Sin asignar</option>
    <option value="42">101005 - CUENTAS CORRIENTES</option>
</dmb-combobox>
 */
export class DmbCombobox extends DumboDirective {
    static selector = 'dmb-combobox';
    static get observedAttributes() {
        return ['valid', 'dmb-name', 'label', 'validate', 'dmb-value', 'placeholder'];
    };
    static template =
        '<label for=""></label>' +
        '<div class="dmb-combobox-control">' +
            '<input type="text" class="dmb-combobox-input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">' +
            '<select transclude class="dmb-combobox-carrier" tabindex="-1" aria-hidden="true" hidden></select>' +
            '<ul class="dmb-combobox-list" role="listbox" hidden></ul>' +
        '</div>';

    validations = {
        _required: value => {
            let response = { valid: true, error: null };
            if (typeof value === 'undefined' || value === null || value === '') {
                response.valid = false;
                response.error = '';
            }
            return response;
        }
    };
    isValid = false;
    validators = [];
    _errorInputClass = '_error';
    #_debounceMs = 300;
    #_debounceTimer = null;
    #_outsideClickHandler = null;
    #_activeIndex = -1;

    init() {
        const label = this.querySelector('label');
        const input = this.querySelector('input.dmb-combobox-input');
        const select = this.querySelector('select.dmb-combobox-carrier');
        const list = this.querySelector('ul.dmb-combobox-list');

        input.id = this.getAttribute('dmb-id') || this.generateId();
        select.id = this.generateId();
        select.setAttribute('name', this.getAttribute('dmb-name') || '');
        label.setAttribute('for', input.id);

        this.hasAttribute('label') && (label.innerText = this.getAttribute('label'));
        this.hasAttribute('label') && input.setAttribute('aria-label', this.getAttribute('label'));
        input.placeholder = this.getAttribute('placeholder') || '';
        list.id = this.generateId();
        input.setAttribute('aria-controls', list.id);

        if (this.getAttribute('validate')) {
            this.validators = this.buildValidators(input, this.getAttribute('validate'));
        }

        this._buildList(select, list);
        this._applyPreselection(select, input);

        input.addEventListener('focus', () => this._open(), { passive: true });
        input.addEventListener('input', () => {
            clearTimeout(this.#_debounceTimer);
            this.#_debounceTimer = setTimeout(() => {
                // Con `src` las opciones se traen de un endpoint remoto; sin él
                // se filtran las opciones estáticas (comportamiento original).
                if (this.hasAttribute('src')) {
                    this._remoteFetch(input.value);
                } else {
                    this._open();
                    this._filter(input.value);
                }
            }, this.#_debounceMs);
        }, { passive: true });
        input.addEventListener('keydown', (e) => this._onKeydown(e));
        input.addEventListener('blur', () => this.setValidation(), { capture: true, passive: true });

        list.addEventListener('mousedown', (e) => {
            // mousedown (no click) para ganarle al blur del input
            const li = e.target.closest('li.dmb-combobox-option');
            if (!li || li.hasAttribute('hidden')) return;
            e.preventDefault();
            this._select(li.getAttribute('data-value'));
        });

        // sincroniza el texto visible cuando el valor se cambia por código
        select.addEventListener('change', () => this._syncInputToSelection(), { passive: true });

        this.#_outsideClickHandler = (e) => {
            if (!this.contains(e.target)) this._close();
        };
        document.addEventListener('click', this.#_outsideClickHandler);
    }

    destroy() {
        clearTimeout(this.#_debounceTimer);
        document.removeEventListener('click', this.#_outsideClickHandler);
    }

    _buildList(select, list) {
        const options = [...select.querySelectorAll('option')];
        let li = null;
        let empty = null;

        list.innerHTML = '';
        options.forEach(option => {
            li = document.createElement('li');
            li.className = 'dmb-combobox-option';
            li.setAttribute('role', 'option');
            li.setAttribute('data-value', (option.getAttribute('value') || '').trim());
            li.innerText = option.text;
            list.appendChild(li);
        });

        empty = document.createElement('li');
        empty.className = 'dmb-combobox-empty';
        empty.innerText = 'Sin resultados';
        empty.setAttribute('hidden', '');
        list.appendChild(empty);
    }

    _applyPreselection(select, input) {
        let value = this.hasAttribute('dmb-value') ? this.getAttribute('dmb-value').trim() : null;
        const options = [...select.querySelectorAll('option')];
        let selected = null;

        options.forEach(option => {
            option.value = (option.getAttribute('value') || '').trim();
        });

        if (value === null || value === '') {
            selected = options.find(o => o.hasAttribute('selected'));
        } else {
            selected = options.find(o => o.value == value);
        }

        if (selected) {
            selected.selected = true;
            select.value = selected.value;
            input.value = selected.value === '' ? '' : selected.text;
        } else {
            select.value = '';
            input.value = '';
        }
    }

    _open() {
        const list = this.querySelector('ul.dmb-combobox-list');
        const input = this.querySelector('input.dmb-combobox-input');
        list.removeAttribute('hidden');
        input.setAttribute('aria-expanded', 'true');
        this.classList.add('open');
    }

    _close() {
        const list = this.querySelector('ul.dmb-combobox-list');
        const input = this.querySelector('input.dmb-combobox-input');
        list.setAttribute('hidden', '');
        input.setAttribute('aria-expanded', 'false');
        this.classList.remove('open');
        this.#_activeIndex = -1;
        this._clearActive();
        this._syncInputToSelection();
    }

    _filter(term) {
        const list = this.querySelector('ul.dmb-combobox-list');
        const needle = (term || '').toLowerCase().trim();
        const items = [...list.querySelectorAll('li.dmb-combobox-option')];
        const empty = list.querySelector('li.dmb-combobox-empty');
        let visible = 0;
        let text = '';
        let value = '';

        items.forEach(li => {
            text = (li.innerText || '').toLowerCase();
            value = (li.getAttribute('data-value') || '').toLowerCase();
            const match = needle === '' || text.includes(needle) || value.includes(needle);
            match ? li.removeAttribute('hidden') : li.setAttribute('hidden', '');
            match && visible++;
        });

        visible === 0 ? empty.removeAttribute('hidden') : empty.setAttribute('hidden', '');
        this.#_activeIndex = -1;
        this._clearActive();
    }

    _remoteFetch(term) {
        const needle = (term || '').trim();

        // Mínimo 3 caracteres antes de consultar al servidor.
        if (needle.length < 3) {
            this._populateRemote([]);
            this._open();
            return;
        }

        const src = this.getAttribute('src');
        const url = `${src}?q=${encodeURIComponent(needle)}`;

        this.classList.add('loading');
        fetch(new Request(url, { method: 'GET' }))
            .then(response => response.json())
            .then(items => {
                this._populateRemote(Array.isArray(items) ? items : []);
                this._open();
            })
            .catch(() => { /* búsqueda silenciosa: no interrumpe al usuario */ })
            .finally(() => this.classList.remove('loading'));
    }

    _populateRemote(items) {
        const select = this.querySelector('select.dmb-combobox-carrier');
        const list = this.querySelector('ul.dmb-combobox-list');
        let opt = null;

        select.innerHTML = '';

        opt = document.createElement('option');
        opt.value = '';
        opt.text = '';
        select.appendChild(opt);

        items.forEach(item => {
            opt = document.createElement('option');
            opt.value = String(item.value);
            opt.text = item.label;
            select.appendChild(opt);
        });

        this._buildList(select, list);
        // Sin filtro local: el servidor ya devolvió las coincidencias.
        this._filter('');
    }

    _visibleItems() {
        const list = this.querySelector('ul.dmb-combobox-list');
        return [...list.querySelectorAll('li.dmb-combobox-option:not([hidden])')];
    }

    _clearActive() {
        const list = this.querySelector('ul.dmb-combobox-list');
        list.querySelectorAll('li.active').forEach(li => {
            li.classList.remove('active');
            li.removeAttribute('aria-selected');
        });
    }

    _highlight(index) {
        const items = this._visibleItems();
        if (!items.length) return;

        this.#_activeIndex = (index + items.length) % items.length;
        this._clearActive();
        const li = items[this.#_activeIndex];
        li.classList.add('active');
        li.setAttribute('aria-selected', 'true');
        li.scrollIntoView({ block: 'nearest' });
    }

    _onKeydown(e) {
        const list = this.querySelector('ul.dmb-combobox-list');
        const isOpen = !list.hasAttribute('hidden');

        switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            if (!isOpen) { this._open(); this._filter(''); }
            this._highlight(this.#_activeIndex + 1);
            break;
        case 'ArrowUp':
            e.preventDefault();
            if (!isOpen) { this._open(); this._filter(''); }
            this._highlight(this.#_activeIndex - 1);
            break;
        case 'Enter': {
            const items = this._visibleItems();
            if (isOpen && this.#_activeIndex >= 0 && items[this.#_activeIndex]) {
                e.preventDefault();
                this._select(items[this.#_activeIndex].getAttribute('data-value'));
            }
            break;
        }
        case 'Escape':
            if (isOpen) { e.preventDefault(); this._close(); }
            break;
        }
    }

    _select(value) {
        const select = this.querySelector('select.dmb-combobox-carrier');
        const input = this.querySelector('input.dmb-combobox-input');
        const option = [...select.querySelectorAll('option')].find(o => o.value == value);

        select.value = value;
        input.value = (option && value !== '') ? option.text : '';

        this._close();
        this.setValidation();
        this.dispatchEvent(DmbEvents.inputChanged.event);
        this.dispatchEvent(new Event('change'));
    }

    _syncInputToSelection() {
        const select = this.querySelector('select.dmb-combobox-carrier');
        const input = this.querySelector('input.dmb-combobox-input');
        const option = [...select.querySelectorAll('option')].find(o => o.value == select.value);
        input.value = (option && select.value !== '') ? option.text : '';
    }

    set value(val) {
        const select = this.querySelector('select.dmb-combobox-carrier');
        select.value = val;
        this._syncInputToSelection();
        this.dispatchEvent(DmbEvents.inputChanged.event);
        this.dispatchEvent(new Event('change'));
    }

    get value() {
        return this.querySelector('select.dmb-combobox-carrier').value;
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
                    this.classList.add('required');
                    element.setAttribute('required', true);
                }
            }
        }

        return validators;
    }

    _runValidators() {
        const input = this.querySelector('input.dmb-combobox-input');
        const select = this.querySelector('select.dmb-combobox-carrier');
        const content = select.value;
        let unknownValidator = () => ({ valid: false, error: 'Unknown validator' });
        let valid = true;
        let validator = null;
        let func = null;
        let result = null;

        for (let i = 0, len = this.validators.length; i < len; i++) {
            validator = this.validators[i];
            func = this.validations['_' + validator.key] || unknownValidator;
            result = func(content, validator.param);
            if (result.valid !== true) { valid = false; break; }
        }

        valid ? this.classList.remove(this._errorInputClass) : this.classList.add(this._errorInputClass);
        valid ? input.setAttribute('valid', '') : input.removeAttribute('valid');
        this.isValid = valid;
    }

    setValidation() {
        this._runValidators();
    }

    resetValidation() {
        const input = this.querySelector('input.dmb-combobox-input');
        this.classList.remove(this._errorInputClass);
        input && input.removeAttribute('valid');
    }

    attributeChangedCallback(attr, oldValue, newValue) {
        const label = this.querySelector('label');
        const select = this.querySelector('select.dmb-combobox-carrier');
        const input = this.querySelector('input.dmb-combobox-input');

        switch (attr) {
        case 'valid':
            this.isValid = (newValue !== null);
            break;
        case 'dmb-name':
            if (select) select.setAttribute('name', newValue);
            break;
        case 'validate':
            if (input) this.validators = this.buildValidators(input, newValue);
            break;
        case 'label':
            if (label) label.innerText = newValue;
            break;
        case 'placeholder':
            if (input) input.placeholder = newValue || '';
            break;
        case 'dmb-value':
            if (!oldValue && newValue && select) this._applyPreselection(select, input);
            break;
        }
    }
}
