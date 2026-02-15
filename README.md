# VKS Attendance System - Complete Installation Guide

## 📋 Overview
A production-ready PHP MVC attendance tracking system with:
- ✅ **6/8/10 Hour Business Logic** (Auto status calculation)
- ✅ **Auto-logout at 10 hours**
- ✅ **Break violation tracking** (>75 min alerts)
- ✅ **Admin-configurable leave categories**
- ✅ **Quota management** (Resets Dec 31)
- ✅ **Geolocation capture** (Required for punch-in/out)
- ✅ **PWA support** (Offline capability, push notifications)
- ✅ **Mobile-first responsive design**
- ✅ **Dark/Light mode toggle**
- ✅ **Role-based access** (Admin, Manager, User)

---

## 🚀 Installation Steps

### Step 1: Upload Files to Your WebHost
1. **Connect via FTP/File Manager**
   - Login to your WebHost account
   - Go to File Manager or use FTP client (FileZilla)

2. **Create Subfolder**
   ```
   public_html/vks/
   ```

3. **Upload All Files**
   - Upload the entire `vks-attendance` folder contents to `public_html/vks/`
   - Ensure the folder structure matches:
     ```
     public_html/
     └── vks/
         ├── .htaccess
         ├── database_schema.sql
         ├── README.md
         ├── app/
         │   ├── Controllers/
         │   ├── Models/
         │   └── Views/
         ├── config/
         ├── public/
         ├── pwa/
         └── logs/
     ```

### Step 2: Set Up Database

1. **Create MySQL Database**
   - Go to Hostinger Control Panel → Databases → MySQL Databases
   - Click "Create Database"
   - Database name: `your_database_name`
   - Username: `your_database_user`
   - Password: `your_database_pass`
   - Click "Create"

2. **Import Schema**
   - Go to phpMyAdmin
   - Select your newly created database
   - Click "Import" tab
   - Choose `database_schema.sql` file
   - Click "Go"
   - ✅ This will create all tables, triggers, and default data

### Step 3: Configure Database Connection

1. **Edit config/database.php**
   ```php
   define('DB_HOST', 'localhost');              // Usually 'localhost'
   define('DB_NAME', 'your_database_name');     // Your database name
   define('DB_USER', 'your_database_user');     // Your database username
   define('DB_PASS', 'your_database_pass');     // Your database password
   ```

2. **Save the file**

### Step 4: Set Folder Permissions

Set the following folder permissions (chmod):

```bash
logs/                    → 755 or 777
public/assets/uploads/   → 755 or 777
```

**Using File Manager:**
- Right-click folder → Permissions → Set to 755

**Using FTP:**
- Right-click folder → File Permissions → Set to 755

### Step 5: Access the System

1. **Open in Browser**
   ```
   https://yourdomain.com/vks/
   ```

2. **Default Admin Login**
   ```
   Email: admin@vks.local
   Password: Admin@123
   ```

3. **⚠️ IMPORTANT: Change default password immediately!**

---

## 🔧 Configuration

### Update Company Branding

**Method 1: Via Admin Panel (After Login)**
1. Login as Admin
2. Go to Settings → Branding
3. Update:
   - Company Name
   - Logo (Upload image)
   - Color Theme

**Method 2: Via Database**
```sql
UPDATE system_settings SET setting_value = 'Your Company Name' WHERE setting_key = 'company_name';
```

### Enable HTTPS (Recommended)

1. Edit `.htaccess`
2. Uncomment these lines:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

### Configure Timezone

Edit `config/database.php`:
```php
define('TIMEZONE', 'Asia/Kolkata');  // Change to your timezone
```

---

## ⚙️ CRON Jobs Setup

### Required CRON Jobs

1. **Auto-Logout (Every 15 minutes)**
   ```bash
   */15 * * * * php /home/your_account/public_html/vks/cron/auto-logout.php
   ```

2. **Quota Reset (Daily at midnight on Dec 31)**
   ```bash
   0 0 31 12 * php /home/your_account/public_html/vks/cron/reset-quotas.php
   ```

**To Add CRON Jobs in Hostinger:**
1. Go to Advanced → Cron Jobs
2. Click "Create Cron Job"
3. Enter command and schedule
4. Save

---

## 📱 PWA Installation

### For Users (Mobile)

**Android:**
1. Open the site in Chrome
2. Tap "Add to Home Screen" when prompted
3. Or: Menu (⋮) → "Install app"

**iOS:**
1. Open the site in Safari
2. Tap Share button
3. Tap "Add to Home Screen"

### Push Notifications Setup

To enable push notifications, you'll need to:
1. Get VAPID keys from a service like Firebase
2. Update service worker with keys
3. Implement push notification API calls

(Detailed guide in `docs/push-notifications.md`)

---

## 🏗️ System Architecture

### Business Logic Implementation

**Attendance Status (6/8/10 Rule)**
```php
// Automatically calculated on punch-out:
< 6 hours   → half_day
6-8 hours   → short_day
≥ 8 hours   → full_day
≥ 10 hours  → auto_logged_out (forced punch-out)
```

**Break Violations**
```php
// Total breaks > 75 minutes:
1. Notification to Manager (real-time)
2. Flagged in dashboard
3. Included in daily report (end of day)
```

**Leave Quotas**
```php
// Reset on December 31st annually
- Monthly quotas
- Quarterly quotas  
- Annual quotas
- Comp-off balances (Manager can add)
```

