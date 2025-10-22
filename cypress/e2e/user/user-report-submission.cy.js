describe('User Report Submission Tests', () => {
  beforeEach(() => {
    cy.visit('/src/user/index.php')
  })

  it('should display homepage with form and handle form validation', () => {
    cy.get('h1').should('contain', 'Animal Bite Incident Report')
    cy.get('form').should('be.visible')
    cy.get('.navbar-nav').should('be.visible')
    cy.get('a[href*="index.php"]').should('contain', 'Home')
    cy.get('a[href*="report.php"]').should('contain', 'Report a Bite')
    cy.get('a[href*="information.php"]').should('contain', 'Bite Information')
    cy.get('a[href*="faq.php"]').should('contain', 'FAQs')
    cy.get('a[href*="contact.php"]').should('contain', 'Contact Us')
    
    // Check form fields
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

  it('should validate form inputs and handle submission', () => {
    // Test validation
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
    
    cy.get('input[name="firstName"]').focus().blur()
    cy.get('button[type="submit"]').click()
    cy.get('input[name="firstName"]').should('have.class', 'is-invalid')
    
    cy.get('input[name="email"]').type('invalid-email')
    cy.get('button[type="submit"]').click()
    cy.get('input[name="email"]').should('have.class', 'is-invalid')
    
    cy.get('input[name="contactNumber"]').type('123')
    cy.get('button[type="submit"]').click()
    cy.get('input[name="contactNumber"]').should('have.class', 'is-invalid')
    
    // Test valid submission
    cy.fillPatientForm()
    cy.fillBiteForm()
    cy.get('button[type="submit"]').click()
    cy.url().should('include', 'submit_report.php')
    cy.get('.alert-success').should('be.visible')
    cy.get('.alert-success').should('contain', 'Report submitted successfully')
    cy.get('.reference-number').should('be.visible')
    cy.get('.reference-number').should('contain', 'Reference Number')
    cy.get('button').contains('Print Report').should('be.visible')
  })

  it('should handle animal type selection and file upload', () => {
    // Test animal type selection
    cy.get('select[name="animalType"]').should('contain', 'Dog')
    cy.get('select[name="animalType"]').should('contain', 'Cat')
    cy.get('select[name="animalType"]').should('contain', 'Other')
    
    cy.get('select[name="animalType"]').select('Other')
    cy.get('input[name="otherAnimal"]').should('be.visible')
    
    cy.get('select[name="animalType"]').select('Dog')
    cy.get('input[name="otherAnimal"]').should('not.be.visible')
    
    // Test file upload
    cy.get('input[type="file"]').should('be.visible')
    cy.get('input[type="file"]').should('have.attr', 'accept', 'image/*')
    
    cy.uploadTestImage('input[type="file"]')
    cy.get('input[type="file"]').should('have.value')
    
    cy.get('input[type="file"]').selectFile('cypress/fixtures/test.txt')
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
  })
})