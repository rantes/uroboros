import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbOperationalTopbar } from './dmb-operational-topbar.directive.js';

describe('DmbOperationalTopbar Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbOperationalTopbar
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbOperationalTopbar);
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