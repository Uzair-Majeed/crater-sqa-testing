const { defineConfig } = require('cypress');

module.exports = defineConfig({
    e2e: {
        baseUrl: 'http://localhost',
        viewportWidth: 1920,
        viewportHeight: 1080,
        video: true,
        screenshotOnRunFailure: true,
        defaultCommandTimeout: 30000,
        requestTimeout: 30000,
        responseTimeout: 30000,
        pageLoadTimeout: 60000,
        setupNodeEvents(on, config) {
            // This event listener allows us to log messages to the terminal/console
            // Usage in tests: cy.task('log', 'my message')
            on('task', {
                log(message) {
                    console.log(message);
                    return null;
                },
            });
        },
        specPattern: 'cypress/e2e//*.cy.{js,jsx,ts,tsx}',
        supportFile: 'cypress/support/e2e.js',
    },
    env: {
        adminEmail: 'i230598@isb.nu.edu.pk',
        adminPassword: '12345678',
    },
});