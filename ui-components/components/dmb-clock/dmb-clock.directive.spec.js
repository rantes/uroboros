import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbClock } from './dmb-clock.directive.js';

describe('DmbClock Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbClock
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbClock);
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