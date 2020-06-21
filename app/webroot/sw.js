
const version = '1.0';
const cacheName = `uroboros-${version}`;
const urlBlacklist = [
    '/index/signin',
    '/index/setsesion'
];
const urls = [
    '/',
    '/index/index',
    '/index/login',
    '/favicon.ico',
    '/fonts/icomoon/icomoon.woff',
    '/fonts/openSans/Regular/OpenSans-Regular.ttf'
];

function updateStaticCache() {
    return caches.open(cacheName)
        .then(cache => {
            return cache.addAll(urls);
        });
}

function clearOldCaches() {
    return caches.keys().then(keys => {
        return Promise.all(
            keys
                .filter(key => key.indexOf(cacheName) !== 0)
                .map(key => caches.delete(key))
        );
    });
}

function isHtmlRequest(request) {
    return request.headers.get('Accept').indexOf('text/html') !== -1;
}

// function isJsonRequest(request) {
//     return request.headers.get('Accept').indexOf('application/json') !== -1;
// }

function isListed(url) {
    return urls.filter(bl => url.indexOf(bl) == 0).length > 0;
}

function isBlacklisted(url) {
    return urlBlacklist.filter(bl => url.indexOf(bl) == 0).length > 0;
}


function isCachableResponse(response) {
    return response && response.ok && response.headers.pragma && response.headers.pragma.toLowerCase() === 'cache';
}


self.addEventListener('install', event => {
    event.waitUntil(
        updateStaticCache()
            .then(() => self.skipWaiting())
    );
});


self.addEventListener('activate', event => {
    event.waitUntil(
        clearOldCaches()
            .then(() => self.clients.claim())
    );
});


self.addEventListener('fetch', event => {
    let request = event.request;

    if (request.method !== 'GET') {
        
        if (!navigator.onLine && isHtmlRequest(request)) {
            return event.respondWith(caches.match(urls));
        }
        return;
    }
    if (isHtmlRequest(request)) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (isCachableResponse(response) && !isBlacklisted(response.url) && isListed(response.url)) {
                        let copy = response.clone();
                        caches.open(cacheName).then(cache => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(request)
                        .then(response => {
                            if (!response && request.mode == 'navigate') {
                                return caches.match(urls);
                            }
                            return response;
                        });
                })
        );
    } else if (event.request.cache === 'only-if-cached' && event.request.mode === 'same-origin') {
        return event.respondWith(
            caches.match(request)
                .then(response => {
                    return response || fetch(request)
                        .then(response => {
                            if (isCachableResponse(response)) {
                                let copy = response.clone();
                                caches.open(cacheName).then(cache => cache.put(request, copy));
                            }
                            return response;
                        });
                })
        );
    }
});