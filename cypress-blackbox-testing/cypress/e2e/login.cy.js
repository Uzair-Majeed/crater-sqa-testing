/// <reference types="cypress" />

describe('Crater Login Tests', () => {
    const validEmail = 'i230695@isb.nu.edu.pk';
    const validPassword = 'shahop5121';

    beforeEach(() => {
        cy.visit('http://localhost/login');
        cy.wait(1000);
    });

    // ==================== ORIGINAL TEST CASES ====================

    it('TC-001: Should successfully login with valid credentials', () => {
        cy.get('input[name="email"]').type(validEmail);
        cy.get('input[name="password"]').type(validPassword);
        cy.contains('button', 'Login').click();
        cy.wait(2000);

        // Verify successful login by checking URL redirect
        cy.url().should('include', '/admin/dashboard');
    });

    it('TC-002: Should show error with invalid credentials', () => {
        cy.get('input[name="email"]').type('wrong@example.com');
        cy.get('input[name="password"]').type('wrongpassword');
        cy.contains('button', 'Login').click();
        cy.wait(1000);

        // Should still be on login page
        cy.url().should('include', '/login');
    });

    // ==================== ADDITIONAL VALID TEST CASES ====================

    it('TC-003: All login inputs exist and are interactable', () => {
        cy.get('input[name="email"]', { timeout: 10000 })
            .should('be.visible')
            .type(validEmail)
            .should('have.value', validEmail);

        cy.get('input[name="password"]')
            .should('be.visible')
            .type(validPassword)
            .should('have.value', validPassword);

        cy.contains('button', 'Login')
            .should('be.visible')
            .and('not.be.disabled');
    });

    it('TC-004: Can copy and paste credentials into fields', () => {
        // Simulate copy-paste behavior
        cy.get('input[name="email"]')
            .invoke('val', validEmail)
            .trigger('input')
            .should('have.value', validEmail);

        cy.get('input[name="password"]')
            .invoke('val', validPassword)
            .trigger('input')
            .should('have.value', validPassword);

        cy.contains('button', 'Login').should('be.visible');
    });

    // ==================== ADDITIONAL INVALID TEST CASES ====================

    it('TC-005: Very long email can be typed into email field', () => {
        const longEmail = 'a'.repeat(300) + '@example.com';

        cy.get('input[name="email"]')
            .type(longEmail)
            .should('have.value', longEmail);

        cy.get('input[name="password"]')
            .type('testpass')
            .should('have.value', 'testpass');
    });

    it('TC-006: Invalid email format can be typed', () => {
        // Missing @ symbol
        cy.get('input[name="email"]')
            .type('i230695isb.nu.edu.pk')
            .should('have.value', 'i230695isb.nu.edu.pk');

        cy.get('input[name="password"]')
            .type(validPassword)
            .should('have.value', validPassword);
    });





});
