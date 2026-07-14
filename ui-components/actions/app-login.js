import {
    DumboApp
} from '../libs/dumbojs/dumbo.min.js';
import { DmbLogin } from '../components/dmb-login/dmb-login.directive.js';

class AppLogin extends DumboApp {
    constructor() {
        super();

        this.components = [
            DmbLogin
        ];
    }

}

const login = new AppLogin();
login.buildComponents();
