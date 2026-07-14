import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbParkingLot } from './dmb-parking-lot.directive.js';

describe('DmbParkingLot Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbParkingLot
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbParkingLot);
        component = DumboTestApp.createComponent(fixture);
    });

    afterEach( done => {
        component && component.remove();
        done();
    });


    it('Should render element', () => {
        expect(component).toBeDefined();
    });
});