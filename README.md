# Pulong Buhangin e-Barangay

A comprehensive digital platform for barangay services, designed to streamline administrative processes and enhance transparency in local government operations. This system enables residents to request and manage various certificates and clearances online, while providing barangay officials with efficient tools for document processing and management.

## Architecture Overview

This is a PHP-based web application built with modern web technologies, featuring:

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla JS with jQuery)
- **Backend**: PHP 7.4+ with MySQL database
- **Security**: CSRF protection, input validation, secure authentication
- **Database**: MySQL with prepared statements and transaction support
- **File Management**: Secure file upload and storage system

## Project Description

The e-Barangayxz system is a digital platform dedicated to promoting efficient and transparent barangay services. It serves as a central hub for residents to request documents, submit payments, and track their applications, while providing barangay staff with tools to manage requests, generate reports, and maintain records.

## Key Features

- **Role-based Access System**: Three distinct user roles with specialized interfaces
  - **Resident Dashboard**: Request services, track applications, upload documents
  - **Staff Dashboard**: Process requests, manage documents, generate reports
  - **Super Admin Dashboard**: System oversight, staff management, analytics

- **Document Management**: Comprehensive handling of various barangay certificates and clearances
  - Barangay Clearance
  - Certificate of Residency
  - Indigency Certificate
  - Good Moral Certificate
  - Business Permit
  - Burial Assistance
  - And more

- **Payment Integration**: Secure payment proof upload and verification system

- **Notification System**: Real-time updates on request status and announcements

- **Responsive Design**: Mobile-friendly interface accessible on all devices

- **Secure Authentication**: Password hashing, session management, and role-based permissions

- **Audit Logging**: Track user activities and system changes

## Technology Stack

### Frontend Technologies
- **HTML5**: Semantic markup and accessibility
- **CSS3**: Custom styling with responsive design
- **JavaScript**: Interactive features and AJAX requests
- **jQuery**: DOM manipulation and event handling

### Backend Technologies
- **PHP 7.4+**: Server-side scripting and business logic
- **MySQL**: Relational database management
- **PDO**: Secure database connections with prepared statements

### Security & Libraries
- **CSRF Protection**: Token-based cross-site request forgery prevention
- **Password Hashing**: Secure password storage using PHP's password_hash
- **Input Validation**: Server-side and client-side validation
- **File Upload Security**: MIME type checking and secure storage

### Development Tools
- **XAMPP**: Local development environment (Apache, MySQL, PHP)
- **phpMyAdmin**: Database management interface
- **Browser DevTools**: Debugging and testing

## Getting Started

### Prerequisites
Ensure you have the following installed on your system:
- XAMPP (or similar Apache/MySQL/PHP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, etc.)
- Text editor (VS Code recommended)

### Installation Steps

1. **Clone or Download the Project**
   ```
   # Place the project in your XAMPP htdocs directory
   # Example: C:\xampp\htdocs\e-Barangayxz
   ```

2. **Start XAMPP**
   - Launch XAMPP Control Panel
   - Start Apache and MySQL services

3. **Database Setup**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database (e.g., `e_barangay_db`)
   - Import database schema if available, or run setup scripts

4. **Configuration**
   - Update `db.php` with your database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'e_barangay_db';
     $username = 'root';
     $password = ''; // Default XAMPP password
     ```

5. **Run Setup Scripts** (if available)
   - Execute PHP files like `create_good_moral_table.php` to set up tables
   - Run `hash_password.php` for password management

6. **Access the Application**
   - Homepage: `http://localhost/e-Barangayxz/index.html`
   - Staff Login: `http://localhost/e-Barangayxz/staff-login.html`
   - Super Admin: `http://localhost/e-Barangayxz/super-admin.html`

## Available Scripts

### PHP Setup Scripts
- `create_good_moral_table.php`: Creates good moral certificate table
- `create_indigency_table.php`: Creates indigency certificate table
- `hash_password.php`: Password hashing utilities
- `update_superadmin_password.php`: Reset super admin password

### Debug Scripts
- `debug_requests.php`: Debug request handling
- `debug-resident.php`: Debug resident data
- `test_login.php`: Test login functionality
- `test_post.php`: Test POST requests

## Project Structure

