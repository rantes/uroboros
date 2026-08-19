import { DumboTestApp } from '/../../libs/dumbojs/dumbo.min.js';
import { DmbTableSort } from './dmb-table-sort.directive.js';

describe('DmbTableSort Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbTableSort
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbTableSort);
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