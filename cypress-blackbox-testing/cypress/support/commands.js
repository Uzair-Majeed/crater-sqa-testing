// ***********************************************
// This file contains custom commands for Cypress
// ***********************************************

// Example custom command for handling API requests (if needed)
Cypress.Commands.add('apiLogin', (email, password) => {
    cy.request({
        method: 'POST',
        url: '/api/auth/login',
        body: {
            username: email,
            password: password,
            device_name: 'cypress-test'
        }
    }).then((response) => {
        window.localStorage.setItem('authToken', response.body.token)
    })
})

// Add more custom commands as needed
