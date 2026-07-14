import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbConfigPending } from './dmb-config-pending.directive.js';

describe('DmbConfigPending Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbConfigPending
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbConfigPending);
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