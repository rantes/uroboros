import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbPropertyUsersAdd } from './dmb-property-users-add.directive.js';

describe('DmbPropertyUsersAdd Directive', () => {
    let component = null;
    let fixture = null;

    DumboTestApp.setComponents([
        DmbPropertyUsersAdd
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbPropertyUsersAdd);
        fixture.innerHTML = '<i class="cloner icon icon-plus"></i>';
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