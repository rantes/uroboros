import { DumboTestApp } from '/ui-components/libs/dumbojs/dumbo.min.js';
import { DmbBookingCalendar } from './dmb-booking-calendar.directive.js';

describe('DmbBookingCalendar Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbBookingCalendar
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbBookingCalendar);
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