describe('Authentication Tests', () => {
  describe('Admin Login', () => {
    beforeEach(() => {
      cy.visit('/src/login/admin_login.html')
    })

    it('should display admin login page correctly', () => {
      cy.get('span').should('contain', 'Animal Bite Admin Portal')
      cy.get('form').should('exist')
      cy.get('#email').should('be.visible')
      cy.get('#password').should('be.visible')
      cy.get('button[type="submit"]').should('contain', 'Log In')
      cy.get('.login-sidebar').should('be.visible')
      cy.get('#googleSignInBtn').should('be.visible')
    })

    it('should validate form inputs', () => {
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=empty')
      
      cy.get('#email').type('invalid-email')
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid_email')
    })

    it('should handle password visibility toggle', () => {
      cy.get('#password').type('testpassword')
      cy.get('#password').should('have.attr', 'type', 'password')
      
      cy.get('#togglePassword').click()
      cy.get('#password').should('have.attr', 'type', 'text')
      
      cy.get('#togglePassword').click()
      cy.get('#password').should('have.attr', 'type', 'password')
    })

    it('should submit form with valid credentials', () => {
      cy.get('#email').type(Cypress.env('testAdminEmail'))
      cy.get('#password').type(Cypress.env('testAdminPassword'))
      cy.get('button[type="submit"]').click()
      cy.url().should('include', '/admin/admin_dashboard.php')
    })

    it('should handle invalid credentials gracefully', () => {
      cy.get('#email').type('invalid@test.com')
      cy.get('#password').type('wrongpassword')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid')
    })
  })

  describe('Staff Login', () => {
    beforeEach(() => {
      cy.visit('/src/login/staff_login.html')
    })

    it('should display staff login page correctly', () => {
      cy.get('h3').should('contain', 'Staff Login')
      cy.get('form').should('exist')
      cy.get('#email').should('be.visible')
      cy.get('#password').should('be.visible')
      cy.get('button[type="submit"]').should('contain', 'Log In')
      cy.get('.login-sidebar').should('be.visible')
      cy.get('#googleSignInBtn').should('be.visible')
    })

    it('should validate form inputs', () => {
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=empty')
      
      cy.get('#email').type('invalid-email')
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid_email')
    })

    it('should handle password visibility toggle', () => {
      cy.get('#password').type('testpassword')
      cy.get('#password').should('have.attr', 'type', 'password')
      
      cy.get('#togglePassword').click()
      cy.get('#password').should('have.attr', 'type', 'text')
      
      cy.get('#togglePassword').click()
      cy.get('#password').should('have.attr', 'type', 'password')
    })

    it('should submit form with valid credentials', () => {
      cy.get('#email').type(Cypress.env('testStaffEmail'))
      cy.get('#password').type(Cypress.env('testStaffPassword'))
      cy.get('button[type="submit"]').click()
      cy.url().should('include', '/staff/dashboard.php')
    })

    it('should handle invalid credentials gracefully', () => {
      cy.get('#email').type('invalid@test.com')
      cy.get('#password').type('wrongpassword')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid')
    })
  })

  describe('Google Authentication', () => {
    it('should display Google Sign-In buttons on both login pages', () => {
      cy.visit('/src/login/admin_login.html')
      cy.get('#googleSignInBtn').should('be.visible')
      cy.get('#googleSignInBtn').should('contain', 'Continue with Google')
      cy.get('#googleSignInBtn svg').should('exist')
      
      cy.visit('/src/login/staff_login.html')
      cy.get('#googleSignInBtn').should('be.visible')
      cy.get('#googleSignInBtn').should('contain', 'Continue with Google')
      cy.get('#googleSignInBtn svg').should('exist')
    })

    it('should handle Google OAuth flow', () => {
      cy.visit('/src/login/google_auth.php?type=admin')
      cy.url().should('include', 'accounts.google.com')
      cy.url().should('include', 'scope=email+profile')
      cy.url().should('include', 'response_type=code')
      cy.url().should('include', 'state=admin')
    })

    it('should handle Google OAuth errors', () => {
      cy.visit('/src/login/admin_login.html?error=google_user_not_found')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'No admin account found with this Google email')
      
      cy.visit('/src/login/staff_login.html?error=google_auth_failed')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Google authentication failed')
    })
  })

  describe('Error Handling', () => {
    it('should display appropriate error messages', () => {
      cy.visit('/src/login/admin_login.html?error=invalid')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Invalid email or password. Please try again.')
      
      cy.visit('/src/login/admin_login.html?error=empty')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Please enter both email and password.')
      
      cy.visit('/src/login/admin_login.html?error=database_error')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'A system error occurred. Please try again later.')
    })

    it('should have shake animation on error message', () => {
      cy.visit('/src/login/admin_login.html?error=invalid')
      cy.get('#error-message').should('have.css', 'animation')
    })
  })

  describe('Security Features', () => {
    it('should not expose sensitive data in URLs or page source', () => {
      cy.visit('/src/login/admin_login.html')
      cy.get('#email').type('admin@test.com')
      cy.get('#password').type('secretpassword')
      cy.get('button[type="submit"]').click()
      cy.url().should('not.contain', 'secretpassword')
      cy.get('body').should('not.contain', 'secretpassword')
    })

    it('should have proper form attributes', () => {
      cy.visit('/src/login/admin_login.html')
      cy.get('form').should('have.attr', 'method', 'POST')
      cy.get('form').should('have.attr', 'action', 'admin_login.php')
      cy.get('#email').should('have.attr', 'type', 'email')
      cy.get('#password').should('have.attr', 'type', 'password')
    })

    it('should be accessible', () => {
      cy.visit('/src/login/admin_login.html')
      cy.get('label[for="email"]').should('contain', 'Email Address')
      cy.get('label[for="password"]').should('contain', 'Password')
      cy.get('#error-message').should('have.attr', 'role', 'alert')
      cy.get('#success-message').should('have.attr', 'role', 'alert')
    })
  })

  describe('Performance', () => {
    it('should load pages within acceptable time', () => {
      cy.visit('/src/login/admin_login.html')
      cy.measurePageLoad()
      
      cy.visit('/src/login/staff_login.html')
      cy.measurePageLoad()
    })

    it('should handle rapid form submissions', () => {
      cy.visit('/src/login/admin_login.html')
      cy.get('#email').type('test@test.com')
      cy.get('#password').type('testpassword')
      
      cy.get('button[type="submit"]').click()
      cy.get('button[type="submit"]').click()
      cy.get('button[type="submit"]').click()
      
      cy.url().should('include', 'error=invalid')
    })
  })
})
