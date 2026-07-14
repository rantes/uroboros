import { DumboTestApp } from '../../libs/dumbojs/dumbo.min.js';
import { DmbLivingDivisionResidentSelect } from './dmb-living-division-resident-select.directive.js';

describe('DmbLivingDivisionResidentSelect Directive', () => {
    let component = null;
    let fixture = null;
    let content = `<dmb-living-division-select>
    <div class="section group">
        <div class="col col12">
            <dmb-button-action
                    action="delete"
                    url="/admin/user_properties/delete/<?=$up->id?>"
                    class="primary"
                    behavior="ajax">
            </dmb-button-action>
        </div>
    </div>
    <div class="section group">
        <div class="col col12">
            <dmb-select
                class="main-unit"
                label=""
                dmb-value=""
            >
                <option value=""></option>
            </dmb-select>
        </div>
    </div>
    <div class="section group">
        <div class="col col12">
            <dmb-select
                class="secondary-unit"
                dmb-name="user_property[0][property_id]"
                label=""
                validate="required"
                dmb-value=""
            >
                <option value=""></option>
            </dmb-select>
        </div>
    </div>
</dmb-living-division-select>`;

    DumboTestApp.setComponents([
        DmbLivingDivisionResidentSelect
    ]);

    beforeEach(() => {
        fixture = DumboTestApp.fixture(DmbLivingDivisionResidentSelect);
        fixture.innerHTML = content;
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