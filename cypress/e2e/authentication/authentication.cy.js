describe('Authentication Tests', () => {
  describe('Staff Login Form', () => {
    beforeEach(() => {
      cy.visit('/src/login/staff_login.html')
    })

    it('should display staff login form elements', () => {
      cy.get('input[name="email"]').should('be.visible')
      cy.get('input[name="password"]').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
      cy.contains('h3', 'Staff Login').should('be.visible')
    })

    it('should display staff login page branding', () => {
      cy.contains('.login-logo', 'BHW Portal').should('be.visible')
      cy.contains('h2', 'Welcome Back!').should('be.visible')
      cy.contains('p', 'Log in to access the Barangay Health Workers Portal.').should('be.visible')
    })

    it('should handle form submission', () => {
      cy.get('input[name="email"]').type('test@example.com')
      cy.get('input[name="password"]').type('testpassword')
      cy.get('button[type="submit"]').click()
      // Should redirect to staff login processing
      cy.url().should('include', 'staff_login.php')
    })

    it('should display Google sign-in option', () => {
      cy.contains('button', 'Continue with Google').should('be.visible')
    })
  })

  describe('Admin Login Form', () => {
    beforeEach(() => {
      cy.visit('/src/login/admin_login.html')
    })

    it('should display admin login form elements', () => {
      cy.get('input[name="email"]').should('be.visible')
      cy.get('input[name="password"]').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
      cy.contains('h3', 'Admin Login').should('be.visible')
    })

    it('should display admin login page branding', () => {
      cy.contains('.login-logo', 'Animal Bite Admin Portal').should('be.visible')
      cy.contains('h2', 'City Health Worker Access').should('be.visible')
      cy.contains('p', 'Log in to access the Animal Bite Incident Admin Portal.').should('be.visible')
    })

    it('should handle form submission', () => {
      cy.get('input[name="email"]').type('admin@example.com')
      cy.get('input[name="password"]').type('adminpassword')
      cy.get('button[type="submit"]').click()
      // Should redirect to admin login processing
      cy.url().should('include', 'admin_login.php')
    })

    it('should display Google sign-in option', () => {
      cy.contains('button', 'Continue with Google').should('be.visible')
    })
  })

  describe('Login Error Handling', () => {
    it('should display error message for invalid credentials', () => {
      cy.visit('/src/login/staff_login.html?error=invalid')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Invalid email or password')
    })

    it('should display error message for empty fields', () => {
      cy.visit('/src/login/staff_login.html?error=empty')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Please enter both email and password')
    })

    it('should display error message for invalid email', () => {
      cy.visit('/src/login/staff_login.html?error=invalid_email')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Please enter a valid email address')
    })
  })
})