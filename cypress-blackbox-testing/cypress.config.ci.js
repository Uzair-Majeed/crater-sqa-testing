const { defineConfig } = require('cypress');

module.exports = defineConfig({
    e2e: {
        baseUrl: 'http://127.0.0.1:8000',
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
        // CI credentials (from .env.cypress)
        adminEmail: 'admin@craterapp.com',
        adminPassword: 'crater@123',
    },
});