```
e-Barangayxz/
├── index.html                          # Homepage
├── login-register.html                 # Resident authentication
├── resident-dashboard.php              # Resident main interface
├── staff-login.html                    # Staff login page
├── staff-dashboard.html                # Staff management interface
├── super-admin.html                    # Super admin panel
│
├── db.php                              # Database configuration
├── auth_check.php                      # Authentication middleware
├── csrf.php                            # CSRF token management
│
├── submit_barangay_clearance.php       # Clearance submission
├── submit_certificate_indigency.php    # Indigency certificate submission
├── submit_certificate_residency.php    # Residency certificate submission
├── submit_good_moral.php               # Good moral certificate submission
├── submit_payment_proof.php            # Payment proof upload
├── submit_request.php                  # General request submission
│
├── get_activities.php                  # Fetch activities
├── get_clearance_details.php           # Clearance data
├── get_csrf.php                        # CSRF token endpoint
├── get_notifications.php               # Notification data
├── get_payment_proof.php               # Payment proof retrieval
├── get_request_stats.php               # Request statistics
├── get_resident_stats.php              # Resident statistics
├── get_staff.php                       # Staff data
├── get_staff_profile.php               # Staff profile data
│
├── update_payment_status.php           # Payment status updates
├── update_request_status.php           # Request status updates
├── update_staff.php                    # Staff information updates
├── update_staff_contact.php            # Staff contact updates
├── update_staff_password.php           # Staff password updates
├── update_superadmin_password.php      # Super admin password reset
│
├── add_staff.php                       # Add new staff
├── delete_staff.php                    # Remove staff
├── restore_staff.php                   # Restore staff account
├── toggle_status.php                   # Toggle staff status
│
├── resident_forgot_password.php        # Password recovery
├── resident_login.php                  # Resident authentication
├── resident_logout.php                 # Resident logout
├── resident_register.php               # Resident registration
├── resident_reset_password.php         # Password reset
├── resident_change-password.php        # Password change
├── resident-profile.php                # Resident profile management
├── resident-profile-api.php            # Profile API
│
├── staff_login.php                     # Staff authentication
├── log_login.php                       # Login logging
├── log_logout.php                      # Logout logging
│
├── upload_document.php                 # Document upload handler
├── mark_notification_read.php          # Notification management
│
├── script.js                           # Main JavaScript file
├── filter.js                           # Filtering utilities
├── contact-format.js                   # Contact formatting
├── name-validation.js                  # Name validation
├── style.css                           # Main stylesheet
│
├── pics/                               # Static images
├── uploads/                            # User-uploaded files
│   ├── clearance_ids/                  # Clearance documents
│   ├── documents/                      # General documents
│   ├── good_moral_ids/                 # Good moral documents
│   ├── indigency_ids/                  # Indigency documents
│   ├── indigency_proofs/               # Indigency proofs
│   ├── payment_proofs/                 # Payment proofs
│
├── sidebar-profile.html                # Profile sidebar
├── sidebar-reports.html                # Reports sidebar
├── sidebar-requests.html               # Requests sidebar
├── sidebar-requests.php                # Requests sidebar (PHP)
├── sidebar-residents.php               # Residents sidebar
│
├── barangay-clearance.php              # Clearance page
├── barangay-id.html                    # Barangay ID page
├── burial-assistance.html              # Burial assistance page
├── business-permit.html                # Business permit page
├── certificate-residency.php           # Residency certificate page
├── goodmoral-certificate.php           # Good moral certificate page
├── indigency-certificate.html          # Indigency certificate page
├── indigency-certificate.php           # Indigency certificate (PHP)
├── low-income-certificate.html         # Low income certificate page
├── no-derogatory.html                  # No derogatory record page
├── non-employment.html                 # Non-employment certificate page
├── others.html                         # Other services page
├── soloparent-certificate.html         # Solo parent certificate page
│
├── aboutus.html                        # About us page
├── contact-format.js                   # Contact formatting script
├── first_login_modal.html              # First login modal
├── info.php                            # System information
├── test_*.php                          # Various test files
├── README.md                           # This file
└── ...
```

## Key Features

### Security Architecture
- **CSRF Protection**: Token-based prevention of cross-site request forgery
- **Prepared Statements**: SQL injection prevention using PDO
- **Input Sanitization**: Server-side validation and cleaning of user inputs
- **Secure File Uploads**: MIME type validation and secure storage paths
- **Session Management**: Secure PHP sessions with proper timeout handling

### Database Management
- **Connection Pooling**: Efficient database connections
- **Transaction Support**: Atomic operations for data integrity
- **Query Optimization**: Indexed queries for performance
- **Backup Support**: Easy database export/import

### File Management
- **Secure Uploads**: Validated file types and size limits
- **Organized Storage**: Categorized directories for different document types
- **Access Control**: Role-based file access permissions

## User Roles & Permissions

### Resident Role
- Register and manage personal account
- Submit requests for various certificates and clearances
- Upload payment proofs and required documents
- Track request status and view notifications
- Access personal dashboard and profile

### Staff Role
- Process resident requests and applications
- Review and approve/reject submissions
- Generate and manage documents
- View reports and statistics
- Manage notifications and announcements

### Super Admin Role
- Complete system administration
- Manage staff accounts (add, edit, delete, restore)
- Access system-wide analytics and reports
- Configure system settings
- Reset passwords and manage permissions

