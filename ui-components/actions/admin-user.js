import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbUserPhoto } from '../components/dmb-user-photo/dmb-user.photo.directive.js';
import { DmbLivingDivisionSelect } from '../components/dmb-living-division-select/dmb-living-division-select.directive.js';
import { DmbPropertyUsersAdd } from '../components/dmb-property-users-add/dmb-property-users-add.directive.js';
import { DmbPhotoCamera } from '../components/dmb-photo-camera/dmb-photo-camera.directive.js';

export class AdminUser extends DumboApp {
    constructor() {
        super();

        this.components = [
            DmbPhotoCamera,
            DmbUserPhoto,
            DmbLivingDivisionSelect,
            DmbPropertyUsersAdd
        ];
    }

}

const adminUser = new AdminUser();
adminUser.buildComponents();
