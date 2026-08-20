import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbHealthWidget } from './dmb-health-widget.directive.js';

describe('DmbHealthWidget Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbHealthWidget
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbHealthWidget);
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