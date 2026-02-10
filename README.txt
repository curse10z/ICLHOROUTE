================================================================================
                    DRIMS - Document Route Internal Management System
                                    README FILE
================================================================================

SYSTEM OVERVIEW:
----------------
DRIMS is a web-based document routing and management system designed to 
streamline document workflows within organizations. The system allows 
administrators and employees to upload, route, track, and manage documents 
across different teams with status tracking and messaging capabilities.

TECHNOLOGY STACK:
-----------------
- Backend: PHP
- Database: MySQL (via XAMPP)
- Frontend: HTML, CSS, JavaScript
- Server: Apache (XAMPP)


================================================================================
                            CURRENTLY IMPLEMENTED FEATURES
================================================================================

1. USER AUTHENTICATION & AUTHORIZATION
   ✓ Admin login system (username: admin, password: admin123)
   ✓ Employee login system with unique employee IDs
   ✓ Session-based authentication
   ✓ Role-based access control (Admin vs Employee)
   ✓ Secure logout functionality

2. EMPLOYEE MANAGEMENT (Admin Only)
   ✓ Add new employees with auto-generated employee IDs (format: YYYY-NN)
   ✓ Edit employee information (name, email, password, team)
   ✓ Delete employees with confirmation
   ✓ Search employees by ID, name, email, or team
   ✓ View all employees in a sortable table
   ✓ Email validation for employee accounts
   ✓ Automatic ID gap filling (reuses deleted employee IDs)

3. TEAM MANAGEMENT
   ✓ Pre-configured teams in database:
     - Admin
     - Frontdesk
     - Technical
     - Survey
     - TXZ
     - Atty. Peter
     - OV (Office of the Vice)
     - Eviction and Dismantling
     - Legal Team
     - HHRO (Human Resources and Housing Operations)
   ✓ Team-based document routing
   ✓ Team filtering in document views

4. DOCUMENT UPLOAD & ROUTING
   ✓ Upload documents with file attachments
   ✓ Document metadata:
     - Title
     - Reference number
     - Document type (letter, memo, report, etc.)
     - Originating team
     - Recipient team
     - Route type (Internal, External, Urgent, Confidential)
     - Due date (route_before)
     - Remarks/comments
   ✓ File storage in uploads directory
   ✓ Track who uploaded the document (admin or employee)

5. DOCUMENT ROUTING TRAY
   ✓ Three routing views:
     a) New Documents - Shows pending documents
     b) Incoming Routed Documents - Documents routed to user's team
     c) Outgoing Routed Documents - Documents uploaded by the user
   ✓ Document status management:
     - Pending
     - Completed
     - Pending for Completion
     - Reverted
   ✓ Status update via dropdown in table
   ✓ Route type badges with color coding
   ✓ Filter documents by:
     - Team
     - Status
     - Route type
     - Search text (title, reference, type, remarks)

