import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbOemMetricsFooter } from './dmb-oem-metrics-footer.directive.js';

describe('DmbOemMetricsFooter Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbOemMetricsFooter
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbOemMetricsFooter);
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