**Midnight Crossing**
```php
// If punch-in session crosses midnight:
1. Force punch-out at 23:59:59 of previous day
2. Create new session if user punches in after midnight
```

---

## 👥 User Roles & Permissions

### Admin
- ✅ Full system access
- ✅ User management (create, edit, delete)
- ✅ Leave category management
- ✅ System settings & branding
- ✅ Reports & analytics
- ✅ Audit logs

### Manager
- ✅ View team attendance
- ✅ Approve/reject leave requests
- ✅ Add comp-offs
- ✅ View break violations
- ✅ Generate team reports
- ✅ Can also be a regular user (dual role)

### User
- ✅ Punch in/out
- ✅ Start/end breaks
- ✅ Request leaves
- ✅ View own attendance history
- ✅ Check leave balances
- ✅ View notifications

---

## 🔒 Security Features

- ✅ **CSRF Protection** on all forms
- ✅ **Session regeneration** on login
- ✅ **PDO prepared statements** (SQL injection prevention)
- ✅ **Password hashing** (bcrypt)
- ✅ **Input sanitization**
- ✅ **File upload restrictions**
- ✅ **Audit logging** (all critical actions)
- ✅ **Geolocation recording** (login + attendance)

---

## 📊 Reports Available

### Pre-built Reports
1. **Monthly Attendance Summary** (CSV, PDF)
2. **Leave Balance Report** (CSV, PDF)
3. **Break Violation Report** (Daily)
4. **Team Attendance Dashboard**
5. **Audit Trail Export**

### Generate Reports
- Go to Reports section in dashboard
- Select report type and date range
- Export as CSV or PDF

---

## 🐛 Troubleshooting

### Database Connection Error
```
✓ Check config/database.php credentials
✓ Ensure database exists
✓ Check if MySQL service is running
```

### 404 Errors
```
✓ Verify .htaccess is uploaded
✓ Check mod_rewrite is enabled
✓ Ensure BASE_PATH is '/vks/' in config/database.php
```

### Geolocation Not Working
```
✓ Site must be on HTTPS (or localhost)
✓ User must grant location permission
✓ Check browser compatibility
```

### PWA Not Installing
```
✓ Ensure manifest.json is accessible
✓ Check service worker registration
✓ Site must be on HTTPS
✓ Clear browser cache
```

### File Upload Errors
```
✓ Check folder permissions (755 or 777)
✓ Verify upload_max_filesize in php.ini
✓ Check file type restrictions
```

---

## 📂 File Structure

```
vks-attendance/
├── .htaccess                    # Apache configuration
├── database_schema.sql          # Complete database schema
├── README.md                    # This file
│
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── AdminController.php
│   │   └── ManagerController.php
│   │
│   ├── Models/
│   │   ├── Attendance.php       # 6/8/10 logic implementation
│   │   ├── User.php
│   │   ├── Leave.php
│   │   └── Notification.php
│   │
│   └── Views/
│       ├── auth/
│       ├── user/
│       ├── manager/
│       ├── admin/
│       └── shared/              # Reusable components
│
├── config/
│   └── database.php             # Database configuration
│
├── public/                      # Public web root
│   ├── index.php                # Main router
│   ├── css/
│   ├── js/
│   └── assets/
│       └── uploads/             # User uploads (755/777)
│
├── pwa/
│   ├── manifest.json            # PWA manifest
│   └── sw.js                    # Service worker
│
└── logs/                        # Error logs (755/777)
    └── php_errors.log
```

---

## 🔄 Updates & Maintenance

### Backup Database (Weekly Recommended)
```bash
# Via phpMyAdmin: Export → SQL
# Or via command line:
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### Monitor Logs
```bash
# Check error logs regularly
tail -f logs/php_errors.log
```

### Update System
1. Backup database and files
2. Upload new files
3. Run any migration scripts
4. Clear cache

---

## 📞 Support

**System Developed By:** Vikram Kumar Sinha  
**Version:** 1.0  
**Release Date:** February 15, 2026

For technical support:
- Email: vkslocal@gmail.com
- Documentation: /vks/docs/

---

## 📄 License

Proprietary software. All rights reserved.
Unauthorized copying, modification, or distribution is prohibited.

---

## ✅ Post-Installation Checklist

- [ ] Database created and schema imported
- [ ] Database credentials updated in config/database.php
- [ ] .htaccess uploaded and working
- [ ] Folder permissions set (logs/, uploads/)
- [ ] Accessed system via browser successfully
- [ ] Logged in with default admin credentials
- [ ] Changed default admin password
- [ ] Updated company branding (name, logo, colors)
- [ ] CRON jobs configured
- [ ] HTTPS enabled (recommended)
- [ ] Created test users for each role
- [ ] Tested punch-in/out with geolocation
- [ ] Tested leave request workflow
- [ ] Tested PWA installation on mobile
- [ ] Configured timezone

---

## 🎯 Next Steps

1. **Create Users**: Add your team members via Admin panel
2. **Configure Leave Categories**: Customize leave types and quotas
3. **Set Up Managers**: Assign managers to users
4. **Test Workflows**: Run through complete attendance cycle
5. **Train Users**: Share login credentials and user guide
6. **Monitor System**: Check logs and reports regularly

**🎉 Your attendance system is now ready to use!**
