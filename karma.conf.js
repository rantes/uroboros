module.exports = function(config) {
    config.set({
        frameworks: ['jasmine'],
        // browserNoActivityTimeout: 100000,
        files: [
            'app/webroot/libs/dmb-styles.css',
            'app/webroot/css/styles.css',
            'app/webroot/libs/dumbo.min.js',
            'app/webroot/libs/dmb-factories.min.js',
            'app/webroot/js/factories.min.js',
            'app/webroot/libs/dmb-components.min.js',
            'app/webroot/js/components.min.js',
            {
                pattern: 'ui-sources/components/**/*.spec.js',
                served: true,
                watched: true
            }
        ],
        plugins: [
            require('karma-jasmine'),
            require('karma-chrome-launcher'),
            require('karma-jasmine-html-reporter'),
            require('karma-coverage-istanbul-reporter'),
        ],
        port: 9876,  // karma web server port
        colors: true,
        logLevel: config.LOG_INFO,
        browsers: ['Chrome'],
        autoWatch: false,
        singleRun: false, // Karma captures browsers, runs the tests and exits
        client: {
            clearContext: false // leave Jasmine Spec Runner output visible in browser
        },
        coverageIstanbulReporter: {
            dir: require('path').join(__dirname, '../../coverage/devices-frontend'),
            reports: ['cobertura', 'html'],
            fixWebpackSourcePaths: true
        },
        reporters: ['progress', 'kjhtml', 'dots'],
        concurrency: Infinity
    });
};
