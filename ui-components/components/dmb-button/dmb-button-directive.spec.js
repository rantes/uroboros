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
});