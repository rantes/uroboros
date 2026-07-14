import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbWysiwyg } from './dmb-wysiwyg.directive.js';

describe('DmbWYSIWYG Directive', () => {
    let component = null;
    let fixture = null;
    let textarea = null;
    let editarea = null;

    DumboTestApp.setComponents([
        DmbWysiwyg
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbWysiwyg);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });

    beforeAll((done) => {
        textarea = element.querySelector('textarea.dmb-wysiwyg__content-content');
        editarea = element.querySelector('section.dmb-wysiwyg__content-content');
        done();
    });

    beforeEach((done) => {
        textarea.value = '';
        editarea.innerHTML = '';
        done();
    });

    it('Should render element', (done) => {
        expect(element).toBeDefined();
        done();
    });

    it('Should check fields if have data', (done) => {
        element.setAttribute('validate', 'required');
        textarea.value = 'prueba';
        editarea.innerHTML = 'prueba';
        editarea.dispatchEvent(new Event('focusin'));
        editarea.dispatchEvent(new Event('blur'));
        const errMsg = element.querySelector('span.error-container').innerHTML;
        expect(errMsg).toBe('');
        done();
    });

    it('Should sync data from textarea to editarea', (done) => {
        editarea.setAttribute('hidden', true);
        textarea.removeAttribute('hidden');
        editarea.innerHTML = '';
        textarea.value = 'texto';
        element.syncData();
        expect(editarea.innerHTML).toBe('texto');
        done();
    });

    it('Should sync data from editarea to textarea', (done) => {
        textarea.setAttribute('hidden', true);
        editarea.removeAttribute('hidden');
        textarea.value = '';
        editarea.innerHTML = 'texto';
        element.syncData();
        expect(textarea.value).toBe('texto');
        done();
    });

    it('Should validate required in textarea', (done) => {
        element.setAttribute('validate', 'required');
        editarea.setAttribute('hidden',true);
        textarea.removeAttribute('hidden');
        textarea.value = null;
        textarea.dispatchEvent(new Event('focusin'));
        textarea.dispatchEvent(new Event('blur'));
        const errMsg = element.querySelector('span.error-container').innerHTML;
        expect(errMsg).toBe('Este campo es obligatorio');
        done();
    });

    it('Should validate required in editarea', (done) => {
        element.setAttribute('validate', 'required');
        textarea.setAttribute('hidden',true);
        editarea.removeAttribute('hidden');
        editarea.innerHTML = '';
        editarea.dispatchEvent(new Event('focusin'));
        editarea.dispatchEvent(new Event('blur'));
        const errMsg = element.querySelector('span.error-container').innerHTML;
        expect(errMsg).toBe('Este campo es obligatorio');
        done();
    });
});

// describe('dmbWYSIWYG Directive', () => {
//     let element = null;
//     let textarea = null;
//     let editarea = null;
//     let container = null;

//     element = document.createElement('dmb-wysiwyg');
//     container = document.querySelector('#components');
//     container.append(element);

//     afterEach( done => {
//         element && element.remove();
//         done();
//     });


// });