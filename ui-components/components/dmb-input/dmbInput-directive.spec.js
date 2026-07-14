import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbInput } from './dmb-input.directive.js';

describe('DmbInput Directive', () => {
    let component = null;
    let fixture = null;
    let input = null;

    DumboTestApp.setComponents([
        DmbInput
    ]);

    beforeEach((done) => {
        fixture = DumboTestApp.fixture(DmbInput);
        component = DumboTestApp.createComponent(fixture);
        input = component.querySelector('input');
        done();
    });

    afterEach( (done) => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });

    it('Should have label', (done) => {
        const label = component.querySelector('label');
        expect(label).toBeDefined();
        done();
    });

    it('Should have input text', (done) => {
        expect(component).toBeDefined();
        expect(input).toBeDefined();
        done();
    });

    it('Should validate required', (done) => {
        component.setAttribute('validate', 'required');
        input.value = null;
        input.dispatchEvent(new Event('focusin'));
        input.dispatchEvent(new Event('blur'));
        expect(input.hasAttribute('valid')).toBe(false);
        input.value = 'a value';
        input.dispatchEvent(new Event('focusin'));
        input.dispatchEvent(new Event('blur'));
        expect(input.hasAttribute('valid')).toBe(true);
        done();
    });

    it('Should validate email', (done) => {
        component.setAttribute('validate', 'email');
        input.value = 'anything but email address';
        input.dispatchEvent(new Event('focusin'));
        input.dispatchEvent(new Event('blur'));
        expect(input.hasAttribute('valid')).toBe(false);
        done();
    });
});
