window.innerWidth = window.outerWidth;
export const CURRENT_LOCALE = 'es_CO';
export const CURRENT_ACTION = 'index';
export const CURRENT_CONTROLLER = 'index';

export const endpoints = {
    livingDivisions: '/params/livingDivisions',
    residents: '/params/residents',
    admin: {
        login: '/admin/signin'
    }
};

export const appEvents = {
    cacheReset: { event: new Event('cache.reset'), listener: 'cache.reset' },
    launchIdReader: { event: new Event('launch.id.reader'), listener: 'launch.id.reader' }
};

