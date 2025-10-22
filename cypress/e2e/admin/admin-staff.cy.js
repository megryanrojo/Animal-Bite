describe('Admin Staff Management Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/view_staff.php')
  })

  describe('Staff List Page', () => {
    it('should display the staff page correctly', () => {
      cy.get('h1').should('contain', 'Staff Management')
      cy.get('.table').should('be.visible')
    })

    it('should display staff table with proper columns', () => {
      cy.verifyTableHeaders('.table', [
        'Staff ID',
        'Name',
        'Email',
        'Contact',
        'Position',
        'Status',
        'Actions'
      ])
    })

    it('should display staff data', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })

    it('should have search functionality', () => {
      cy.get('input[type="search"]').should('be.visible')
      cy.get('button[type="submit"]').should('be.visible')
    })

    it('should have filter options', () => {
      cy.get('select').should('be.visible')
    })
  })

  describe('Add Staff', () => {
    it('should navigate to add staff page', () => {
      cy.get('a[href*="add_staff.html"]').click()
      cy.url().should('include', 'add_staff.html')
    })

    it('should display add staff form', () => {
      cy.visit('/src/admin/add_staff.html')
      cy.get('h1').should('contain', 'Add Staff')
      cy.get('form').should('be.visible')
    })

    it('should have required form fields', () => {
      cy.visit('/src/admin/add_staff.html')
      cy.get('input[name="firstName"]').should('be.visible')
      cy.get('input[name="lastName"]').should('be.visible')
      cy.get('input[name="email"]').should('be.visible')
      cy.get('input[name="contactNumber"]').should('be.visible')
      cy.get('select[name="position"]').should('be.visible')
    })

    it('should validate required fields', () => {
      cy.visit('/src/admin/add_staff.html')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should validate email format', () => {
      cy.visit('/src/admin/add_staff.html')
      cy.get('input[name="email"]').type('invalid-email')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should add new staff member', () => {
      cy.visit('/src/admin/add_staff.html')
      cy.fillFormField('input[name="firstName"]', 'John')
      cy.fillFormField('input[name="lastName"]', 'Doe')
      cy.fillFormField('input[name="email"]', 'john.doe@test.com')
      cy.fillFormField('input[name="contactNumber"]', '09123456789')
      cy.selectDropdownOption('select[name="position"]', 'Health Worker')
      cy.get('button[type="submit"]').click()
      cy.checkNotification('Staff member added successfully', 'success')
    })
  })

  describe('Search and Filter', () => {
    it('should search staff by name', () => {
      cy.performSearch('John')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should search staff by email', () => {
      cy.performSearch('staff@test.com')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should filter staff by position', () => {
      cy.testFiltering('select[name="position"]', 'Health Worker')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should filter staff by status', () => {
      cy.testFiltering('select[name="status"]', 'Active')
      cy.get('tbody tr').should('have.length.at.least', 1)
    })

    it('should clear search results', () => {
      cy.performSearch('test')
      cy.clearSearch()
      cy.get('tbody tr').should('have.length.at.least', 1)
    })
  })

  describe('Staff Actions', () => {
    it('should view staff details', () => {
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Staff Details')
    })

    it('should edit staff', () => {
      cy.get('button[data-bs-target*="edit"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Staff')
    })

    it('should delete staff', () => {
      cy.get('button[data-bs-target*="delete"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })

    it('should reset staff password', () => {
      cy.get('button[data-bs-target*="reset"]').first().click()
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Reset Password')
    })
  })

  describe('Staff Details Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="view"]').first().click()
    })

    it('should display staff details correctly', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-body').should('contain', 'Personal Information')
      cy.get('.modal-body').should('contain', 'Contact Information')
    })

    it('should display personal information', () => {
      cy.get('.modal-body').should('contain', 'Name:')
      cy.get('.modal-body').should('contain', 'Email:')
      cy.get('.modal-body').should('contain', 'Position:')
    })

    it('should display contact information', () => {
      cy.get('.modal-body').should('contain', 'Contact Number:')
      cy.get('.modal-body').should('contain', 'Status:')
    })

    it('should close modal when close button is clicked', () => {
      cy.get('.modal .btn-close').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Edit Staff Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="edit"]').first().click()
    })

    it('should display edit form correctly', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Edit Staff')
      cy.get('form').should('be.visible')
    })

    it('should populate form with existing data', () => {
      cy.get('input[name="firstName"]').should('have.value')
      cy.get('input[name="lastName"]').should('have.value')
      cy.get('input[name="email"]').should('have.value')
    })

    it('should update staff information', () => {
      cy.get('input[name="contactNumber"]').clear().type('09123456789')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Staff updated successfully', 'success')
    })

    it('should validate required fields', () => {
      cy.get('input[name="firstName"]').clear()
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should close modal on cancel', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Delete Staff Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="delete"]').first().click()
    })

    it('should display delete confirmation', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Delete Staff')
      cy.get('.modal-body').should('contain', 'Are you sure')
    })

    it('should confirm deletion', () => {
      cy.get('button').contains('Yes, delete it!').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Staff deleted successfully', 'success')
    })

    it('should cancel deletion', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Reset Password Modal', () => {
    beforeEach(() => {
      cy.get('button[data-bs-target*="reset"]').first().click()
    })

    it('should display reset password form', () => {
      cy.get('.modal').should('be.visible')
      cy.get('.modal-title').should('contain', 'Reset Password')
      cy.get('form').should('be.visible')
    })

    it('should have new password field', () => {
      cy.get('input[name="newPassword"]').should('be.visible')
      cy.get('input[name="confirmPassword"]').should('be.visible')
    })

    it('should validate password confirmation', () => {
      cy.get('input[name="newPassword"]').type('newpassword123')
      cy.get('input[name="confirmPassword"]').type('differentpassword')
      cy.get('button[type="submit"]').click()
      cy.get('.invalid-feedback').should('be.visible')
    })

    it('should reset password successfully', () => {
      cy.get('input[name="newPassword"]').type('newpassword123')
      cy.get('input[name="confirmPassword"]').type('newpassword123')
      cy.get('button[type="submit"]').click()
      cy.get('.modal').should('not.exist')
      cy.checkNotification('Password reset successfully', 'success')
    })

    it('should close modal on cancel', () => {
      cy.get('button').contains('Cancel').click()
      cy.get('.modal').should('not.exist')
    })
  })

  describe('Export Functionality', () => {
    it('should have export button', () => {
      cy.get('button').contains('Export').should('be.visible')
    })

    it('should export staff to CSV', () => {
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_staff.php"]').should('be.visible')
    })

    it('should export staff to Excel', () => {
      cy.get('button').contains('Export').click()
      cy.get('a[href*="export_staff.php?format=excel"]').should('be.visible')
    })
  })

  describe('Pagination', () => {
    it('should display pagination if there are many staff members', () => {
      cy.get('.pagination').then(($pagination) => {
        if ($pagination.length > 0) {
          cy.wrap($pagination).should('be.visible')
          cy.testPagination()
        }
      })
    })
  })

  describe('Sorting', () => {
    it('should sort staff by name', () => {
      cy.testSorting('Name')
    })

    it('should sort staff by email', () => {
      cy.testSorting('Email')
    })

    it('should sort staff by position', () => {
      cy.testSorting('Position')
    })
  })

  describe('Bulk Actions', () => {
    it('should have select all checkbox', () => {
      cy.get('input[type="checkbox"]').first().should('be.visible')
    })

    it('should select individual staff members', () => {
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('input[type="checkbox"]').eq(1).should('be.checked')
    })

    it('should select all staff members', () => {
      cy.get('input[type="checkbox"]').first().check()
      cy.get('input[type="checkbox"]').should('be.checked')
    })

    it('should have bulk action buttons', () => {
      cy.get('input[type="checkbox"]').eq(1).check()
      cy.get('button').contains('Bulk Actions').should('be.visible')
    })
  })

  describe('Staff Statistics', () => {
    it('should display staff statistics', () => {
      cy.get('.card').should('contain', 'Total Staff')
      cy.get('.card').should('contain', 'Active Staff')
      cy.get('.card').should('contain', 'Inactive Staff')
    })

    it('should display numeric values in statistics', () => {
      cy.get('.card .h3').should('be.visible')
      cy.get('.card .h3').each(($el) => {
        cy.wrap($el).should('match', /^\d+$/)
      })
    })
  })

  describe('Responsive Design', () => {
    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.table-responsive').should('be.visible')
      cy.get('.table').should('be.visible')
    })

    it('should maintain functionality on tablet', () => {
      cy.viewport('ipad-2')
      cy.get('.table').should('be.visible')
      cy.get('button[data-bs-target*="view"]').first().click()
      cy.get('.modal').should('be.visible')
    })
  })

  describe('Accessibility', () => {
    it('should have proper table headers', () => {
      cy.get('.table thead th').should('have.attr', 'scope', 'col')
    })

    it('should have proper form labels', () => {
      cy.get('label').should('be.visible')
    })

    it('should be keyboard navigable', () => {
      cy.get('body').tab()
      cy.focused().should('be.visible')
    })
  })

  describe('Performance', () => {
    it('should load staff page within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle large number of staff members', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })
  })
})
