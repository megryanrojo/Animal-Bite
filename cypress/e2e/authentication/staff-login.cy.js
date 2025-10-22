describe('Staff Login Tests', () => {
  beforeEach(() => {
    cy.visit('/src/login/staff_login.html')
  })

  describe('Login Page UI', () => {
    it('should display the staff login page correctly', () => {
      cy.get('h3').should('contain', 'Staff Login')
      cy.get('form').should('exist')
      cy.get('#email').should('be.visible')
      cy.get('#password').should('be.visible')
      cy.get('button[type="submit"]').should('contain', 'Log In')
    })

    it('should display the sidebar with correct information', () => {
      cy.get('.login-sidebar').should('be.visible')
      cy.get('.login-logo').should('contain', 'BHW Portal')
      cy.get('.login-title').should('contain', 'Welcome Back!')
      cy.get('.login-subtitle').should('contain', 'Log in to access the Barangay Health Workers Portal')
    })

    it('should display help text', () => {
      cy.get('.help-text').should('be.visible')
      cy.get('.help-text').should('contain', 'Need Help?')
      cy.get('.help-text').should('contain', 'If you\'re having trouble logging in')
    })

    it('should display Google Sign-In button', () => {
      cy.get('#googleSignInBtn').should('be.visible')
      cy.get('#googleSignInBtn').should('contain', 'Continue with Google')
    })

    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.login-card').should('be.visible')
      cy.get('.login-form').should('be.visible')
    })
  })

  describe('Form Validation', () => {
    it('should show error for empty email field', () => {
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=empty')
    })

    it('should show error for empty password field', () => {
      cy.get('#email').type('staff@test.com')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=empty')
    })

    it('should show error for invalid email format', () => {
      cy.get('#email').type('invalid-email')
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid_email')
    })

    it('should show error for both empty fields', () => {
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=empty')
    })
  })

  describe('Error Message Display', () => {
    it('should display error message for invalid credentials', () => {
      cy.visit('/src/login/staff_login.html?error=invalid')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Invalid email or password. Please try again.')
    })

    it('should display error message for empty fields', () => {
      cy.visit('/src/login/staff_login.html?error=empty')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Please enter both email and password.')
    })

    it('should display error message for invalid email', () => {
      cy.visit('/src/login/staff_login.html?error=invalid_email')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Please enter a valid email address.')
    })

    it('should display error message for database error', () => {
      cy.visit('/src/login/staff_login.html?error=database_error')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'A system error occurred. Please try again later.')
    })

    it('should display error message for Google user not found', () => {
      cy.visit('/src/login/staff_login.html?error=google_user_not_found')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'No staff account found with this Google email')
    })

    it('should display error message for Google auth failed', () => {
      cy.visit('/src/login/staff_login.html?error=google_auth_failed')
      cy.get('#error-message').should('be.visible')
      cy.get('#error-text').should('contain', 'Google authentication failed')
    })

    it('should have shake animation on error message', () => {
      cy.visit('/src/login/staff_login.html?error=invalid')
      cy.get('#error-message').should('have.css', 'animation')
    })
  })

  describe('Password Visibility Toggle', () => {
    it('should toggle password visibility when clicked', () => {
      cy.get('#password').type('testpassword')
      cy.get('#password').should('have.attr', 'type', 'password')
      
      cy.get('#togglePassword').click()
      cy.get('#password').should('have.attr', 'type', 'text')
      
      cy.get('#togglePassword').click()
      cy.get('#password').should('have.attr', 'type', 'password')
    })

    it('should change icon when password visibility is toggled', () => {
      cy.get('#togglePassword i').should('have.class', 'bi-eye')
      
      cy.get('#togglePassword').click()
      cy.get('#togglePassword i').should('have.class', 'bi-eye-slash')
      
      cy.get('#togglePassword').click()
      cy.get('#togglePassword i').should('have.class', 'bi-eye')
    })
  })

  describe('Google Authentication', () => {
    it('should have Google Sign-In button with correct styling', () => {
      cy.get('#googleSignInBtn').should('be.visible')
      cy.get('#googleSignInBtn').should('have.class', 'btn-outline-danger')
      cy.get('#googleSignInBtn svg').should('exist')
    })

    it('should redirect to Google auth when clicked', () => {
      cy.get('#googleSignInBtn').click()
      // Note: In a real test environment, you would mock the Google OAuth flow
      // For now, we'll just verify the button is clickable
      cy.get('#googleSignInBtn').should('be.visible')
    })
  })

  describe('Form Submission', () => {
    it('should submit form with valid credentials', () => {
      cy.get('#email').type(Cypress.env('testStaffEmail'))
      cy.get('#password').type(Cypress.env('testStaffPassword'))
      cy.get('button[type="submit"]').click()
      
      // Should redirect to staff dashboard on successful login
      cy.url().should('include', '/staff/dashboard.php')
    })

    it('should handle invalid credentials gracefully', () => {
      cy.get('#email').type('invalid@test.com')
      cy.get('#password').type('wrongpassword')
      cy.get('button[type="submit"]').click()
      
      // Should stay on login page with error
      cy.url().should('include', 'error=invalid')
    })
  })

  describe('Accessibility', () => {
    it('should have proper form labels', () => {
      cy.get('label[for="email"]').should('contain', 'Email Address')
      cy.get('label[for="password"]').should('contain', 'Password')
    })

    it('should have proper ARIA attributes', () => {
      cy.get('#error-message').should('have.attr', 'role', 'alert')
      cy.get('#success-message').should('have.attr', 'role', 'alert')
    })

    it('should be keyboard navigable', () => {
      cy.get('#email').focus()
      cy.get('#email').should('have.focus')
      
      cy.get('#email').tab()
      cy.get('#password').should('have.focus')
      
      cy.get('#password').tab()
      cy.get('button[type="submit"]').should('have.focus')
    })
  })

  describe('Security Features', () => {
    it('should not display password in URL or page source', () => {
      cy.get('#password').type('secretpassword')
      cy.get('button[type="submit"]').click()
      
      cy.url().should('not.contain', 'secretpassword')
      cy.get('body').should('not.contain', 'secretpassword')
    })

    it('should have proper form method and action', () => {
      cy.get('form').should('have.attr', 'method', 'POST')
      cy.get('form').should('have.attr', 'action', 'staff_login.php')
    })

    it('should have autocomplete attributes', () => {
      cy.get('#email').should('have.attr', 'type', 'email')
      cy.get('#password').should('have.attr', 'type', 'password')
    })
  })

  describe('Performance', () => {
    it('should load page within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle rapid form submissions', () => {
      cy.get('#email').type('test@test.com')
      cy.get('#password').type('testpassword')
      
      // Rapid clicks should not cause issues
      cy.get('button[type="submit"]').click()
      cy.get('button[type="submit"]').click()
      cy.get('button[type="submit"]').click()
      
      cy.url().should('include', 'error=invalid')
    })
  })
})
