# ICLHO Document Route Internal Management System (DRIMS)

Full-stack document routing and management system for Iloilo City Local Housing Office with PHP backend, MySQL database, role-based access control, and real-time notifications.

## 🚀 Features

- **User Authentication**: Secure login for admins and employees with role-based access
- **Employee Management**: Add, edit, delete employees with auto-generated IDs
- **Document Upload & Routing**: Upload documents with metadata and route to teams
- **Document Tracking**: Track document status through workflow stages
- **Team-Based Routing**: Route documents across 10+ organizational teams
- **Messaging System**: Internal messaging between admin and employees
- **File Management Dashboard**: View, search, filter, and download documents
- **Real-Time Notifications**: Live updates for new messages and documents
- **Audit Logging**: Track all document and user activities
- **Excel Export**: Download document data as .xlsx or CSV
- **Mobile Responsive**: Works on all devices

## 📁 Project Structure

```
ICLHO_Route/
├── public/                    # Frontend files
│   ├── login.php             # Login page
│   ├── dashboard.php         # Admin dashboard
│   ├── employee_dashboard.php # Employee dashboard
│   ├── inbox.php             # Document routing tray
│   ├── new_document.php      # Document upload form
│   ├── file_management.php   # Document browser
│   ├── employees.php         # Employee management
│   ├── messages.php          # Messaging system
│   ├── profile.php           # User profile
│   ├── audit_logs.php        # Activity logs
│   ├── style.css             # Shared styles
│   ├── notifications.js      # Real-time notifications
│   └── ICLOGO.jpg            # Logo image
├── api/                      # Backend endpoints
│   ├── get_message.php       # Fetch messages
│   ├── get_messages_ajax.php # AJAX message handler
│   ├── get_notifications.php # Notification feed
│   ├── get_inbox_count.php   # Unread count
│   ├── get_doc_history.php   # Document history
│   ├── mark_notifications_read.php # Mark as read
│   ├── serve_file.php        # Secure file serving
│   └── serve_avatar.php      # User avatar handler
├── utils/                    # Utility modules
│   ├── audit_utils.php       # Audit logging
│   ├── notification_utils.php # Notification system
│   ├── document_history_utils.php # Version tracking
│   ├── password_utils.php    # Password hashing
│   └── notifications_sse.php # Server-Sent Events
├── components/               # Reusable components
│   ├── topbar_user.php       # Top bar with user info
│   ├── notification_bell.php # Notification dropdown
│   └── comments.php          # Comment widget
├── config/                   # Configuration
│   ├── db.php                # Database connection
│   ├── config.php            # App configuration
│   ├── db_production.php     # Production DB config
│   └── .env.example          # Environment template
├── uploads/                  # Document storage
├── lib/                      # External libraries
│   ├── mammoth.browser.min.js # DOCX viewer
│   ├── pdf.min.js            # PDF viewer
│   └── pdf.worker.min.js     # PDF worker
├── database/                 # Database files
│   ├── drims_database.sql    # Main schema
│   └── add_teams.sql         # Team data
├── .htaccess.backup          # Apache config backup
└── README.md                 # This file
```

## 🛠️ Setup Instructions

### 1. Prerequisites

- XAMPP installed (Apache + MySQL + PHP 7.4+)
- Web browser (Chrome, Firefox, Edge)
- Text editor (VS Code recommended)

### 2. Database Setup

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Create Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Create new database: `drims_database`
   - Set collation: `utf8mb4_unicode_ci`

3. **Import Database Schema**
   ```sql
   -- Import drims_database.sql via phpMyAdmin
   -- Or via command line:
   mysql -u root -p drims_database < drims_database.sql
   ```

4. **Import Team Data**
   ```sql
   -- Import add_teams.sql via phpMyAdmin
   -- This adds 10+ organizational teams
   ```

### 3. Local Development