## Supported Services

The system currently supports the following barangay services:

- Barangay Clearance
- Certificate of Residency
- Indigency Certificate
- Good Moral Certificate
- Business Permit
- Burial Assistance
- Barangay ID
- Low Income Certificate
- No Derogatory Record Certificate
- Non-Employment Certificate
- Solo Parent Certificate
- Other Custom Services

## Configuration

### Environment Setup
The application uses PHP configuration files for database and system settings:

- `db.php`: Database connection parameters
- `csrf.php`: CSRF token configuration
- `auth_check.php`: Authentication settings

### Database Configuration
```php
// db.php
$host = 'localhost';
$dbname = 'e_barangay_db';
$username = 'root';
$password = '';
$charset = 'utf8mb4';
```

### Security Settings
- PHP sessions configured for security
- File upload limits set appropriately
- Error reporting configured for development/production

## Security Features

### Authentication & Authorization
- **Password Hashing**: bcrypt-style hashing for secure storage
- **Session Security**: Secure session handling with regeneration
- **Role-Based Access**: Granular permissions for different user types
- **Login Logging**: Track authentication attempts and activities

### Data Protection
- **SQL Injection Prevention**: Parameterized queries using PDO
- **XSS Protection**: Input validation and output escaping
- **CSRF Protection**: Token validation for state-changing operations
- **File Security**: Secure upload handling with type validation

### Audit & Monitoring
- **Activity Logging**: Track user actions and system events
- **Error Handling**: Secure error messages without information disclosure
- **Access Control**: IP-based restrictions and rate limiting

## API Endpoints

The system uses AJAX endpoints for dynamic content:

### Authentication
- `resident_login.php`: Resident login
- `resident_register.php`: Resident registration
- `staff_login.php`: Staff login
- `log_login.php`: Login logging

### Data Retrieval
- `get_staff.php`: Retrieve staff information
- `get_notifications.php`: Fetch notifications
- `get_request_stats.php`: Request statistics
- `get_resident_stats.php`: Resident statistics
- `get_activities.php`: Activity data

### Document Management
- `submit_request.php`: Submit new requests
- `update_request_status.php`: Update request status
- `upload_document.php`: Handle file uploads
- `get_payment_proof.php`: Retrieve payment proofs

### User Management
- `add_staff.php`: Add new staff member
- `update_staff.php`: Update staff information
- `delete_staff.php`: Remove staff member
- `toggle_status.php`: Change staff status

## Testing & Debugging

### Database Testing
```bash
# Test database connection
# Access db.php and check for connection errors

# Validate table structure
# Use phpMyAdmin to inspect tables created by setup scripts
```

### Authentication Testing
- Use `test_login.php` to test login functionality
- Check `debug_requests.php` for request debugging
- Verify CSRF tokens with `get_csrf.php`

### File Upload Testing
- Test document uploads through resident dashboard
- Verify file storage in `uploads/` directories
- Check MIME type validation

### Development Tools
- **Browser DevTools**: Network tab for AJAX requests
- **phpMyAdmin**: Database inspection and queries
- **XAMPP Logs**: Apache and MySQL error logs
- **PHP Error Logs**: Check for PHP errors and warnings

## Contributing

### Development Workflow
1. Fork the repository
2. Clone your fork locally
3. Create a feature branch: `git checkout -b feature/new-service`
4. Make your changes following PHP best practices
5. Test thoroughly on local XAMPP setup
6. Commit with descriptive messages: `git commit -m "feat: add new certificate type"`
7. Push to your fork: `git push origin feature/new-service`
8. Open a Pull Request with detailed description

### Code Standards
- **PHP**: Follow PSR standards, use prepared statements
- **JavaScript**: Use modern ES6+ features, proper error handling
- **Security**: Never commit sensitive data, validate all inputs
- **Documentation**: Update README and add code comments
- **Testing**: Test all new features before submission

### Security Guidelines
- Use prepared statements for all database queries
- Validate and sanitize all user inputs
- Follow secure file upload practices
- Keep PHP and dependencies updated
- Never expose sensitive information in error messages

## Support & Documentation

### Getting Help
- **Issues**: Report bugs on GitHub Issues
- **Documentation**: Check inline code comments and this README
- **Community**: Join discussions for feature requests

### Troubleshooting Common Issues
- **Database Connection**: Verify credentials in `db.php`
- **File Uploads**: Check upload directory permissions
- **PHP Errors**: Enable error reporting in PHP configuration
- **AJAX Requests**: Use browser DevTools Network tab

### Project Links
- **Repository**: [GitHub Repository URL]
- **Live Demo**: [Demo URL when deployed]
- **Documentation**: Available in code comments and this README

---

**Note**: This system is designed for local barangay use. Ensure compliance with local data protection regulations and implement additional security measures for production deployment.
