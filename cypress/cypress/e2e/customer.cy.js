describe('Customer Management - Black Box Testing', () => {

    beforeEach(() => {
        // Login using custom command (uses cy.session for caching)
        cy.login(Cypress.env('adminEmail'), Cypress.env('adminPassword'));

        // Ensure we are on the dashboard before starting
        cy.visit('/admin/dashboard');
    });

    describe('1. Customer Creation Tests', () => {

        it('TC-001: Should successfully create a new customer with all required fields', () => {
            cy.visit('/admin/customers');
            cy.contains('button', /New Customer|Add New Customer/i).click();

            const uniqueName = `Customer ${Date.now()}`;
            cy.get('input[name="name"]').eq(0).should('be.visible').type(uniqueName);
            cy.get('input[name="email"]').type(`test${Date.now()}@example.com`);

            cy.contains('button', /Save|Submit/i).click();

            // Verify success message or redirection
            cy.contains(uniqueName).should('be.visible');
        });

        it('TC-002: Should create customer with all optional fields filled', () => {
            cy.visit('/admin/customers');
            cy.contains('button', /New Customer|Add New Customer/i).click();

            const uniqueName = `Full Customer ${Date.now()}`;
            cy.get('input[name="name"]').eq(0).should('be.visible').type(uniqueName);
            cy.get('input[name="email"]').type(`full${Date.now()}@example.com`);
            cy.get('input[name="phone"]').eq(0).type('+1234567890');
            cy.get('input[type="url"]').type('https://example.com');

            cy.get('textarea[name="billing_street1"]').type('123 Main St');
            cy.get('input[name="billing.city"]').type('New York');
            cy.get('input[name="zip"]').eq(0).type('10001');

            cy.contains('button', /Save|Submit/i).click();
            cy.contains(uniqueName).should('be.visible');
        });

        it('TC-003: Should show validation error when name is empty', () => {
            cy.visit('/admin/customers');
            cy.contains('button', /New Customer|Add New Customer/i).click();

            // Skip name, fill email
            cy.get('input[name="email"]').should('be.visible').type(`noname${Date.now()}@example.com`);

            cy.contains('button', /Save|Submit/i).click();

            // Verify validation error
            cy.contains(/Field is required/i).should('be.visible');
        });

        it('TC-004: Should show validation error for invalid email format', () => {
            cy.visit('/admin/customers');
            cy.contains('button', /New Customer|Add New Customer/i).click();

            cy.get('input[name="name"]').eq(0).should('be.visible').type('Invalid Email User');
            cy.get('input[name="email"]').type('not-an-email');

            cy.contains('button', /Save|Submit/i).click();

            // Verify validation error
            cy.contains(/Incorrect Email/i).should('be.visible');
        });
    });

    describe('2. Customer Viewing & Search Tests', () => {

        it('TC-005: Should display customer list page', () => {
            cy.visit('/admin/customers');

            cy.url().should('include', '/customers');
            cy.contains(/Customers|Customer List/i).should('be.visible');
        });

        it('TC-006: Should search customers by name', () => {
            const searchName = `SearchTarget ${Date.now()}`;
            cy.createCustomer({
                name: searchName,
                email: `search${Date.now()}@example.com`
            });

            cy.visit('/admin/customers');
            cy.get('input[placeholder*="Search"]').type(searchName);
            cy.wait(1000); // Wait for debounce/search

            cy.get('table tbody').should('contain', searchName);
        });
    });

    describe('5. Advanced Creation Scenarios', () => {

        it('TC-007: Should create a customer with ALL fields filled (Basic, Billing, Shipping)', () => {
            cy.visit('/admin/customers');
            cy.contains('button', /New Customer|Add New Customer/i).click();

            const timestamp = Date.now();
            const uniqueName = `AllFields Customer ${timestamp}`;

            // --- Basic Info ---
            cy.get('input[name="name"]').eq(0).type(uniqueName);
            cy.contains('label', 'Primary Contact Name').parent().find('input').type(`Contact ${timestamp}`);
            cy.get('input[name="email"]').type(`allfields${timestamp}@example.com`);
            cy.get('input[name="phone"]').eq(0).type('+15550000000');
            cy.get('input[type="url"]').type('https://craterapp.com');
            cy.get('input[name="name"]').eq(1).type('Mr.');

            // --- Billing Address ---
            cy.get('input[name="address_name"]').eq(0).type(`Billing Name ${timestamp}`);
            cy.get('input[name="billing.state"]').type('California');
            cy.get('input[name="billing.city"]').type('Los Angeles');
            cy.get('textarea[name="billing_street1"]').type('123 Billing St');
            cy.get('textarea[name="billing_street2"]').type('Suite 100');
            cy.get('input[name="phone"]').eq(1).type('+15551111111');
            cy.get('input[name="zip"]').eq(0).type('90001');

            // --- Shipping Address ---
            cy.get('input[name="address_name"]').eq(1).type(`Shipping Name ${timestamp}`);
            cy.get('input[name="shipping.state"]').type('Nevada');
            cy.get('input[name="shipping.city"]').type('Las Vegas');
            cy.get('textarea[name="shipping_street1"]').type('777 Shipping Blvd');
            cy.get('textarea[name="shipping_street2"]').type('Floor 2');
            cy.get('input[name="phone"]').eq(2).type('+15552222222');
            cy.get('input[name="zip"]').eq(1).type('89109');

            cy.contains('button', /Save|Submit/i).click();
            cy.contains(uniqueName).should('be.visible');
        });

        it('TC-008: Should verify "Copy from Billing" functionality', () => {
            cy.visit('/admin/customers');
            cy.contains('button', /New Customer|Add New Customer/i).click();

            const timestamp = Date.now();
            const billingName = `Billing Copy ${timestamp}`;
            const street1 = 'Copy St 1';
            const zip = '12345';

            // Fill Billing
            cy.get('input[name="address_name"]').eq(0).type(billingName);
            cy.get('textarea[name="billing_street1"]').type(street1);
            cy.get('input[name="zip"]').eq(0).type(zip);

            // Click Copy
            cy.contains('button', /Copy from Billing/i).click();

            // Verify Shipping fields are populated
            cy.get('input[name="address_name"]').eq(1).should('have.value', billingName);
            cy.get('textarea[name="shipping_street1"]').should('have.value', street1);
            cy.get('input[name="zip"]').eq(1).should('have.value', zip);
        });
    });
});
