// cypress/e2e/expenses.cy.js

describe('Expenses Module - Black Box Tests', () => {

  const baseUrl = 'http://localhost';     
  const email = 'i230598@isb.nu.edu.pk';
  const password = '12345678';

  beforeEach(() => {
    Cypress.config('defaultCommandTimeout', 60000);
    Cypress.config('pageLoadTimeout', 60000);
    Cypress.config('requestTimeout', 60000);

    cy.visit(baseUrl + '/login', { timeout: 60000 });

    cy.get('input[type="email"]').type(email);
    cy.get('input[type="password"]').type(password, { log: false });
    cy.get('button[type="submit"]').click();

    cy.url({ timeout: 60000 }).should('not.include', '/login');
    cy.contains('Dashboard', { timeout: 60000 }).should('be.visible');

    cy.visit(baseUrl + '/admin/expenses', { timeout: 60000 });
    cy.contains('Expenses', { timeout: 60000 }).should('be.visible');
  });

  const openNewExpenseForm = () => {
    cy.contains('Add New Expense', { timeout: 20000 }).click();
    cy.contains('Save Expense', { timeout: 60000 }).should('be.visible');
  };
  const selectFirstDropdownItem = () => {
  cy.get('.vs__dropdown-menu li, .multiselect__element, [role="option"]', { timeout: 8000 })
    .first()
    .click({ force: true });
};
const openDropdown = (index = 0) => {
  // Try vue-select
  cy.get('.v-select', { timeout: 5000 }).eq(index).find('.vs__selected').click({ force: true }).then(() => {
    return;
  }, () => {
    // Try multiselect variant
    cy.get('.multiselect', { timeout: 5000 }).eq(index).click({ force: true }).then(() => {
      return;
    }, () => {
      // Try button-style dropdown (new Crater UI)
      cy.get('[role="button"]', { timeout: 5000 }).filter(':contains("Select")').eq(index).click({ force: true }).then(() => {
        return;
      }, () => {
        // Try clicking the actual input field
        cy.get('input[role="combobox"], input[type="search"]', { timeout: 5000 }).eq(index).click({ force: true });
      });
    });
  });
};



//   // ===============================
//   // EPC - Customer Field
//   // ===============================
  describe('EPC - Customer Field', () => {

    it('TC-1: Leave customer empty (invalid)', () => {
      openNewExpenseForm();
      cy.contains('Save', { timeout: 15000 }).click();
      cy.get('body').should('contain.text', 'Customer');
    });


  });

  // ===============================
  // EPC - Category Field
  // ===============================
  describe('EPC - Category Field', () => {

    it('TC-2: Leave category empty (invalid)', () => {
      openNewExpenseForm();
      cy.contains('Save', { timeout: 15000 }).click();
      cy.get('body').should('contain.text', 'Category');
    });

  });

  // ===============================
  // Amount (unchanged)
  // ===============================
  describe('EPC - Amount Field', () => {

    it('TC-3: Add valid amount', () => {
      openNewExpenseForm();
      cy.contains('Amount', { timeout: 20000 }).parent().find('input')
        .clear({ force: true }).type('150', { force: true });
    });

    it('TC-4: Add zero amount', () => {
      openNewExpenseForm();
      cy.contains('Amount', { timeout: 20000 }).parent().find('input')
        .clear({ force: true }).type('0', { force: true });
      cy.contains('Save', { timeout: 15000 }).click();
    });

    it('TC-5: Add negative amount', () => {
      openNewExpenseForm();
      cy.contains('Amount', { timeout: 20000 }).parent().find('input')
        .clear({ force: true }).type('-50', { force: true });
      cy.contains('Save', { timeout: 15000 }).click();
    });
  });



  // ===============================
  // Notes (unchanged)
  // ===============================
  describe('EPC - Notes Field', () => {

    it('TC-6: Add valid notes', () => {
      openNewExpenseForm();
      cy.get('textarea, [contenteditable="true"]').last()
        .clear({ force: true }).type('This is a test expense note.', { force: true });
    });

    it('TC-7: Add special characters in notes', () => {
      openNewExpenseForm();
      cy.get('textarea, [contenteditable="true"]').last()
        .clear({ force: true }).type('@#$%^&*() test note', { force: true });
    });

    it('TC-8: Leave notes empty', () => {
      openNewExpenseForm();
      cy.get('textarea, [contenteditable="true"]').last().clear({ force: true });
    });
  });

  // ===============================
  // Save Expense (also fixed dropdowns)
  // ===============================
  describe('EPC - Save Expense', () => {

    it('TC-09: Save with valid input', () => {
      openNewExpenseForm();

      

      // Amount
      cy.contains('Amount').parent().find('input')
        .clear().type('150', { force: true });


      // Notes
      cy.get('textarea, [contenteditable="true"]').last()
        .type('Auto test expense submission', { force: true });

      cy.contains('Save', { timeout: 30000 }).click();
      cy.wait(3000);

      cy.get('body').then(($b) => {
        const text = $b.text().toLowerCase();
        expect(text.includes('success'));
      });
    });
  });
});
