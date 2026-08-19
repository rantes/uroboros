import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbCombobox } from './dmb-combobox.directive.js';

describe('DmbCombobox Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbCombobox
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbCombobox);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });

    it('Should populate carrier options from remote JSON (src mode)', () => {
        component._populateRemote([
            { value: '7', label: 'Juan Pérez - 12345678' },
            { value: '9', label: 'Ana Gómez - 87654321' }
        ]);

        const options = component.querySelectorAll('select.dmb-combobox-carrier option');
        // opción vacía inicial + 2 opciones remotas
        expect(options.length).toBe(3);
        expect(options[1].value).toBe('7');
        expect(options[1].text).toBe('Juan Pérez - 12345678');
    });

    it('Should mirror the selected label text to another element via mirror-text-to', () => {
        const target = document.createElement('input');
        target.id = 'mirror-target-test';
        document.body.appendChild(target);
        component.setAttribute('mirror-text-to', '#mirror-target-test');

        component._populateRemote([
            { value: '7', label: 'Juan Pérez - 12345678' }
        ]);
        component._select('7');

        expect(target.value).toBe('Juan Pérez - 12345678');

        target.remove();
    });

    it('Should do nothing when mirror-text-to is absent (no error, no side effect)', () => {
        component._populateRemote([
            { value: '7', label: 'Juan Pérez - 12345678' }
        ]);

        expect(() => component._select('7')).not.toThrow();
    });
});