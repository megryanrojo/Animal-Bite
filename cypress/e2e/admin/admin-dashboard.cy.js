describe('Admin Dashboard Tests', () => {
  beforeEach(() => {
    cy.loginAsAdmin()
    cy.visit('/src/admin/admin_dashboard.php')
  })

  describe('Dashboard Layout', () => {
    it('should display the admin dashboard correctly', () => {
      cy.get('h1').should('contain', 'Admin Dashboard')
      cy.get('.navbar-brand').should('contain', 'Animal Bite Admin Portal')
    })

    it('should display navigation menu', () => {
      cy.get('.navbar-nav').should('be.visible')
      cy.get('a[href*="admin_dashboard.php"]').should('contain', 'Dashboard')
      cy.get('a[href*="geomapping.php"]').should('contain', 'Geomapping')
      cy.get('a[href*="view_reports.php"]').should('contain', 'Reports')
      cy.get('a[href*="view_patients.php"]').should('contain', 'Patients')
      cy.get('a[href*="decisionSupport.php"]').should('contain', 'Decision Support')
    })

    it('should display user information in navbar', () => {
      cy.get('.navbar').should('contain', 'Admin')
      cy.get('a[href*="logout"]').should('be.visible')
    })

    it('should be responsive on mobile devices', () => {
      cy.viewport('iphone-6')
      cy.get('.navbar-toggler').should('be.visible')
      cy.get('.navbar-collapse').should('not.be.visible')
      
      cy.get('.navbar-toggler').click()
      cy.get('.navbar-collapse').should('be.visible')
    })
  })

  describe('Dashboard Statistics', () => {
    it('should display statistics cards', () => {
      cy.get('.card').should('have.length.at.least', 4)
      cy.get('.card').should('contain', 'Total Reports')
      cy.get('.card').should('contain', 'Total Patients')
      cy.get('.card').should('contain', 'Urgent Cases')
      cy.get('.card').should('contain', 'Staff Members')
    })

    it('should display numeric values in statistics', () => {
      cy.get('.card .h3').should('be.visible')
      cy.get('.card .h3').each(($el) => {
        cy.wrap($el).should('match', /^\d+$/)
      })
    })

    it('should have proper styling for statistics cards', () => {
      cy.get('.card').each(($card) => {
        cy.wrap($card).should('have.class', 'card')
        cy.wrap($card).find('.card-body').should('be.visible')
      })
    })
  })

  describe('Recent Activity', () => {
    it('should display recent reports section', () => {
      cy.get('h5').should('contain', 'Recent Reports')
      cy.get('.table').should('be.visible')
    })

    it('should display recent reports table with proper columns', () => {
      cy.get('.table thead th').should('contain', 'Report ID')
      cy.get('.table thead th').should('contain', 'Patient Name')
      cy.get('.table thead th').should('contain', 'Date')
      cy.get('.table thead th').should('contain', 'Status')
    })

    it('should display recent reports data', () => {
      cy.get('.table tbody tr').should('have.length.at.least', 1)
    })

    it('should have view all reports link', () => {
      cy.get('a[href*="view_reports.php"]').should('contain', 'View All Reports')
    })
  })

  describe('Quick Actions', () => {
    it('should display quick action buttons', () => {
      cy.get('.btn').should('contain', 'Add Staff')
      cy.get('.btn').should('contain', 'View Reports')
      cy.get('.btn').should('contain', 'View Patients')
    })

    it('should navigate to add staff page', () => {
      cy.get('a[href*="add_staff.html"]').click()
      cy.url().should('include', 'add_staff.html')
    })

    it('should navigate to view reports page', () => {
      cy.get('a[href*="view_reports.php"]').click()
      cy.url().should('include', 'view_reports.php')
    })

    it('should navigate to view patients page', () => {
      cy.get('a[href*="view_patients.php"]').click()
      cy.url().should('include', 'view_patients.php')
    })
  })

  describe('Charts and Visualizations', () => {
    it('should display charts if present', () => {
      cy.get('canvas').then(($canvas) => {
        if ($canvas.length > 0) {
          cy.wrap($canvas).should('be.visible')
        }
      })
    })

    it('should display chart legends if present', () => {
      cy.get('.chart-legend').then(($legend) => {
        if ($legend.length > 0) {
          cy.wrap($legend).should('be.visible')
        }
      })
    })
  })

  describe('Notifications', () => {
    it('should display notifications if present', () => {
      cy.get('.alert').then(($alert) => {
        if ($alert.length > 0) {
          cy.wrap($alert).should('be.visible')
        }
      })
    })

    it('should have dismissible notifications', () => {
      cy.get('.alert .btn-close').then(($closeBtn) => {
        if ($closeBtn.length > 0) {
          cy.wrap($closeBtn).click()
          cy.wrap($closeBtn).parent().should('not.exist')
        }
      })
    })
  })

  describe('Navigation', () => {
    it('should navigate to geomapping page', () => {
      cy.navigateToPage('geomapping.php')
      cy.url().should('include', 'geomapping.php')
    })

    it('should navigate to decision support page', () => {
      cy.navigateToPage('decisionSupport.php')
      cy.url().should('include', 'decisionSupport.php')
    })

    it('should navigate to staff management page', () => {
      cy.navigateToPage('view_staff.php')
      cy.url().should('include', 'view_staff.php')
    })
  })

  describe('Logout Functionality', () => {
    it('should logout successfully', () => {
      cy.logout()
      cy.url().should('include', 'login')
    })

    it('should clear session on logout', () => {
      cy.logout()
      cy.visit('/src/admin/admin_dashboard.php')
      cy.url().should('include', 'login')
    })
  })

  describe('Accessibility', () => {
    it('should have proper heading structure', () => {
      cy.get('h1').should('exist')
      cy.get('h2, h3, h4, h5, h6').should('exist')
    })

    it('should have proper alt text for images', () => {
      cy.get('img').each(($img) => {
        cy.wrap($img).should('have.attr', 'alt')
      })
    })

    it('should be keyboard navigable', () => {
      cy.get('body').tab()
      cy.focused().should('be.visible')
    })
  })

  describe('Performance', () => {
    it('should load dashboard within acceptable time', () => {
      cy.measurePageLoad()
    })

    it('should handle page refresh', () => {
      cy.reload()
      cy.get('h1').should('contain', 'Admin Dashboard')
    })
  })

  describe('Data Refresh', () => {
    it('should refresh statistics on page reload', () => {
      cy.get('.card .h3').first().then(($initialValue) => {
        const initialValue = $initialValue.text()
        cy.reload()
        cy.get('.card .h3').first().should('be.visible')
      })
    })

    it('should update recent activity on page reload', () => {
      cy.get('.table tbody tr').then(($rows) => {
        const initialRowCount = $rows.length
        cy.reload()
        cy.get('.table tbody tr').should('have.length.at.least', 1)
      })
    })
  })
})
