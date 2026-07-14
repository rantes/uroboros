import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbSelect } from './dmb-select.directive.js';

describe('DmbSelect Directive', () => {
    let component = null;
    let fixture = null;
    let input = null;

    DumboTestApp.setComponents([
        DmbSelect
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbSelect);
        component = DumboTestApp.createComponent(fixture);
        component.setAttribute('label', 'label');
        input = component.querySelector('select');

        component.setAttribute('values', true);
        component.value = null;

        component.values = [
            {value: '', text: 'Seleccione', selected: true},
            {value: '1', text: 'Laboral'},
            {value: '2', text: 'Pension'},
            {value: '3', text: 'Accidente'}
        ];
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
        expect(input).toBeDefined();
    });

    it('Should have label', (done) => {
        const label = component.querySelector('label');
        expect(label).toBeDefined();
        done();
    });

    it('Should have select', (done) => {
        expect(input).toBeDefined();
        done();
    });

    it('Should validate required', (done) => {
        component.setAttribute('validate', 'required');
        component.value = '';
        input.dispatchEvent(new Event('focusin'));
        input.dispatchEvent(new Event('blur'));
        expect(input.hasAttribute('valid')).toBe(false);
        component.value = '1';
        input.dispatchEvent(new Event('focusin'));
        input.dispatchEvent(new Event('blur'));
        expect(input.hasAttribute('valid')).toBe(true);
        done();
    });
});
