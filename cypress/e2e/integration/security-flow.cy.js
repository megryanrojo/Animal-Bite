describe('Security Flow Integration Tests', () => {
  describe('Authentication Security', () => {
    it('should enforce authentication for protected routes', () => {
      // Test admin routes without authentication
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/view_reports.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/view_patients.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/view_staff.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/geomapping.php')
      cy.url().should('include', 'login')
      
      // Test staff routes without authentication
      cy.visit('/src/staff/dashboard.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/staff/reports.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/staff/patients.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/staff/search.php')
      cy.url().should('include', 'login')
    })

    it('should enforce role-based access control', () => {
      // Staff should not access admin routes
      cy.loginAsStaff()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/view_reports.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/view_patients.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/view_staff.php')
      cy.url().should('include', 'login')
      
      cy.visit('/src/admin/geomapping.php')
      cy.url().should('include', 'login')
      
      // Admin should access all routes
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
      
      cy.visit('/src/admin/view_reports.php')
      cy.url().should('include', 'view_reports.php')
      
      cy.visit('/src/admin/view_patients.php')
      cy.url().should('include', 'view_patients.php')
      
      cy.visit('/src/admin/view_staff.php')
      cy.url().should('include', 'view_staff.php')
      
      cy.visit('/src/admin/geomapping.php')
      cy.url().should('include', 'geomapping.php')
    })
  })

  describe('Session Security', () => {
    it('should handle session timeout', () => {
      // Login as admin
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
      
      // Clear session (simulate timeout)
      cy.clearCookies()
      cy.clearLocalStorage()
      
      // Try to access protected route
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
    })

    it('should handle concurrent sessions', () => {
      // Login as admin in first session
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
      
      // Login as staff in second session (different browser context)
      cy.loginAsStaff()
      cy.visit('/src/staff/dashboard.php')
      cy.url().should('include', 'dashboard.php')
      
      // Verify both sessions are active
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
    })
  })

  describe('Input Validation Security', () => {
    it('should prevent SQL injection in forms', () => {
      // Test admin login with SQL injection
      cy.visit('/src/login/admin_login.html')
      cy.get('#email').type("admin@test.com'; DROP TABLE admin; --")
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid')
      
      // Test staff login with SQL injection
      cy.visit('/src/login/staff_login.html')
      cy.get('#email').type("staff@test.com'; DROP TABLE staff; --")
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid')
    })

    it('should prevent XSS attacks in forms', () => {
      // Test user report form with XSS
      cy.visit('/src/user/index.php')
      cy.get('input[name="firstName"]').type('<script>alert("XSS")</script>')
      cy.get('input[name="lastName"]').type('Doe')
      cy.get('input[name="contactNumber"]').type('09123456789')
      cy.get('input[name="address"]').type('123 Test Street')
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('input[name="biteDate"]').type('2024-01-15')
      cy.get('input[name="biteLocation"]').type('Left arm')
      cy.get('button[type="submit"]').click()
      
      // Verify XSS is prevented
      cy.get('body').should('not.contain', '<script>')
      cy.get('body').should('not.contain', 'alert("XSS")')
    })

    it('should validate file uploads', () => {
      // Test file upload with malicious file
      cy.visit('/src/user/index.php')
      cy.fillPatientForm()
      cy.fillBiteForm()
      
      // Try to upload non-image file
      cy.get('input[type="file"]').selectFile('cypress/fixtures/malicious.exe')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      
      // Try to upload oversized image
      cy.get('input[type="file"]').selectFile('cypress/fixtures/large-image.jpg')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })
  })

  describe('Data Security', () => {
    it('should protect sensitive data in URLs', () => {
      // Test that passwords are not in URLs
      cy.visit('/src/login/admin_login.html')
      cy.get('#email').type('admin@test.com')
      cy.get('#password').type('secretpassword')
      cy.get('button[type="submit"]').click()
      cy.url().should('not.contain', 'secretpassword')
      
      // Test that session data is not in URLs
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('not.contain', 'admin_id')
      cy.url().should('not.contain', 'admin_name')
    })

    it('should protect sensitive data in page source', () => {
      // Test that passwords are not in page source
      cy.visit('/src/login/admin_login.html')
      cy.get('#email').type('admin@test.com')
      cy.get('#password').type('secretpassword')
      cy.get('button[type="submit"]').click()
      cy.get('body').should('not.contain', 'secretpassword')
      
      // Test that database credentials are not in page source
      cy.visit('/src/admin/admin_dashboard.php')
      cy.get('body').should('not.contain', 'password')
      cy.get('body').should('not.contain', 'database')
    })
  })

  describe('CSRF Protection', () => {
    it('should prevent CSRF attacks', () => {
      // Login as admin
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      
      // Try to submit form without proper CSRF token
      cy.request({
        method: 'POST',
        url: '/src/admin/view_reports.php',
        body: {
          action: 'delete',
          reportId: '1'
        },
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.not.eq(200)
      })
    })
  })

  describe('Rate Limiting', () => {
    it('should prevent brute force attacks', () => {
      // Test multiple failed login attempts
      cy.visit('/src/login/admin_login.html')
      
      for (let i = 0; i < 5; i++) {
        cy.get('#email').type('admin@test.com')
        cy.get('#password').type('wrongpassword')
        cy.get('button[type="submit"]').click()
        cy.url().should('include', 'error=invalid')
        cy.visit('/src/login/admin_login.html')
      }
      
      // Should still allow login with correct credentials
      cy.get('#email').type(Cypress.env('testAdminEmail'))
      cy.get('#password').type(Cypress.env('testAdminPassword'))
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'admin_dashboard.php')
    })
  })

  describe('Error Handling Security', () => {
    it('should not expose sensitive information in errors', () => {
      // Test database error handling
      cy.visit('/src/login/admin_login.html')
      cy.get('#email').type('admin@test.com')
      cy.get('#password').type('password123')
      cy.get('button[type="submit"]').click()
      
      // Error should not contain database details
      cy.get('body').should('not.contain', 'PDO')
      cy.get('body').should('not.contain', 'mysql')
      cy.get('body').should('not.contain', 'database')
    })

    it('should handle file upload errors securely', () => {
      // Test file upload error handling
      cy.visit('/src/user/index.php')
      cy.fillPatientForm()
      cy.fillBiteForm()
      
      // Try to upload invalid file
      cy.get('input[type="file"]').selectFile('cypress/fixtures/invalid.txt')
      cy.get('button[type="submit"]').click()
      
      // Error should not contain file system details
      cy.get('body').should('not.contain', '/var/www')
      cy.get('body').should('not.contain', 'permission')
    })
  })

  describe('Logout Security', () => {
    it('should properly clear session on logout', () => {
      // Login as admin
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
      
      // Logout
      cy.logout()
      cy.url().should('include', 'login')
      
      // Try to access protected route
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
    })

    it('should handle multiple logout attempts', () => {
      // Login as admin
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
      
      // Logout multiple times
      cy.logout()
      cy.visit('/src/login/admin_login.html')
      cy.get('a[href*="logout"]').click()
      cy.url().should('include', 'login')
    })
  })
})