1. **Install Project**
   ```bash
   # Place project in XAMPP htdocs
   C:\xampp\htdocs\ICLHO_Route\
   ```

2. **Configure Database**
   
   Create `.env` file (copy from `.env.example`):
   ```env
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=drims_database
   
   # Admin Settings
   ADMIN_USERNAME=admin
   ADMIN_PASSWORD=admin123
   
   # Upload Settings
   MAX_FILE_SIZE=10485760  # 10MB in bytes
   ALLOWED_FILE_TYPES=pdf,doc,docx,xls,xlsx,jpg,png
   
   # Session Settings
   SESSION_TIMEOUT=3600  # 1 hour
   ```

3. **Set Folder Permissions**
   ```bash
   # Make uploads folder writable
   chmod 755 uploads/
   ```

4. **Run Locally**
   - Open browser: http://localhost/ICLHO_Route/login.php
   - Login with default admin credentials

### 4. Default Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

**Test Employee Accounts:**
- Created via admin dashboard after login

## 🔐 Security Configuration

### Password Hashing

The system uses PHP's `password_hash()` with bcrypt:

```php
// In password_utils.php
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
```

### Database Security

Update `db.php` with production credentials:

```php
$host = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: 'your_secure_password';
$database = getenv('DB_NAME') ?: 'drims_database';
```

### File Upload Security

Configure in `config.php`:

```php
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png']);
```

### Session Security

Sessions are protected with:
- Secure session cookies
- HTTPOnly flag
- Session timeout (1 hour default)
- CSRF token validation

## 📊 Using the System

### Admin Workflow

1. **Login** at `/login.php`
2. **Manage Employees**:
   - Navigate to Employees menu
   - Add new employee (ID auto-generated: YYYY-NN)
   - Assign to team
   - Set email and password
3. **Upload Document**:
   - Click "New Document"
   - Fill metadata (title, reference, type)
   - Select originating and recipient team
   - Choose route type (Internal/External/Urgent/Confidential)
   - Attach file
   - Submit
4. **Track Documents**:
   - View in "Routing Tray"
   - Filter by team, status, route type
   - Update status (Pending → Completed)
5. **Monitor Activity**:
   - Check Audit Logs for all actions
   - View notifications for updates

### Employee Workflow

1. **Login** with employee credentials
2. **View Assigned Documents**:
   - See documents routed to your team
   - Filter by status or type
3. **Update Document Status**:
   - Open document in routing tray
   - Change status dropdown
   - Add comments/remarks
4. **Upload Documents**:
   - Submit new documents for routing
   - Track in "Outgoing" tab
5. **Communicate**:
   - Send/receive messages via Inbox
   - Get real-time notifications

### Document Statuses

- **Pending**: Awaiting action
- **Completed**: Finished processing
- **Pending for Completion**: Needs additional work
- **Reverted**: Sent back to originator

### Route Types

- **Internal**: Within organization
- **External**: External correspondence
- **Urgent**: Priority processing
- **Confidential**: Restricted access

## 🧪 Testing

### Test Document Upload

1. Login as admin
2. Navigate to "New Document"
3. Fill form with test data:
   - Title: "Test Document"
   - Reference: "TD-2026-001"
   - Type: "Memo"
   - Origin Team: "Admin"
   - Recipient Team: "Technical"
   - Route Type: "Internal"
   - File: Upload sample PDF
4. Submit and verify:
   - Success message appears
   - Document appears in routing tray
   - Recipient team gets notification
   - File stored in `uploads/` folder

### Test Employee Management

1. Login as admin
2. Go to "Employees"
3. Click "Add New Employee"
4. Fill form:
   - Name: "Test User"
   - Email: "test@iclho.gov.ph"
   - Team: "Technical"
   - Password: "test123"
5. Verify:
   - Employee ID auto-generated (e.g., 2026-01)
   - Employee appears in table
   - Can login with new credentials

### Test Notifications

