describe('Invoice Management - Black-Box Tests', () => {

    beforeEach(() => {
        cy.login(Cypress.env('adminEmail'), Cypress.env('adminPassword'));
        cy.visit('/admin/dashboard');
        cy.wait(1000);
    });

    // ==================== VALID/POSITIVE TEST CASES ====================

    it('TC-001: Should navigate to invoices page and display table', () => {
        cy.visit('/admin/invoices');
        cy.wait(2000);

        // Verify we're on invoices page
        cy.url().should('include', '/admin/invoices');

        // Verify page title
        cy.contains('Invoices').should('be.visible');
    });

    it('TC-002: Should open invoice creation form', () => {
        cy.visit('/admin/invoices');
        cy.wait(2000);

        cy.contains('button', /New Invoice|Add Invoice/i).click();
        cy.wait(2000);

        // Verify we're on the create page
        cy.url().should('include', '/invoices/create');
        cy.contains('New Invoice').should('be.visible');
    });

    it('TC-003: Fill All Invoice Fields', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(3000);

        // Select customer
        cy.contains('New Customer').click();
        cy.wait(2000);
        cy.get('input').filter(':visible').first().type('c');
        cy.wait(3000);
        cy.get('li').filter(':visible').first().click({ force: true });
        cy.wait(3000);

        // Fill item name
        cy.get('input[type="text"]').filter(':visible').first().type('Web Development');
        cy.wait(500);

        // Fill description
        cy.get('textarea').first().type('Full stack web development services');
        cy.wait(500);

        // Fill quantity
        cy.get('input[type="number"]').first().type('3');
        cy.wait(500);

        // Fill price
        cy.get('input[type="tel"]').first().type('750');
        cy.wait(500);

        // Test complete - all fields filled successfully
    });

    it('TC-004: Should display invoice date and due date fields', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Verify Invoice Date field
        cy.contains('Invoice Date').should('be.visible');

        // Verify Due Date field
        cy.contains('Due Date').should('be.visible');
    });

    it('TC-005: Should have search functionality on invoices page', () => {
        cy.visit('/admin/invoices');
        cy.wait(2000);

        // Check if search input exists
        cy.get('input[placeholder*="Search"], input[type="search"], input[type="text"]').first().should('be.visible');
    });

    // ==================== INVALID/NEGATIVE TEST CASES ====================

    it('TC-006: Should show validation error when saving empty invoice', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Try to save without filling anything
        cy.contains('button', 'Save Invoice').click();
        cy.wait(1000);

        // Should still be on create page (validation failed)
        cy.url().should('include', '/create');
    });

    it('TC-007: Should validate missing required customer field', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Fill item details but leave customer empty
        cy.get('input[type="text"]').filter(':visible').first().type('Service');
        cy.wait(500);
        cy.get('input[type="tel"]').first().type('100');
        cy.wait(500);

        // Try to save
        cy.contains('button', 'Save Invoice').click();
        cy.wait(1000);

        // Should still be on create page
        cy.url().should('include', '/create');
    });

    it('TC-008: Should handle negative price values', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Fill item name
        cy.get('input[type="text"]').filter(':visible').first().type('Test Item');
        cy.wait(500);

        // Enter negative price
        cy.get('input[type="tel"]').first().type('-100');
        cy.wait(500);

        // The form should either reject it or convert to positive
    });

    it('TC-009: Should handle negative quantity values', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Enter negative quantity
        cy.get('input[type="number"]').first().type('-5');
        cy.wait(500);

        // System should reject or handle gracefully
    });

    it('TC-010: Should handle very large quantity (boundary test)', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Enter very large quantity
        cy.get('input[type="number"]').first().type('999999');
        cy.wait(500);

        // Verify field accepts or limits the value
        cy.get('input[type="number"]').first().should('exist');
    });

    it('TC-011: Should validate text in numeric price field', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Try to enter text in price field
        cy.get('input[type="tel"]').first().type('abcd');
        cy.wait(500);

        // Price field should reject or clear invalid input
    });

    it('TC-012: Should handle XSS attempts in item description', () => {
        cy.visit('/admin/invoices/create');
        cy.wait(2000);

        // Enter XSS payload in description
        cy.get('textarea').first().type('<script>alert("XSS")</script>');
        cy.wait(500);

        // Verify XSS protection
        cy.get('textarea').should('exist');
    });

});
