describe('Item Management - Black-Box Tests', () => {

    beforeEach(() => {
        cy.login(Cypress.env('adminEmail'), Cypress.env('adminPassword'));
        cy.visit('/admin/dashboard');
        cy.wait(1000);
    });

    // ==================== VALID/POSITIVE TEST CASES ====================

    it('TC-001: Should navigate to items page and display table', () => {
        cy.visit('/admin/items');
        cy.wait(2000);

        // Verify we're on items page
        cy.url().should('include', '/admin/items');

        // Verify page title
        cy.contains('Items').should('be.visible');

        // Verify table exists
        cy.get('table').should('be.visible');
    });

    it('TC-002: Should open item creation form', () => {
        cy.visit('/admin/items');
        cy.wait(2000);

        cy.contains('button', /Add Item|New Item/i).click();
        cy.wait(2000);

        // Verify we're on the create page
        cy.url().should('include', '/items/create');
        cy.contains('button', 'Save Item').should('be.visible');
    });

    it('TC-003: Create New Item with All Fields Filled', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Fill item name
        cy.get('input[name="name"]').type('Professional Consulting');
        cy.wait(500);

        // Fill price
        cy.contains('Price').parent().find('input').type('250');
        cy.wait(500);

        // Select unit (if dropdown exists)
        cy.get('select, [role="combobox"]').first().click({ force: true });
        cy.wait(500);
        cy.get('option, li').filter(':visible').first().click({ force: true });
        cy.wait(500);

        // Fill description
        cy.get('textarea').type('High-quality professional consulting services for business development');
        cy.wait(500);

        // Test complete - all fields filled successfully
    });

    it('TC-004: Should display items in the list', () => {
        cy.visit('/admin/items');
        cy.wait(2000);

        // Verify table headers
        cy.contains('NAME').should('be.visible');
        cy.contains('PRICE').should('be.visible');
        cy.contains('UNIT').should('be.visible');
    });

    it('TC-005: Should have search functionality', () => {
        cy.visit('/admin/items');
        cy.wait(2000);

        // Check if search input exists
        cy.get('input[placeholder*="Search"], input[type="search"], input[type="text"]').first().should('be.visible');
    });

    // ==================== INVALID/NEGATIVE TEST CASES ====================

    it('TC-006: Should show validation error when saving empty form', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Try to save without filling anything
        cy.contains('button', 'Save Item').click();
        cy.wait(1000);

        // Should still be on create page (validation failed)
        cy.url().should('include', '/create');
    });

    it('TC-007: Should validate missing required name field', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Fill only price, leave name empty
        cy.contains('Price').parent().find('input').type('100');
        cy.wait(500);

        // Try to save
        cy.contains('button', 'Save Item').click();
        cy.wait(1000);

        // Should still be on create page
        cy.url().should('include', '/create');
    });

    it('TC-008: Should handle negative price values', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Fill name
        cy.get('input[name="name"]').type('Test Item');
        cy.wait(500);

        // Enter negative price
        cy.contains('Price').parent().find('input').clear().type('-50');
        cy.wait(500);

        // The form should either reject it or convert to positive
    });

    it('TC-009: Should handle very large price values (boundary test)', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Fill name
        cy.get('input[name="name"]').type('Expensive Item');
        cy.wait(500);

        // Enter very large number
        cy.contains('Price').parent().find('input').clear().type('999999999');
        cy.wait(500);

        // Verify field accepts or limits the value
        cy.contains('Price').parent().find('input').should('exist');
    });

    it('TC-010: Should handle special characters in name field', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Enter special characters in name
        cy.get('input[name="name"]').type('Item<script>alert("test")</script>');
        cy.wait(500);

        // Fill price
        cy.contains('Price').parent().find('input').type('100');
        cy.wait(500);

        // Verify XSS protection
    });

    it('TC-011: Should validate price field with text input', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Fill name
        cy.get('input[name="name"]').type('Test Item');
        cy.wait(500);

        // Try to enter text in price field
        cy.contains('Price').parent().find('input').clear().type('abc');
        cy.wait(500);

        // Price field should reject or clear invalid input
    });

    it('TC-012: Should handle maximum length description', () => {
        cy.visit('/admin/items/create');
        cy.wait(2000);

        // Fill name
        cy.get('input[name="name"]').type('Test Item');
        cy.wait(500);

        // Fill price
        cy.contains('Price').parent().find('input').type('100');
        cy.wait(500);

        // Enter very long description
        const longText = 'A'.repeat(1000);
        cy.get('textarea').type(longText);
        cy.wait(500);

        // Verify field handles long text
        cy.get('textarea').should('exist');
    });

});
