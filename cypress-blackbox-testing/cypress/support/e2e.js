// ***********************************************************
// This file is processed and loaded automatically before your test files.
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Disable screenshot and video on test failure to speed up tests (optional)
Cypress.Screenshot.defaults({
    screenshotOnRunFailure: true
})

// Custom command to handle common login
Cypress.Commands.add('login', (email, password) => {
    cy.session([email, password], () => {
        cy.visit('/login')
        cy.get('input[type="email"]').type(email)
        cy.get('input[type="password"]').type(password)
        cy.get('button[type="submit"]').click()
        // Wait for dashboard to ensure login was successful
        cy.url().should('include', '/admin/dashboard')
        cy.contains('Dashboard').should('be.visible')
    }, {
        validate: () => {
            // Validate session is still active
            cy.getCookie('XSRF-TOKEN').should('exist')
            cy.getCookie('laravel_session').should('exist')
        }
    })
})

// Custom command to navigate to customers
Cypress.Commands.add('goToCustomers', () => {
    cy.contains('Customers').click()
    cy.wait(1000)
})

// Custom command to create a customer
Cypress.Commands.add('createCustomer', (customerData) => {
    cy.visit('/admin/customers')
    cy.contains('button', /New Customer|Add New Customer/i).click()
    cy.wait(1000)

    cy.get('input[name="name"]').eq(0).type(customerData.name)
    cy.get('input[name="email"]').type(customerData.email)

    if (customerData.phone) {
        cy.get('input[name="phone"]').type(customerData.phone)
    }

    if (customerData.website) {
        cy.get('input[name="website"]').type(customerData.website)
    }

    cy.contains('button', /Save|Submit/i).click()
    cy.wait(2000)
})
