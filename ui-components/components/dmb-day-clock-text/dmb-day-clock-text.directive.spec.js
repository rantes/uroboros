import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbDayClockText } from './dmb-day-clock-text.directive.js';

describe('DmbDayClockText Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbDayClockText
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbDayClockText);
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