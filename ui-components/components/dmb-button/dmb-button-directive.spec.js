import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbButton } from './dmb-button.directive.js';

describe('DmbButton Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbButton
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbButton);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });

    it('Should render component', () => {
        expect(component).toBeDefined();
    });

    it('dmbButtonCancelResetsFormTest', () => {
        // Bloque de definiciones
        const form = document.createElement('dmb-form');

        form.reset = jasmine.createSpy('reset');
        form.append(component);
        component.setAttribute('type', 'cancel');

        component._submitter({ target: component });

        expect(form.reset).toHaveBeenCalled();
        form.remove();
    });

    it('dmbButtonCancelClosesPanelTest', () => {
        // Bloque de definiciones
        const panel = document.createElement('dmb-panel');
        const form = document.createElement('dmb-form');

        panel.close = jasmine.createSpy('close');
        form.reset = jasmine.createSpy('reset');
        form.append(component);
        panel.append(form);
        component.setAttribute('type', 'cancel');

        component._submitter({ target: component });

        expect(form.reset).toHaveBeenCalled();
        expect(panel.close).toHaveBeenCalledWith('cancelled');
        panel.remove();
    });

    it('dmbButtonCancelWithoutPanelTest', () => {
        // Bloque de definiciones
        const form = document.createElement('dmb-form');

        form.reset = jasmine.createSpy('reset');
        form.append(component);
        component.setAttribute('type', 'cancel');

        expect(() => {
            component._submitter({ target: component });
        }).not.toThrow();
        expect(form.reset).toHaveBeenCalled();
        form.remove();
    });

    it('dmbButtonResetUnchangedTest', () => {
        // Bloque de definiciones
        const panel = document.createElement('dmb-panel');
        const form = document.createElement('dmb-form');

        panel.close = jasmine.createSpy('close');
        form.reset = jasmine.createSpy('reset');
        form.append(component);
        panel.append(form);
        component.setAttribute('type', 'reset');

        component._submitter({ target: component });

        expect(form.reset).toHaveBeenCalled();
        expect(panel.close).not.toHaveBeenCalled();
        panel.remove();
    });

    it('dmbButtonOnClickRegistersListenerTest', () => {
        // Bloque de definiciones
        const handler = jasmine.createSpy('handler');

        component.onClick(handler);
        component.dispatchEvent(new Event('click', { bubbles: true }));

        expect(handler).toHaveBeenCalled();
    });

    it('domNativeClickStillWorksOnDmbButtonTest', async () => {
        // Regresión — antes onClick() se llamaba click(), lo que
        // sombreaba HTMLElement.prototype.click() nativo y volvía
        // elemento.click() (sin argumentos) un no-op silencioso.
        // connectedCallback() es async — hay que esperar a que
        // init() termine de correr (marcado por el atributo
        // 'rendered') antes de que el listener de _submitter
        // esté realmente registrado.
        // Bloque de definiciones
        const form = document.createElement('dmb-form');

        form.submit = jasmine.createSpy('submit');
        form.append(component);
        component.setAttribute('type', 'submit');

        await new Promise(resolve => {
            const check = () => component.hasAttribute('rendered') ? resolve() : setTimeout(check, 0);
            check();
        });

        component.click();

        expect(form.submit).toHaveBeenCalled();
        form.remove();
    });
});