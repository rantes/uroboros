import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbDocumentUploader } from './dmb-document-uploader.directive.js';

describe('DmbDocumentUploader Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbDocumentUploader
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbDocumentUploader);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });

    it('Should not throw and should wire up file selection correctly even when the nested dmb-input renders asynchronously (race condition fix)', async () => {
        // dmb-document-uploader usa templateUrl (fetch async) — espera a
        // que termine antes de inspeccionar su interior.
        if (!component.hasAttribute('rendered')) {
            await new Promise(resolve => {
                component.addEventListener('dmb.after-rendered', resolve, { once: true });
            });
        }

        const dmbFileInput = component.querySelector('dmb-input[type="file"]');
        expect(dmbFileInput).not.toBeNull();

        // dmb-input es OTRO componente DumboJS con su propio
        // connectedCallback() async — este es exactamente el escenario que
        // antes causaba "Cannot read properties of null": si no ha
        // terminado su propio ciclo de renderizado, init() del padre debía
        // esperar su evento dmb.after-rendered en vez de asumir que el
        // <input> real ya existe.
        if (!dmbFileInput.hasAttribute('rendered')) {
            await new Promise(resolve => {
                dmbFileInput.addEventListener('dmb.after-rendered', resolve, { once: true });
            });
        }

        const fileInput = dmbFileInput.querySelector('input[type="file"]');
        expect(fileInput).not.toBeNull();

        // Confirma que, pese a la carrera, el listener de "change" sí quedó
        // conectado — selecciona un archivo y verifica que loadFile() lo
        // procese (nombre reflejado en .preview .filename).
        const file = new File(['contenido'], 'factura.pdf', { type: 'application/pdf' });
        Object.defineProperty(fileInput, 'files', { value: [file], configurable: true });
        fileInput.dispatchEvent(new Event('change'));

        await new Promise(resolve => setTimeout(resolve, 50));

        const filenameLabel = component.querySelector('.preview .filename');
        expect(filenameLabel.textContent).toBe('factura.pdf');
    });
});