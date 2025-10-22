describe('Authentication Tests', () => {
  beforeEach(() => {
    cy.visit('/src/login/admin_login.html')
  })

  it('should handle admin login with validation and security', () => {
    cy.get('h2').should('contain', 'Admin Login')
    cy.get('form').should('be.visible')
    cy.get('input[name="username"]').should('be.visible')
    cy.get('input[name="password"]').should('be.visible')
    cy.get('button[type="submit"]').should('be.visible')
    cy.get('a[href*="staff_login.html"]').should('contain', 'Staff Login')
    
    // Test validation
    cy.get('button[type="submit"]').click()
    cy.get('.invalid-feedback').should('be.visible')
    
    cy.get('input[name="username"]').focus().blur()
    cy.get('button[type="submit"]').click()
    cy.get('input[name="username"]').should('have.class', 'is-invalid')
    
    cy.get('input[name="password"]').focus().blur()
    cy.get('button[type="submit"]').click()
    cy.get('input[name="password"]').should('have.class', 'is-invalid')
    
    // Test invalid credentials
    cy.get('input[name="username"]').type('invalid')
    cy.get('input[name="password"]').type('invalid')
    cy.get('button[type="submit"]').click()
    cy.get('.alert-danger').should('be.visible')
    cy.get('.alert-danger').should('contain', 'Invalid username or password')
    
    // Test valid login
    cy.get('input[name="username"]').clear().type('admin')
    cy.get('input[name="password"]').clear().type('admin123')
    cy.get('button[type="submit"]').click()
    cy.url().should('include', 'admin_dashboard.php')
    cy.get('.navbar-brand').should('contain', 'Admin Dashboard')
  })

  it('should handle staff login and Google authentication', () => {
    cy.visit('/src/login/staff_login.html')
    cy.get('h2').should('contain', 'Staff Login')
    cy.get('form').should('be.visible')
    cy.get('input[name="username"]').should('be.visible')
    cy.get('input[name="password"]').should('be.visible')
    cy.get('button[type="submit"]').should('be.visible')
    cy.get('a[href*="admin_login.html"]').should('contain', 'Admin Login')
    
    // Test staff login
    cy.get('input[name="username"]').type('staff')
    cy.get('input[name="password"]').type('staff123')
    cy.get('button[type="submit"]').click()
    cy.url().should('include', 'dashboard.php')
    cy.get('.navbar-brand').should('contain', 'Staff Dashboard')
    
    // Test Google authentication
    cy.visit('/src/login/google_auth.php')
    cy.get('h2').should('contain', 'Google Authentication')
    cy.get('form').should('be.visible')
    cy.get('input[name="email"]').should('be.visible')
    cy.get('input[name="password"]').should('be.visible')
    cy.get('button[type="submit"]').should('be.visible')
    
    cy.get('input[name="email"]').type('test@example.com')
    cy.get('input[name="password"]').type('password123')
    cy.get('button[type="submit"]').click()
    cy.url().should('include', 'dashboard.php')
  })
})