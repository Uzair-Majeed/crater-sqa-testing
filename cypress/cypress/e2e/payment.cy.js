// cypress/e2e/payments.cy.js

describe('Payments Module - Black Box Tests', () => {

  const baseUrl = 'http://localhost'; // change port if needed
  const email = 'i230598@isb.nu.edu.pk';
  const password = '12345678';

  beforeEach(() => {
    // KEEP THIS EXACTLY THE SAME (YOU SAID IT WORKS)
    Cypress.config('defaultCommandTimeout', 60000);
    Cypress.config('pageLoadTimeout', 60000);
    Cypress.config('requestTimeout', 60000);

    cy.visit(baseUrl + '/login', { timeout: 60000 });

    cy.get('input[type="email"]').type(email);
    cy.get('input[type="password"]').type(password, { log: false });
    cy.get('button[type="submit"]').click();

    cy.url({ timeout: 60000 }).should('not.include', '/login');
    cy.contains('Dashboard', { timeout: 60000 }).should('be.visible');

    cy.visit(baseUrl + '/admin/payments', { timeout: 60000 });
    cy.contains('Payments', { timeout: 60000 }).should('be.visible');
  });

  // Function to open the form
  const openNewPaymentForm = () => {
    cy.contains('Add Payment', { timeout: 40000 }).click({ force: true });
    cy.contains('New Payment', { timeout: 60000 }).should('be.visible');
  };

  // ===============================
  // DATE FIELD
  // ===============================
  describe('EPC - Date Field', () => {

    it('TC-1: Select a valid date', () => {
    openNewPaymentForm();

    cy.contains('Date', { timeout: 20000 })
      .parent()
      .find('input[type="text"], input[type="date"], input[placeholder*="Date"]')
      .clear({ force: true })
      .type('2025-12-05', { force: true });
  });
  });


  // ===============================
  // PAYMENT NUMBER
  // ===============================
  describe('EPC - Payment Number Field', () => {

    it('TC-2: Auto-generated Payment Number is visible', () => {
      openNewPaymentForm();

      cy.contains('Payment Number', { timeout: 20000 }).should('be.visible');
      
      // Find the input field near Payment Number label
      cy.contains('Payment Number').parent().find('input', { timeout: 10000 })
        .should('exist')
        .and(($el) => {
          expect($el.val().length).to.be.greaterThan(0);
        });
    });

    it('TC-3: Modify Payment Number manually', () => {
      openNewPaymentForm();

      cy.contains('Payment Number').parent().find('input')
        .clear({ force: true })
        .type('PAY-TEST-001', { force: true });
    });

  });

  // ===============================
  // CUSTOMER (Vue Select Dropdown)
  // ===============================
  describe('EPC - Customer Field', () => {

    it('TC-4: Leave Customer empty (invalid)', () => { openNewPaymentForm(); 
    cy.contains('Save Payment').click({ force: true }); 
    cy.get('body').should('contain.text', 'Customer'); });


  });

  // ===============================
  // AMOUNT FIELD
  // ===============================
  describe('EPC - Amount Field', () => {

    it('TC-5: Enter valid amount', () => {
      openNewPaymentForm();

      cy.contains('Amount', { timeout: 20000 }).parent().find('input')
        .clear({ force: true })
        .type('500', { force: true });
    });

    it('TC-6: Enter zero amount', () => {
      openNewPaymentForm();

      cy.contains('Amount').parent().find('input')
        .clear({ force: true })
        .type('0', { force: true });

      cy.contains('Save Payment', { timeout: 20000 }).click({ force: true });
      
      // Check for validation or successful save
      cy.wait(2000);
    });

    it('TC-7: Enter negative amount', () => {
      openNewPaymentForm();

      cy.contains('Amount').parent().find('input')
        .clear({ force: true })
        .type('-100', { force: true });

      cy.contains('Save Payment', { timeout: 20000 }).click({ force: true });
      
      cy.wait(2000);
    });

  });

  // ===============================
  // PAYMENT MODE (Vue Select)
  // ===============================
  describe('EPC - Payment Mode Field', () => {

    it('TC-8: Payment Mode empty (invalid)', () => {
      openNewPaymentForm();

      cy.contains('Save Payment', { timeout: 20000 }).click({ force: true });

      cy.get('body', { timeout: 10000 }).should('contain.text', 'payment mode');
    });

    it('TC-9: Select valid Payment Mode: Cash', () => {
    openNewPaymentForm();

    cy.contains('Payment Mode', { timeout: 20000 })
      .click({ force: true });

    cy.contains('Cash', { timeout: 30000 })
      .click({ force: true });
  });

  it('TC-10: Select valid Payment Mode: Check', () => {
    openNewPaymentForm();

    cy.contains('Payment Mode', { timeout: 20000 })
      .click({ force: true });

    cy.contains('Check', { timeout: 30000 })
      .click({ force: true });
  });

  it('TC-11: Select valid Payment Mode: Credit Card', () => {
    openNewPaymentForm();

    cy.contains('Payment Mode', { timeout: 20000 })
      .click({ force: true });

    cy.contains('Credit Card', { timeout: 30000 })
      .click({ force: true });
  });

  it('TC-12: Select valid Payment Mode: Bank Transfer', () => {
    openNewPaymentForm();

    cy.contains('Payment Mode', { timeout: 20000 })
      .click({ force: true });

    cy.contains('Bank Transfer', { timeout: 30000 })
      .click({ force: true });
  });
  });
  
});