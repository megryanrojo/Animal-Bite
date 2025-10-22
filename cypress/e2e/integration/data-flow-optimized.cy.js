describe('Data Flow Integration Tests', () => {
  describe('User to Staff Data Flow', () => {
    it('should flow data from user report to staff dashboard', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.fillPatientForm({
        firstName: 'Alice',
        lastName: 'Johnson',
        contactNumber: '09111111111',
        email: 'alice.johnson@test.com'
      })
      cy.fillBiteForm({
        animalType: 'Dog',
        biteDate: '2024-01-17',
        urgency: 'Normal'
      })
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Staff logs in and sees the new report
      cy.loginAsStaff()
      cy.visit('/src/staff/dashboard.php')
      cy.get('.card').should('contain', 'My Reports')
      cy.get('.card .h3').should('be.visible')
      
      // Step 3: Staff views reports page
      cy.visit('/src/staff/reports.php')
      cy.get('.table tbody tr').should('contain', 'Alice Johnson')
      cy.get('.table tbody tr').should('contain', 'Dog')
      cy.get('.table tbody tr').should('contain', 'Normal')
      
      // Step 4: Staff views patients page
      cy.visit('/src/staff/patients.php')
      cy.get('.table tbody tr').should('contain', 'Alice Johnson')
      cy.get('.table tbody tr').should('contain', '09111111111')
    })
  })

  describe('Staff to Admin Data Flow', () => {
    it('should flow data from staff updates to admin dashboard', () => {
      // Step 1: Staff logs in and updates a report
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('select[name="status"]').select('Reviewed')
      cy.get('select[name="urgency"]').select('High')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      
      // Step 2: Admin logs in and sees the updated report
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('.table tbody tr').should('contain', 'Reviewed')
      cy.get('.table tbody tr').should('contain', 'High')
      
      // Step 3: Admin views dashboard statistics
      cy.visit('/src/admin/admin_dashboard.php')
      cy.get('.card').should('contain', 'Total Reports')
      cy.get('.card').should('contain', 'Urgent Cases')
      cy.get('.card .h3').should('be.visible')
    })
  })

  describe('Cross-Module Data Consistency', () => {
    it('should maintain data consistency across all modules', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.fillPatientForm({
        firstName: 'Bob',
        lastName: 'Wilson',
        contactNumber: '09222222222',
        email: 'bob.wilson@test.com'
      })
      cy.fillBiteForm({
        animalType: 'Cat',
        biteDate: '2024-01-18',
        urgency: 'High'
      })
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Verify data in staff reports
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('.table tbody tr').should('contain', 'Bob Wilson')
      cy.get('.table tbody tr').should('contain', 'Cat')
      cy.get('.table tbody tr').should('contain', 'High')
      
      // Step 3: Verify data in staff patients
      cy.visit('/src/staff/patients.php')
      cy.get('.table tbody tr').should('contain', 'Bob Wilson')
      cy.get('.table tbody tr').should('contain', '09222222222')
      
      // Step 4: Verify data in admin reports
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('.table tbody tr').should('contain', 'Bob Wilson')
      cy.get('.table tbody tr').should('contain', 'Cat')
      cy.get('.table tbody tr').should('contain', 'High')
      
      // Step 5: Verify data in admin patients
      cy.visit('/src/admin/view_patients.php')
      cy.get('.table tbody tr').should('contain', 'Bob Wilson')
      cy.get('.table tbody tr').should('contain', '09222222222')
      
      // Step 6: Verify data in geomapping
      cy.visit('/src/admin/geomapping.php')
      cy.get('#map').should('be.visible')
      cy.get('#map .leaflet-marker').should('have.length.at.least', 1)
    })
  })

  describe('Real-time Data Updates', () => {
    it('should update data in real-time across modules', () => {
      // Step 1: Staff logs in and updates a report
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('select[name="status"]').select('Completed')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      
      // Step 2: Admin logs in and sees the updated report
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('.table tbody tr').should('contain', 'Completed')
      
      // Step 3: Admin updates the report
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('select[name="urgency"]').select('Low')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      
      // Step 4: Staff logs back in and sees the updated report
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('.table tbody tr').should('contain', 'Completed')
      cy.get('.table tbody tr').should('contain', 'Low')
    })
  })

  describe('Data Validation Flow', () => {
    it('should validate data at each step of the flow', () => {
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
      
      // Step 3: Staff tries to edit with invalid data
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('input[name="firstName"]').clear()
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
      
      // Step 4: Staff corrects the data and saves
      cy.get('input[name="firstName"]').type('John')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Report updated successfully', 'success')
    })
  })

  describe('Data Export Flow', () => {
    it('should export data correctly from all modules', () => {
      // Step 1: Staff exports reports
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_reports.php"]').should('be.visible')
      
      // Step 2: Staff exports patients
      cy.visit('/src/staff/patients.php')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_patients.php"]').should('be.visible')
      
      // Step 3: Admin exports reports
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_reports.php"]').should('be.visible')
      
      // Step 4: Admin exports patients
      cy.visit('/src/admin/view_patients.php')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_patients.php"]').should('be.visible')
      
      // Step 5: Admin exports staff
      cy.visit('/src/admin/view_staff.php')
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_staff.php"]').should('be.visible')
    })
  })

  describe('Data Search Flow', () => {
    it('should search data consistently across all modules', () => {
      // Step 1: User submits a report
      cy.visit('/src/user/index.php')
      cy.fillPatientForm({
        firstName: 'Charlie',
        lastName: 'Brown',
        contactNumber: '09333333333',
        email: 'charlie.brown@test.com'
      })
      cy.fillBiteForm({
        animalType: 'Dog',
        biteDate: '2024-01-19',
        urgency: 'Normal'
      })
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Staff searches for the report
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.performSearch('Charlie')
      cy.get('tbody tr').should('contain', 'Charlie Brown')
      
      // Step 3: Staff searches for the patient
      cy.visit('/src/staff/patients.php')
      cy.performSearch('Charlie')
      cy.get('tbody tr').should('contain', 'Charlie Brown')
      
      // Step 4: Staff uses advanced search
      cy.visit('/src/staff/search.php')
      cy.get('input[type="search"]').type('Charlie')
      cy.get('button[type="submit"]').click()
      cy.get('.search-results').should('be.visible')
      cy.get('.search-results .result-item').should('contain', 'Charlie Brown')
      
      // Step 5: Admin searches for the report
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.performSearch('Charlie')
      cy.get('tbody tr').should('contain', 'Charlie Brown')
      
      // Step 6: Admin searches for the patient
      cy.visit('/src/admin/view_patients.php')
      cy.performSearch('Charlie')
      cy.get('tbody tr').should('contain', 'Charlie Brown')
    })
  })

  describe('Data Filtering Flow', () => {
    it('should filter data consistently across all modules', () => {
      // Step 1: User submits multiple reports with different data
      cy.visit('/src/user/index.php')
      cy.fillPatientForm({
        firstName: 'David',
        lastName: 'Lee',
        contactNumber: '09444444444',
        email: 'david.lee@test.com'
      })
      cy.fillBiteForm({
        animalType: 'Cat',
        biteDate: '2024-01-20',
        urgency: 'High'
      })
      cy.get('button[type="submit"]').click()
      cy.get('.alert-success').should('be.visible')
      
      // Step 2: Staff filters reports by animal type
      cy.loginAsStaff()
      cy.visit('/src/staff/reports.php')
      cy.testFiltering('select[name="animalType"]', 'Cat')
      cy.get('tbody tr').should('contain', 'Cat')
      
      // Step 3: Staff filters reports by urgency
      cy.testFiltering('select[name="urgency"]', 'High')
      cy.get('tbody tr').should('contain', 'High')
      
      // Step 4: Staff filters patients by gender
      cy.visit('/src/staff/patients.php')
      cy.testFiltering('select[name="gender"]', 'Male')
      cy.get('tbody tr').should('have.length.at.least', 1)
      
      // Step 5: Admin filters reports by status
      cy.loginAsAdmin()
      cy.visit('/src/admin/view_reports.php')
      cy.testFiltering('select[name="status"]', 'Pending')
      cy.get('tbody tr').should('have.length.at.least', 1)
      
      // Step 6: Admin filters patients by age range
      cy.visit('/src/admin/view_patients.php')
      cy.testFiltering('select[name="age_range"]', '18-30')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })
  })
})
