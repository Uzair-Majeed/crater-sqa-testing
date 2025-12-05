describe('Authentication System Tests', () => {

    it('TC-LOGIN-001: Should successfully login with valid credentials', () => {
        // 1. Visit the login page
        cy.visit('/login');

        // 2. Enter Email (from config)
        cy.get('input[type="email"]').type(Cypress.env('adminEmail'));

        // 3. Enter Password (from config)
        cy.get('input[type="password"]').type(Cypress.env('adminPassword'));

        // 4. Click Login Button
        cy.get('button[type="submit"]').click();

        // 5. Verify Login was successful
        // Check URL changes
        cy.url().should('not.include', '/login');
        cy.url().should('include', '/admin');

        // Check Dashboard is visible
        cy.contains('Dashboard').should('be.visible');
    });

    it('TC-LOGIN-002: Should show error with invalid credentials', () => {
        cy.visit('/login');

        // Enter wrong password
        cy.get('input[type="email"]').type(Cypress.env('adminEmail'));
        cy.get('input[type="password"]').type('wrongpassword123');
        cy.get('button[type="submit"]').click();

        // Verify we are still on login page
        cy.url().should('include', '/login');

        // Verify error message appears (adjust selector if needed)
        cy.get('body').should('contain', 'These credentials do not match our records');
    });



});
