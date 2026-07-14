import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbBarChart } from './dmb-bar-chart.directive.js';

describe('DmbBarChart Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbBarChart
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbBarChart);
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