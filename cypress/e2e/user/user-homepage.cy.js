describe('User Homepage Tests', () => {
  beforeEach(() => {
    cy.visit('/src/user/index.php')
  })

  describe('Homepage Layout', () => {
    it('should display the homepage correctly', () => {
      cy.get('h1').should('contain', 'Animal Bite Incident Report')
      cy.get('form').should('be.visible')
    })

    it('should display navigation menu', () => {
      cy.get('.navbar-nav').should('be.visible')
      cy.get('a[href*="index.php"]').should('contain', 'Home')
      cy.get('a[href*="report.php"]').should('contain', 'Report a Bite')
      cy.get('a[href*="information.php"]').should('contain', 'Bite Information')
      cy.get('a[href*="faq.php"]').should('contain', 'FAQs')
      cy.get('a[href*="contact.php"]').should('contain', 'Contact Us')
    })

    it('should display page header', () => {
      cy.get('.page-header').should('be.visible')
      cy.get('.page-header h1').should('contain', 'Animal Bite Incident Report')
      cy.get('.page-header p').should('contain', 'Fill out this form to report an animal bite incident')
    })

    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.navbar-toggler').should('be.visible')
      cy.get('.navbar-collapse').should('not.be.visible')
      
      cy.get('.navbar-toggler').click()
      cy.get('.navbar-collapse').should('be.visible')
    })
  })

  describe('Report Form', () => {
    it('should display report form', () => {
      cy.get('form').should('be.visible')
      cy.get('form').should('have.attr', 'method', 'POST')
      cy.get('form').should('have.attr', 'action', 'submit_report.php')
    })

    it('should have required form fields', () => {
      cy.get('input[name="firstName"]').should('be.visible')
      cy.get('input[name="lastName"]').should('be.visible')
      cy.get('input[name="contactNumber"]').should('be.visible')
      cy.get('input[name="address"]').should('be.visible')
      cy.get('select[name="animalType"]').should('be.visible')
      cy.get('input[name="biteDate"]').should('be.visible')
      cy.get('input[name="biteLocation"]').should('be.visible')
    })

    it('should have optional form fields', () => {
      cy.get('input[name="middleName"]').should('be.visible')
      cy.get('input[name="email"]').should('be.visible')
      cy.get('input[name="biteTime"]').should('be.visible')
      cy.get('textarea[name="biteDescription"]').should('be.visible')
      cy.get('textarea[name="firstAid"]').should('be.visible')
    })

    it('should have form sections', () => {
      cy.get('h3').should('contain', 'Personal Information')
      cy.get('h3').should('contain', 'Bite Information')
      cy.get('h3').should('contain', 'Additional Information')
    })
  })

  describe('Form Validation', () => {
    it('should validate required fields', () => {
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should validate first name field', () => {
      cy.get('input[name="firstName"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="firstName"]').should('have.class', 'is-invalid')
    })

    it('should validate last name field', () => {
      cy.get('input[name="lastName"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="lastName"]').should('have.class', 'is-invalid')
    })

    it('should validate contact number field', () => {
      cy.get('input[name="contactNumber"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="contactNumber"]').should('have.class', 'is-invalid')
    })

    it('should validate address field', () => {
      cy.get('input[name="address"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="address"]').should('have.class', 'is-invalid')
    })

    it('should validate animal type field', () => {
      cy.get('select[name="animalType"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('select[name="animalType"]').should('have.class', 'is-invalid')
    })

    it('should validate bite date field', () => {
      cy.get('input[name="biteDate"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="biteDate"]').should('have.class', 'is-invalid')
    })

    it('should validate bite location field', () => {
      cy.get('input[name="biteLocation"]').focus().blur()
      cy.get('button[type="submit"]').click()
      cy.get('input[name="biteLocation"]').should('have.class', 'is-invalid')
    })

    it('should validate email format', () => {
      cy.get('input[name="email"]').type('invalid-email')
      cy.get('button[type="submit"]').click()
      cy.get('input[name="email"]').should('have.class', 'is-invalid')
    })

    it('should validate contact number format', () => {
      cy.get('input[name="contactNumber"]').type('123')
      cy.get('button[type="submit"]').click()
      cy.get('input[name="contactNumber"]').should('have.class', 'is-invalid')
    })
  })

  describe('Form Submission', () => {
    it('should submit form with valid data', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'submit_report.php')
    })

    it('should display success message after submission', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      cy.get('.alert-success').should('contain', 'Report submitted successfully')
    })

    it('should display reference number after submission', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.get('.reference-number').should('be.visible')
      cy.get('.reference-number').should('contain', 'Reference Number')
    })

    it('should allow printing the report', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.get('button').contains('Print Report').should('be.visible')
    })
  })

  describe('Form Fields', () => {
    it('should have proper input types', () => {
      cy.get('input[name="email"]').should('have.attr', 'type', 'email')
      cy.get('input[name="contactNumber"]').should('have.attr', 'type', 'tel')
      cy.get('input[name="biteDate"]').should('have.attr', 'type', 'date')
      cy.get('input[name="biteTime"]').should('have.attr', 'type', 'time')
    })

    it('should have proper labels', () => {
      cy.get('label[for="firstName"]').should('contain', 'First Name')
      cy.get('label[for="lastName"]').should('contain', 'Last Name')
      cy.get('label[for="contactNumber"]').should('contain', 'Contact Number')
      cy.get('label[for="email"]').should('contain', 'Email')
    })

    it('should have proper placeholders', () => {
      cy.get('input[name="firstName"]').should('have.attr', 'placeholder')
      cy.get('input[name="lastName"]').should('have.attr', 'placeholder')
      cy.get('input[name="contactNumber"]').should('have.attr', 'placeholder')
    })

    it('should have proper help text', () => {
      cy.get('.form-text').should('be.visible')
    })
  })

  describe('Animal Type Selection', () => {
    it('should display animal type options', () => {
      cy.get('select[name="animalType"]').should('contain', 'Dog')
      cy.get('select[name="animalType"]').should('contain', 'Cat')
      cy.get('select[name="animalType"]').should('contain', 'Other')
    })

    it('should show other animal input when Other is selected', () => {
      cy.get('select[name="animalType"]').select('Other')
      cy.get('input[name="otherAnimal"]').should('be.visible')
    })

    it('should hide other animal input when other option is selected', () => {
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('input[name="otherAnimal"]').should('not.be.visible')
    })
  })

  describe('Date and Time Fields', () => {
    it('should have date picker for bite date', () => {
      cy.get('input[name="biteDate"]').click()
      cy.get('input[name="biteDate"]').should('have.focus')
    })

    it('should have time picker for bite time', () => {
      cy.get('input[name="biteTime"]').click()
      cy.get('input[name="biteTime"]').should('have.focus')
    })

    it('should not allow future dates for bite date', () => {
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)
      const tomorrowString = tomorrow.toISOString().split('T')[0]
      
      cy.get('input[name="biteDate"]').type(tomorrowString)
      cy.get('button[type="submit"]').click()
      cy.get('input[name="biteDate"]').should('have.class', 'is-invalid')
    })
  })

  describe('File Upload', () => {
    it('should have file upload field', () => {
      cy.get('input[type="file"]').should('be.visible')
      cy.get('input[type="file"]').should('have.attr', 'accept', 'image/*')
    })

    it('should allow image file upload', () => {
      cy.uploadTestImage('input[type="file"]')
      cy.get('input[type="file"]').should('have.value')
    })

    it('should validate file type', () => {
      cy.get('input[type="file"]').selectFile('cypress/fixtures/test.txt')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })
  })

  describe('Accessibility', () => {
    it('should have proper heading structure', () => {
      cy.get('h1').should('exist')
      cy.get('h2, h3, h4, h5, h6').should('exist')
    })

    it('should have proper form labels', () => {
      cy.get('label').should('be.visible')
    })

    it('should be keyboard navigable', () => {
      cy.get('body').tab()
      cy.focused().should('be.visible')
    })

    it('should have proper ARIA attributes', () => {
      cy.get('form').should('have.attr', 'role', 'form')
    })
  })

  describe('Performance', () => {
    it('should load homepage within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle form submission efficiently', () => {
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'submit_report.php')
    })
  })

  describe('Responsive Design', () => {
    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('form').should('be.visible')
      cy.get('input[name="firstName"]').should('be.visible')
    })

    it('should maintain functionality on tablet', () => {
      cy.viewport('ipad-2')
      cy.get('form').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
    })
  })
})
