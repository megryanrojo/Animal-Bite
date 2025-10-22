describe('User Report Submission Tests', () => {
  beforeEach(() => {
    cy.visit('/src/user/index.php')
  })

  describe('Homepage and Form Display', () => {
    it('should display homepage with navigation and form', () => {
      cy.get('h1').should('contain', 'Animal Bite Incident Report')
      cy.get('form').should('be.visible')
      cy.get('.navbar-nav').should('be.visible')
      cy.get('a[href*="index.php"]').should('contain', 'Home')
      cy.get('a[href*="report.php"]').should('contain', 'Report a Bite')
      cy.get('a[href*="information.php"]').should('contain', 'Bite Information')
      cy.get('a[href*="faq.php"]').should('contain', 'FAQs')
      cy.get('a[href*="contact.php"]').should('contain', 'Contact Us')
    })

    it('should display form with all required and optional fields', () => {
      cy.get('form').should('have.attr', 'method', 'POST')
      cy.get('form').should('have.attr', 'action', 'submit_report.php')
      
      // Required fields
      cy.get('input[name="firstName"]').should('be.visible')
      cy.get('input[name="lastName"]').should('be.visible')
      cy.get('input[name="contactNumber"]').should('be.visible')
      cy.get('input[name="address"]').should('be.visible')
      cy.get('select[name="animalType"]').should('be.visible')
      cy.get('input[name="biteDate"]').should('be.visible')
      cy.get('input[name="biteLocation"]').should('be.visible')
      
      // Optional fields
      cy.get('input[name="middleName"]').should('be.visible')
      cy.get('input[name="email"]').should('be.visible')
      cy.get('input[name="biteTime"]').should('be.visible')
      cy.get('textarea[name="biteDescription"]').should('be.visible')
      cy.get('textarea[name="firstAid"]').should('be.visible')
      
      // Form sections
      cy.get('h3').should('contain', 'Personal Information')
      cy.get('h3').should('contain', 'Bite Information')
      cy.get('h3').should('contain', 'Additional Information')
    })

    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.navbar-toggler').should('be.visible')
      cy.get('.navbar-toggler').click()
      cy.get('.navbar-collapse').should('be.visible')
      
      cy.get('form').should('be.visible')
      cy.get('input[name="firstName"]').should('be.visible')
    })
  })

  describe('Form Validation', () => {
    it('should validate required fields', () => {
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      
      cy.get('input[name="firstName"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="firstName"]').should('have.class', 'is-invalid')
      
      cy.get('input[name="lastName"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="lastName"]').should('have.class', 'is-invalid')
      
      cy.get('input[name="contactNumber"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="contactNumber"]').should('have.class', 'is-invalid')
    })

    it('should validate field formats', () => {
      cy.get('input[name="email"]').type('invalid-email')
      cy.get('button[type="submit"]').click()
      cy.get('input[name="email"]').should('have.class', 'is-invalid')
      
      cy.get('input[name="contactNumber"]').type('123')
      cy.get('button[type="submit"]').click()
      cy.get('input[name="contactNumber"]').should('have.class', 'is-invalid')
    })

    it('should validate date fields', () => {
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)
      const tomorrowString = tomorrow.toISOString().split('T')[0]
      
      cy.get('input[name="biteDate"]').type(tomorrowString)
      cy.get('button[type="submit"]').click()
      cy.get('input[name="biteDate"]').should('have.class', 'is-invalid')
    })
  })

  describe('Form Submission', () => {
    it('should submit form with valid data', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'submit_report.php')
      cy.get('.alert-success').should('be.visible')
      cy.get('.alert-success').should('contain', 'Report submitted successfully')
    })

    it('should display reference number and print option', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.get('.reference-number').should('be.visible')
      cy.get('.reference-number').should('contain', 'Reference Number')
      cy.get('button').contains('Print Report').should('be.visible')
    })
  })

  describe('Animal Type and File Upload', () => {
    it('should handle animal type selection', () => {
      cy.get('select[name="animalType"]').should('contain', 'Dog')
      cy.get('select[name="animalType"]').should('contain', 'Cat')
      cy.get('select[name="animalType"]').should('contain', 'Other')
      
      cy.get('select[name="animalType"]').select('Other')
      cy.get('input[name="otherAnimal"]').should('be.visible')
      
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('input[name="otherAnimal"]').should('not.be.visible')
    })

    it('should handle file upload with validation', () => {
      cy.get('input[type="file"]').should('be.visible')
      cy.get('input[type="file"]').should('have.attr', 'accept', 'image/*')
      
      cy.uploadTestImage('input[type="file"]')
      cy.get('input[type="file"]').should('have.value')
      
      cy.get('input[type="file"]').selectFile('cypress/fixtures/test.txt')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })
  })

  describe('Accessibility and Performance', () => {
    it('should be accessible and performant', () => {
      cy.get('h1').should('exist')
      cy.get('h2, h3, h4, h5, h6').should('exist')
      cy.get('label').should('be.visible')
      cy.get('body').tab()
      cy.focused().should('be.visible')
      cy.get('form').should('have.attr', 'role', 'form')
      
      cy.measurePageLoad()
    })
  })
})
