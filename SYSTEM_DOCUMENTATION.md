# Animal Bite Reporting and Management System - Comprehensive Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [System Architecture](#system-architecture)
3. [Database Schema](#database-schema)
4. [User Roles and Access Control](#user-roles-and-access-control)
5. [Core Functionalities](#core-functionalities)
6. [Vaccination Management System](#vaccination-management-system)
7. [Analytics and Decision Support](#analytics-and-decision-support)
8. [Notification System](#notification-system)
9. [Security Features](#security-features)
10. [System Workflows](#system-workflows)
11. [API Endpoints](#api-endpoints)
12. [Testing Framework](#testing-framework)

---

## System Overview

The Animal Bite Reporting and Management System is a comprehensive web-based application designed to track, manage, and analyze animal bite incidents in local communities. The system facilitates the complete lifecycle of animal bite case management from initial reporting through treatment completion and follow-up.

### Key Objectives
- **Efficient Case Management**: Streamline the reporting and tracking of animal bite incidents
- **Public Health Monitoring**: Provide real-time analytics on bite patterns and risk areas
- **Vaccination Tracking**: Ensure proper post-exposure prophylaxis (PEP) and pre-exposure prophylaxis (PrEP) administration
- **Decision Support**: Enable data-driven decision making for health authorities
- **Resource Optimization**: Help allocate medical resources effectively

### Technology Stack
- **Backend**: PHP 8.2+ with PDO for database operations
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript with Bootstrap 5
- **Charts/Analytics**: Chart.js for data visualization
- **Authentication**: PHP sessions with bcrypt password hashing
- **Testing**: Cypress for end-to-end testing

---

## System Architecture

### Application Structure
```
Animal-Bite/
├── index.php                 # Main entry point (redirects to admin login)
├── src/
│   ├── admin/               # Administrative interface
│   ├── staff/               # Staff interface
│   ├── login/               # Authentication system
│   ├── conn/                # Database connection
│   ├── css/                 # Styling
│   └── cron/                # Automated tasks
├── db_backups/              # Database backups
├── db_upgrades/             # Schema updates
└── Test_Case_Document.*     # Testing documentation
```

### Three-Tier Architecture
1. **Presentation Layer**: Web interface for admins and staff
2. **Application Layer**: PHP business logic and data processing
3. **Data Layer**: MySQL database with structured tables

---

## Database Schema

### Core Tables

#### `admin`
- **Purpose**: Administrative user accounts
- **Key Fields**: `adminId`, `name`, `email`, `password`
- **Relationships**: None direct (manages all other data)

#### `staff`
- **Purpose**: Field staff user accounts
- **Key Fields**: `staffId`, `firstName`, `lastName`, `email`, `password`, `birthDate`, `address`, `contactNumber`
- **Relationships**: Creates and manages reports

#### `patients`
- **Purpose**: Victim/patient information
- **Key Fields**:
  - Personal: `patientId`, `firstName`, `lastName`, `gender`, `dateOfBirth`, `contactNumber`, `email`
  - Location: `address`, `barangay`, `city`, `province`
  - Medical: `medicalHistory`, `emergencyContact`, `allergies`, `previousRabiesVaccine`
- **Relationships**: One-to-many with reports

#### `reports`
- **Purpose**: Animal bite incident records
- **Key Fields**:
  - Incident: `reportId`, `biteDate`, `reportDate`, `biteLocation`, `biteType` (Category I-III)
  - Animal: `animalType`, `animalOtherType`, `animalOwnership`, `animalStatus`, `animalVaccinated`
  - Treatment: `washWithSoap`, `rabiesVaccine`, `antiTetanus`, `antibiotics`, `referredToHospital`
  - Status: `status` (pending/in_progress/completed/referred), `notes`
- **Relationships**: Belongs to patient and staff

#### `classifications`
- **Purpose**: Bite severity classification system
- **Key Fields**: `classificationID`, `classificationName`, `severityLevel`, `animalType`, `biteCondition`, `recommendedAction`, `colorCode`
- **Relationships**: Referenced by reports for categorization

#### `barangay_coordinates`
- **Purpose**: Geographic mapping data
- **Key Fields**: `id`, `barangay`, `latitude`, `longitude`
- **Relationships**: Used for location-based analytics

#### `notifications`
- **Purpose**: System notifications and alerts
- **Key Fields**: `id`, `title`, `message`, `type`, `recipient_role`, `is_read`, `created_at`
- **Relationships**: Targeted to admin, staff, or all users

### Database Relationships
```
admin (1) ────→ manages all data
staff (1) ────→ creates (many) reports
patients (1) ────→ has (many) reports
reports (many) ────→ belongs to (1) patient
reports (many) ────→ belongs to (1) staff
reports ────→ references (1) classification
barangay_coordinates ────→ used for geographic data
```

---

## User Roles and Access Control

### Administrator Role
**Responsibilities**:
- System-wide oversight and management
- Staff account management
- Patient data management
- Report review and editing
- Analytics and reporting
- System configuration

**Access Permissions**:
- All administrative functions
- Staff management (add/edit/delete)
- Patient management (view/edit/delete)
- Report management (view/edit/delete/approve)
- Analytics dashboard access
- Notification management

### Staff Role
**Responsibilities**:
- Field data collection
- Patient registration
- Incident reporting
- Basic report management
- Vaccination tracking

**Access Permissions**:
- Report creation and editing (own reports)
- Patient registration
- Limited patient data access
- Vaccination scheduling
- Personal dashboard

### Authentication System
- **Session-based authentication** with PHP sessions
- **Password hashing** using bcrypt (PASSWORD_DEFAULT)
- **Role-based access control** enforced at page level
- **Auto-logout** on session timeout
- **Login attempt logging** for security monitoring

---

## Core Functionalities

### 1. Patient Management
**Features**:
- Patient registration with duplicate detection
- Comprehensive patient profiles
- Emergency contact information
- Medical history tracking
- Allergy and vaccination history

**Duplicate Detection Logic**:
```php
// Scoring system for patient matching
$match_score = CASE
  WHEN contactNumber = ? THEN 50      // Exact phone match
  WHEN firstName = ? AND lastName = ? THEN 40  // Exact name match
  WHEN firstName = ? AND lastName = ? AND address LIKE ? THEN 30
  WHEN firstName = ? AND lastName = ? AND barangay = ? THEN 25
  WHEN firstName = ? AND lastName = ? THEN 20   // Name similarity
  WHEN contactNumber LIKE ? THEN 15    // Phone similarity
  ELSE 5                               // Other matches
END
```

### 2. Report Management
**Report Creation Workflow**:
1. Patient selection or registration
2. Incident details entry
3. Animal information
4. Treatment details
5. Follow-up scheduling

**Report Status Flow**:
```
pending → in_progress → completed
    ↓
  referred
```

### 3. Incident Classification
**WHO Categories**:
- **Category I**: Touching/feeding animals, licks on intact skin
- **Category II**: Nibbling, minor scratches, abrasions
- **Category III**: Single/multiple bites, mucous membrane exposure

**Severity Levels**: Low, Medium, High with color coding

---

## Vaccination Management System

### PEP (Post-Exposure Prophylaxis)
**Schedule**: Days 0, 3, 7 (3 doses maximum)
**Purpose**: Emergency vaccination following animal exposure
**Validation**: Strict sequential dosing with date validation

### PrEP (Pre-Exposure Prophylaxis)
**Schedule**: Annual or custom intervals
**Purpose**: Preventive vaccination for high-risk individuals
**Validation**: Flexible scheduling based on healthcare provider discretion

### Dose Status Tracking
**Status Types**:
- `Completed`: Dose administered successfully
- `Upcoming`: Scheduled but not yet due
- `Missed`: Past due date without administration
- `Overdue`: Significantly past due date
- `NotScheduled`: No dose planned

### Vaccination Logic
```php
function getNextDoseNumber($pdo, $reportId, $exposureType = 'PEP', $maxDoses = 3) {
    // Check existing completed doses
    $completedDoses = countCompletedDoses($pdo, $reportId, $exposureType);

    if ($completedDoses >= $maxDoses) {
        return null; // All doses completed
    }

    return $completedDoses + 1; // Next sequential dose
}
```

---

## Analytics and Decision Support

### Dashboard Analytics
**Real-time Metrics**:
- Total reports (all time, today, pending)
- Category III cases requiring urgent attention
- Recent activity feed
- Geographic distribution

### Decision Support Features
**Filtering Capabilities**:
- Date range filtering
- Animal type filtering
- Geographic (barangay) filtering
- Bite category filtering

**Analytics Views**:
1. **Total Cases**: Overall incident trends
2. **Animal Type Distribution**: Most common biting animals
3. **Bite Category Analysis**: Severity distribution
4. **Geographic Mapping**: High-risk areas
5. **Monthly Trends**: Seasonal patterns
6. **Age Group Analysis**: Vulnerable demographics
7. **Gender Distribution**: Demographic patterns

### Geographic Mapping
**Features**:
- Interactive maps with barangay coordinates
- Color-coded risk levels
- Incident clustering
- Location-based filtering

---

## Notification System

### Notification Types
- **Info**: General information and updates
- **Warning**: Important alerts requiring attention
- **Danger**: Critical cases needing immediate action
- **Success**: Positive outcomes and completions

### Targeting System
- **Admin**: Administrative notifications
- **Staff**: Field staff alerts
- **All**: System-wide broadcasts

### Automated Triggers
- New report submissions
- Critical case alerts (Category III)
- Missed vaccination appointments
- System maintenance notifications

---

## Security Features

### Authentication Security
- **Password Hashing**: bcrypt with cost factor
- **Session Management**: Secure PHP sessions
- **CSRF Protection**: Token validation on forms
- **Input Sanitization**: Prepared statements for SQL injection prevention

### Access Control
- **Role-based permissions** enforced at application level
- **Session validation** on all protected pages
- **Automatic logout** on inactivity
- **Audit logging** for security events

### Data Protection
- **PDO Prepared Statements**: SQL injection prevention
- **Input validation**: Server-side validation
- **XSS prevention**: Output escaping
- **File upload restrictions**: Secure file handling

---

## System Workflows

### New Report Creation (Staff)
1. **Access System**: Staff logs into dedicated interface
2. **Patient Selection**: Search existing or create new patient
3. **Duplicate Check**: System scans for similar patients
4. **Report Entry**: Input incident details and animal information
5. **Classification**: Automatic or manual bite category assignment
6. **Treatment Recording**: Document immediate care provided
7. **Vaccination Scheduling**: Set up PEP schedule if required
8. **Follow-up Planning**: Schedule necessary follow-up visits

### Report Review (Admin)
1. **Dashboard Access**: Admin views pending reports
2. **Report Evaluation**: Review incident details and classification
3. **Patient Verification**: Confirm patient information accuracy
4. **Treatment Validation**: Verify appropriate care was provided
5. **Status Updates**: Approve, modify, or escalate reports
6. **Analytics Integration**: Update system statistics

### Vaccination Tracking
1. **Schedule Generation**: Automatic dose scheduling based on bite category
2. **Dose Administration**: Record vaccine administration with dates
3. **Compliance Monitoring**: Track adherence to vaccination schedules
4. **Status Updates**: Mark doses as completed, missed, or overdue
5. **Reporting**: Generate compliance reports for health authorities

---

## API Endpoints

### Vaccination APIs
- `api/get_suggested_doses.php`: Calculate recommended vaccination schedules
- `api/get_vaccination_status.php`: Check patient vaccination status
- `api/get_vaccination_workload.php`: Monitor vaccination workload

### Report APIs
- `api/get_pep_status.php`: PEP vaccination progress
- `api/get_workload.php`: Staff workload metrics
- `api/get_report_details.php`: Detailed report information

### Geographic APIs
- `manage_coordinates.php`: Barangay coordinate management
- `geomapping.php`: Geographic mapping functionality

---

## Testing Framework

### Cypress E2E Testing
**Test Coverage**:
- User authentication flows
- Report creation workflows
- Patient management
- Admin dashboard functionality
- Vaccination scheduling
- Data validation

### Test Structure
```
cypress/
├── e2e/
│   ├── authentication/
│   ├── reporting/
│   ├── patient_management/
│   └── vaccination/
├── fixtures/
│   └── test-data.json
└── support/
    └── commands.js
```

### Automated Testing Features
- **Cross-browser testing**
- **CI/CD integration ready**
- **Screenshot capture on failures**
- **Test data fixtures**
- **Custom test commands**

---

## System Maintenance

### Automated Tasks
**Cron Jobs**:
- `check_thresholds.php`: Monitor system thresholds
- Automated notification cleanup
- Database maintenance tasks

### Backup System
- Regular database exports
- Configuration backups
- Log file archiving

### Performance Optimization
- Database query optimization
- Caching strategies
- File compression
- Image optimization

---

## Future Enhancements

### Planned Features
1. **Mobile Application**: Native mobile app for field staff
2. **SMS Notifications**: Automated SMS alerts for patients
3. **Advanced Analytics**: Machine learning for risk prediction
4. **Multi-language Support**: Localization for different regions
5. **Integration APIs**: Connection with national health databases
6. **Offline Capability**: Work offline with data synchronization

### Scalability Considerations
- Database sharding for large datasets
- CDN integration for static assets
- Microservices architecture preparation
- API rate limiting implementation

---

## Conclusion

The Animal Bite Reporting and Management System represents a comprehensive solution for animal bite incident tracking and management. With its robust architecture, comprehensive feature set, and focus on data-driven decision making, the system effectively supports public health efforts to prevent and manage rabies and other animal-borne diseases.

The system's modular design, security features, and extensive testing framework ensure reliability, maintainability, and scalability for future growth and enhancements.

