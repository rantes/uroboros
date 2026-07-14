import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDonutChart } from './dmb-donut-chart.directive.js';

describe('DmbDonutChart Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbDonutChart
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbDonutChart);
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