6. FILE MANAGEMENT DASHBOARD
   ✓ View all uploaded documents
   ✓ Search and filter capabilities
   ✓ Document preview/view functionality
   ✓ Download documents
   ✓ Open documents in new tab
   ✓ View detailed document information in modal
   ✓ Access control (employees see only their team's documents)

7. MESSAGING SYSTEM
   ✓ Internal messaging between admin and employees
   ✓ Message inbox with unread count
   ✓ Track sender and recipient information
   ✓ Message read/unread status
   ✓ Subject and message body support

8. DASHBOARD FEATURES
   ✓ Admin Dashboard:
     - Total documents count
     - Total employees count
     - Unread messages count
     - Welcome message with user name
   ✓ Employee Dashboard:
     - Personalized view for employees
     - Team-specific document access

9. USER INTERFACE
   ✓ Modern, responsive design with gradient backgrounds
   ✓ Glassmorphism effects
   ✓ Collapsible sidebar navigation
   ✓ Mobile-friendly hamburger menu
   ✓ Interactive hover effects and animations
   ✓ Color-coded status badges
   ✓ Modal dialogs for forms
   ✓ Professional typography (Inter font family)
   ✓ Card-based layouts
   ✓ Smooth transitions and micro-animations

10. DATABASE STRUCTURE
    ✓ admin table - Admin credentials
    ✓ employees table - Employee information
    ✓ teams table - Team definitions
    ✓ documents table - Document metadata and routing
    ✓ messages table - Internal messaging
    ✓ Proper indexing for performance
    ✓ Automatic table creation on first run


================================================================================
                            FEATURES IN PROCESS / PLANNED
================================================================================

1. INCOMPLETE FEATURES
   ⚠ Routes page - Navigation link exists but page not implemented
   ⚠ Settings page - Navigation link exists but page not implemented
   ⚠ Profile page (Employee) - Navigation link exists but not implemented
   ⚠ Comments feature - Button exists in routing tray but shows alert placeholder
   ⚠ File Management link in some sidebars points to "#" instead of file_management.php

2. MISSING FUNCTIONALITY
   ⚠ Document routing history/audit trail
   ⚠ Document version control
   ⚠ Advanced search with date range filters
   ⚠ Email notifications for document routing
   ⚠ Document archiving system
   ⚠ Bulk document operations
   ⚠ Export/print functionality for reports
   ⚠ User activity logs
   ⚠ Password reset functionality
   ⚠ Password encryption (currently stored as plain text)

3. POTENTIAL IMPROVEMENTS
   ⚠ Document workflow automation
   ⚠ Custom team creation by admin
   ⚠ Role-based permissions (beyond admin/employee)
   ⚠ Document templates
   ⚠ Advanced analytics and reporting
   ⚠ Calendar view for due dates
   ⚠ Overdue document notifications
   ⚠ Document sharing with external users
   ⚠ Mobile app version
   ⚠ API for third-party integrations

4. SECURITY ENHANCEMENTS NEEDED
   ⚠ Password hashing (currently plain text)
   ⚠ CSRF protection
   ⚠ SQL injection prevention improvements
   ⚠ File upload validation and sanitization
   ⚠ Session timeout configuration
   ⚠ Rate limiting for login attempts
   ⚠ HTTPS enforcement


================================================================================
                            INSTALLATION & SETUP
================================================================================

PREREQUISITES:
1. XAMPP installed (Apache + MySQL + PHP)
2. Web browser (Chrome, Firefox, Edge, etc.)

INSTALLATION STEPS:
1. Place the ICLHO_Route folder in: C:\xampp\htdocs\
2. Start XAMPP Control Panel
3. Start Apache and MySQL services
4. Open phpMyAdmin: http://localhost/phpmyadmin
5. Import database:
   - Create database named: drims_database
   - Import file: drims_database.sql
   OR
   - Run the SQL file directly in phpMyAdmin
6. Access the system: http://localhost/ICLHO_Route/login.php

DEFAULT CREDENTIALS:
- Admin: username = admin, password = admin123

DATABASE CONFIGURATION:
- Edit db.php to change database connection settings if needed
- Default: localhost, root, no password, drims_database


================================================================================
                            FILE STRUCTURE
================================================================================

ICLHO_Route/
├── db.php                      - Database connection configuration
├── login.php                   - Login page for admin and employees
├── logout.php                  - Logout handler
├── dashboard.php               - Admin dashboard
├── employee_dashboard.php      - Employee dashboard
├── employees.php               - Employee management (admin only)
├── inbox.php                   - Document routing tray
├── file_management.php         - File management dashboard
├── new_document.php            - Document upload form
├── messages.php                - Messaging system
├── get_message.php             - Message retrieval handler
├── check_database.php          - Database structure verification
├── style.css                   - Main stylesheet
├── drims_database.sql          - Database schema and initial data
├── add_teams.sql               - Additional team data
├── ICLOGO.jpg                  - System logo
├── ICLHO.jpg                   - Organization image
├── uploads/                    - Document storage directory
│   └── index.php              - Directory protection
└── .vscode/                    - VS Code configuration


================================================================================
                            USAGE GUIDELINES
================================================================================

FOR ADMINISTRATORS:
1. Login with admin credentials
2. Manage employees via Employees menu
3. Upload documents via Document Upload
4. Route documents to teams
5. Monitor document status in Routing Tray
6. View all documents in File Management
7. Communicate via Inbox

FOR EMPLOYEES:
1. Login with employee credentials (provided by admin)
2. View documents routed to your team
3. Update document status
4. Upload documents for routing
5. View your outgoing documents
6. Communicate with admin via messages


================================================================================
                            KNOWN ISSUES
================================================================================

1. Password stored in plain text (security risk)
2. Some navigation links point to non-existent pages
3. Comments feature not implemented
4. No document history tracking
5. Limited error handling in some areas
6. File upload size limited by PHP configuration


================================================================================
                            SUPPORT & MAINTENANCE
================================================================================

For technical support or feature requests, contact your system administrator.

MAINTENANCE TASKS:
- Regular database backups
- Monitor uploads folder size
- Review and archive old documents
- Update employee records as needed
- Check system logs for errors


================================================================================
                            VERSION INFORMATION
================================================================================

Current Version: 1.0 (In Development)
Last Updated: February 2026
Database Version: 1.0

================================================================================
                                    END OF README
================================================================================
