describe('Complete Workflow Integration Tests', () => {
  describe('End-to-End User Journey', () => {
    it('should complete the full animal bite reporting workflow', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.get('h1').should('contain', 'Animal Bite Incident Report')
      
      cy.fillPatientForm({
        firstName: 'John',
        lastName: 'Doe',
        contactNumber: '09123456789',
        email: 'john.doe@test.com',
        address: '123 Test Street'
      })
      
      cy.fillBiteForm({
        animalType: 'Dog',
        biteDate: '2024-01-15',
        biteLocation: 'Left arm',
        urgency: 'Normal'
      })
      
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'submit_report.php')
      cy.get('.alert-success').should('be.visible')
      cy.get('.reference-number').should('be.visible')
      
      // Step 2: Staff logs in and views the new report
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'John Doe')
      cy.get('.modal .btn-close').click()
      
      // Step 3: Staff edits the report
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('select[name="status"]').select('Reviewed')
      cy.get('select[name="urgency"]').select('High')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Report updated successfully', 'success')
      
      // Step 4: Admin logs in and views the report
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'John Doe')
      cy.get('.modal-body').should('contain', 'Reviewed')
      cy.get('.modal-body').should('contain', 'High')
      cy.get('.modal .btn-close').click()
      
      // Step 5: Admin views geomapping
      cy.visit('/src/admin/geomapping.php')
      cy.get('#map').should('be.visible')
      cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
    })
  })

  describe('Cross-User Workflow', () => {
    it('should handle multiple users working on the same report', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Staff updates the report
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('select[name="status"]').select('In Progress')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      
      // Step 3: Admin sees the updated report
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'In Progress')
      cy.get('.modal .btn-close').click()
      
      // Step 4: Admin updates the report
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('select[name="status"]').select('Completed')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      
      // Step 5: Staff sees the final update
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Completed')
      cy.get('.modal .btn-close').click()
    })
  })

  describe('Data Consistency Workflow', () => {
    it('should maintain data consistency across all modules', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.fillPatientForm({
        firstName: 'Jane',
        lastName: 'Smith',
        contactNumber: '09987654321',
        email: 'jane.smith@test.com'
      })
      cy.fillBiteForm({
        animalType: 'Cat',
        biteDate: '2024-01-16',
        urgency: 'High'
      })
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Verify data appears in staff reports
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('.table tbody tr').should('contain', 'Jane Smith')
      cy.get('.table tbody tr').should('contain', 'Cat')
      cy.get('.table tbody tr').should('contain', 'High')
      
      // Step 3: Verify data appears in staff patients
      cy.visit('/src/staff/patients.php')
      cy.get('.table tbody tr').should('contain', 'Jane Smith')
      cy.get('.table tbody tr').should('contain', '09987654321')
      
      // Step 4: Verify data appears in admin reports
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('.table tbody tr').should('contain', 'Jane Smith')
      cy.get('.table tbody tr').should('contain', 'Cat')
      cy.get('.table tbody tr').should('contain', 'High')
      
      // Step 5: Verify data appears in admin patients
      cy.visit('/src/admin/view_patients.php')
      cy.get('.table tbody tr').should('contain', 'Jane Smith')
      cy.get('.table tbody tr').should('contain', '09987654321')
      
      // Step 6: Verify data appears in geomapping
      cy.visit('/src/admin/geomapping.php')
      cy.get('#map').should('be.visible')
      cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
    })
  })

  describe('Error Handling Workflow', () => {
    it('should handle errors gracefully throughout the workflow', () => {
      // Step 1: User submits invalid data
      cy.visit('/src/user/index.php')
      cy.get('input[name="firstName"]').type('John')
      cy.get('input[name="contactNumber"]').type('123') // Invalid format
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      
      // Step 2: User corrects the data and submits
      cy.get('input[name="contactNumber"]').clear().type('09123456789')
      cy.get('input[name="lastName"]').type('Doe')
      cy.get('input[name="address"]').type('123 Test Street')
      cy.get('select[name="animalType"]').select('Dog')
      cy.get('input[name="biteDate"]').type('2024-01-15')
      cy.get('input[name="biteLocation"]').type('Left arm')
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 3: Staff tries to access without login
      cy.visit('/src/staff/reports.php')
      cy.url().should('include', 'login')
      
      // Step 4: Staff logs in with invalid credentials
      cy.visit('/src/login/staff_login.html')
      cy.get('#email').type('invalid@test.com')
      cy.get('#password').type('wrongpassword')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'error=invalid')
      
      // Step 5: Staff logs in with valid credentials
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('.table tbody tr').should('have.length.at.least', 1)
      
      // Step 6: Staff tries to edit with invalid data
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('input[name="firstName"]').clear()
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      
      // Step 7: Staff corrects the data and saves
      cy.get('input[name="firstName"]').type('John')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Report updated successfully', 'success')
    })
  })

  describe('Security Workflow', () => {
    it('should maintain security throughout the workflow', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Verify user cannot access admin areas
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
      
      // Step 3: Verify user cannot access staff areas
      cy.visit('/src/staff/dashboard.php')
      cy.url().should('include', 'login')
      
      // Step 4: Staff logs in and verifies access
      cy.loginAsStaff()
      cy.visit('/src/staff/dashboard.php')
      cy.url().should('include', 'dashboard.php')
      
      // Step 5: Verify staff cannot access admin areas
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
      
      // Step 6: Admin logs in and verifies access
      cy.loginAsAdmin()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'admin_dashboard.php')
      
      // Step 7: Verify admin can access all areas
      cy.visit('/src/admin/view_reports.php')
      cy.url().should('include', 'view_reports.php')
      
      cy.visit('/src/admin/view_patients.php')
      cy.url().should('include', 'view_patients.php')
      
      cy.visit('/src/admin/view_staff.php')
      cy.url().should('include', 'view_staff.php')
    })
  })

  describe('Performance Workflow', () => {
    it('should maintain performance throughout the workflow', () => {
      // Step 1: Measure homepage load time
      cy.visit('/src/user/index.php')
      cy.measurePageLoad()
      
      // Step 2: Measure form submission time
      cy.fillPatientForm()
      cy.fillBiteForm()
      cy.get('button[type="submit"]').click()
      cy.url().should('include', 'submit_report.php')
      
      // Step 3: Measure staff login time
      cy.loginAsStaff()
      cy.measurePageLoad()
      
      // Step 4: Measure reports page load time
      cy.visit('/src/staff/reports.php')
      cy.measurePageLoad()
      
      // Step 5: Measure admin login time
      cy.loginAsAdmin()
      cy.measurePageLoad()
      
      // Step 6: Measure admin dashboard load time
      cy.visit('/src/admin/admin_dashboard.php')
      cy.measurePageLoad()
      
      // Step 7: Measure geomapping load time
      cy.visit('/src/admin/geomapping.php')
      cy.measurePageLoad()
    })
  })
})
