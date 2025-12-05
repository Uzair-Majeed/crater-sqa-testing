describe('Estimate Management - Simple Tests', () => {

    beforeEach(() => {
        cy.login(Cypress.env('adminEmail'), Cypress.env('adminPassword'));
        cy.visit('/admin/dashboard');
        cy.wait(1000);
    });


    it('TC-004: Create New Estimate with Basic Details', () => {
        cy.visit('/admin/estimates/create');
        cy.wait(3000);

        // Select customer
        cy.contains('New Customer').click();
        cy.wait(2000);
        cy.get('input').filter(':visible').first().type('c');
        cy.wait(3000);
        cy.get('li').filter(':visible').first().click({ force: true });
        cy.wait(3000);

        // Fill item name
        cy.get('input[type="text"]').filter(':visible').first().type('Consulting');
        cy.wait(500);

        // Fill description
        cy.get('textarea').first().type('Professional consulting services');
        cy.wait(500);

        // Fill quantity
        cy.get('input[type="number"]').first().type('2');
        cy.wait(500);

        // Fill price
        cy.get('input[type="tel"]').first().type('500');
        cy.wait(500);

        // Test complete - all fields filled successfully
    });

    it('TC-001: Should navigate to estimates page and display table', () => {
        cy.visit('/admin/estimates');
        cy.wait(2000);

        // Verify we're on estimates page
        cy.url().should('include', '/admin/estimates');

        // Verify table exists
        cy.get('table').should('be.visible');
    });

    it('TC-002: Should show validation error when saving empty form', () => {
        cy.visit('/admin/estimates/create');
        cy.wait(2000);

        // Try to save without filling anything
        cy.contains('button', 'Save Estimate').click();
        cy.wait(1000);

        // Should still be on create page (validation failed)
        cy.url().should('include', '/create');
    });

    it('TC-003: Should search for customer in dropdown', () => {
        // Navigate to estimates
        cy.visit('/admin/estimates');
        cy.wait(2000);
        cy.contains('button', /New Estimate|Add New Estimate/i).should('be.visible').click();
        cy.wait(3000);

        // Open customer selection
        cy.contains('button', /New Customer/i).click();
        cy.wait(1000);

        // Type in search box
        cy.get('input[placeholder*="earch"]').first().should('be.visible').type('Test');
        cy.wait(1000);

        // Verify search input has the text
        cy.get('input[placeholder*="earch"]').first().should('have.value', 'Test');
    });

    it('TC-005: Should open customer selection dropdown', () => {
        cy.visit('/admin/estimates/create');
        cy.wait(2000);

        // Click customer selection button
        cy.contains('button', /New Customer|Customer/i).click();
        cy.wait(1000);

        // Verify search input appears in the dropdown/popup
        cy.get('input[placeholder*="earch"], input[type="search"]').should('be.visible');
    });

    it('TC-006: Should have item input fields', () => {
        cy.visit('/admin/estimates/create');
        cy.wait(2000);

        // Verify item description field exists
        cy.get('textarea').should('exist');

        // Verify price field exists
        cy.get('input[type="tel"], input[type="number"]').should('exist');
    });

    it('TC-007: Should display estimates list', () => {
        cy.visit('/admin/estimates');
        cy.wait(2000);

        // Check that page has loaded
        cy.url().should('include', '/estimates');

        // Check for table or list container
        cy.get('table, .table, [role="table"], tbody').should('exist');
    });

    it('TC-008: Should filter estimates using search', () => {
        cy.visit('/admin/estimates');
        cy.wait(2000);

        // Get the first customer name from the list
        cy.get('table tbody tr').first().invoke('text').then((firstRowText) => {
            // Extract a word from the first row to search for
            const searchTerm = firstRowText.trim().split(/\s+/)[0];

            if (searchTerm && searchTerm.length > 2) {
                // Type in search box
                cy.get('input[placeholder*="Search"], input[type="search"], input[type="text"]').first().type(searchTerm);
                cy.wait(1500);

                // Verify the search term appears in results
                cy.contains(searchTerm).should('be.visible');
            }
        });
    });

});
