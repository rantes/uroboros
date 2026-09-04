import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbFailureSummary } from './dmb-failure-summary.directive.js';

describe('DmbFailureSummary Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbFailureSummary
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbFailureSummary);
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