1. Login as Employee A
2. Open another browser (incognito)
3. Login as Admin
4. Admin: Route document to Employee A's team
5. Employee A: See notification bell update (red dot)
6. Click bell: See new document notification
7. Mark as read: Red dot disappears

## 🐛 Troubleshooting

### Database Connection Failed

- Check XAMPP MySQL is running
- Verify credentials in `db.php`
- Ensure `drims_database` exists
- Check user permissions in phpMyAdmin

### File Upload Fails

- Check `uploads/` folder permissions (755)
- Verify file size under limit (10MB)
- Confirm file type is allowed
- Check PHP `upload_max_filesize` in `php.ini`

### Session Timeout Issues

- Increase session timeout in `config.php`
- Check `session.gc_maxlifetime` in `php.ini`
- Clear browser cookies and try again

### Notifications Not Working

- Ensure notifications.js is loaded
- Check browser console for errors
- Verify SSE endpoint: `notifications_sse.php`
- Test with browser's Network tab

### PDF/DOCX Preview Not Working

- Check `lib/` folder contains:
  - `pdf.min.js`
  - `pdf.worker.min.js`
  - `mammoth.browser.min.js`
- Verify file path in HTML

## 📝 Customization

### Add New Team

```sql
INSERT INTO teams (team_name) VALUES ('New Team Name');
```

### Change Upload File Size

Edit `config.php`:
```php
define('MAX_FILE_SIZE', 20971520); // 20MB
```

Update `php.ini`:
```ini
upload_max_filesize = 20M
post_max_size = 20M
```

### Customize Email Templates

Edit notification functions in `notification_utils.php`:

```php
function sendDocumentNotification($recipientEmail, $documentTitle) {
    $subject = "New Document Routed to Your Team";
    $message = "A new document titled '$documentTitle' has been routed to you.";
    // Add your email sending logic
}
```

### Change Theme Colors

Edit `style.css`:
```css
:root {
    --primary-color: #2563eb;    /* Blue */
    --secondary-color: #7c3aed;  /* Purple */
    --success-color: #10b981;     /* Green */
    --warning-color: #f59e0b;     /* Amber */
    --danger-color: #ef4444;      /* Red */
}
```

## 🔄 Maintenance

### Database Backup

```bash
# Backup via mysqldump
mysqldump -u root -p drims_database > backup_$(date +%Y%m%d).sql

# Restore backup
mysql -u root -p drims_database < backup_20260901.sql
```

### View Logs

```bash
# Apache error log
C:\xampp\apache\logs\error.log

# PHP error log
C:\xampp\php\logs\php_error_log
```

### Clean Old Files

```php
// Run maintenance script to delete old uploads
php maintenance/cleanup_old_files.php
```

### Update Dependencies

1. Update PHP libraries in `lib/`
2. Check for SQL schema updates
3. Review security patches

## 🏢 Teams Configuration

The system includes these pre-configured teams:
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

## 📞 Support

For issues or questions:
1. Check XAMPP logs for errors
2. Review browser console for JavaScript errors
3. Check database connection in phpMyAdmin
4. Verify file permissions on `uploads/` folder

## 📄 License

This project is for ICLHO internal use.

## 🔮 Roadmap

### Upcoming Features
- [ ] Email notifications (SMTP integration)
- [ ] Calendar view for due dates
- [ ] Advanced analytics dashboard
- [ ] Document version control
- [ ] Bulk document operations
- [ ] Custom workflow automation
- [ ] Mobile app (React Native)
- [ ] API for third-party integrations

### Security Enhancements
- [x] Password hashing (bcrypt)
- [x] SQL injection prevention
- [x] CSRF protection
- [x] Secure file serving
- [ ] Two-factor authentication
- [ ] Rate limiting
- [ ] HTTPS enforcement
- [ ] Security audit logging

---

**Version**: 1.0.0  
**Last Updated**: September 2026  
**Maintained by**: ICLHO Development Team
