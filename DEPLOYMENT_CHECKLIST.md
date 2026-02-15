# VKS Attendance System - Deployment Checklist

## Pre-Deployment Checklist

### 1. Environment Requirements
- [ ] PHP 7.4 or higher installed
- [ ] MySQL 5.7 or MariaDB 10.3+ installed
- [ ] Apache with mod_rewrite enabled
- [ ] HTTPS certificate installed (recommended)
- [ ] WebHost hosting account active

### 2. File Preparation
- [ ] All files downloaded/uploaded
- [ ] Directory structure verified
- [ ] .htaccess file present in root
- [ ] Database schema SQL file ready

## Deployment Steps

### Step 1: Database Setup
- [ ] Create MySQL database via WebHost panel
- [ ] Note database credentials:
  - Database name: ________________
  - Username: ____________________
  - Password: ____________________
  - Host: localhost (usually)
- [ ] Import database_schema.sql via phpMyAdmin
- [ ] Verify all tables created (15 tables total)
- [ ] Confirm default admin user exists

### Step 2: Configuration
- [ ] Update config/database.php with DB credentials
- [ ] Set correct timezone in config/database.php
- [ ] Verify BASE_PATH is '/vks/' in config
- [ ] Update .htaccess RewriteBase if needed

### Step 3: File Permissions
- [ ] Set logs/ to 755 or 777
- [ ] Set public/assets/uploads/ to 755 or 777
- [ ] Verify .htaccess is readable
- [ ] Check all PHP files have correct permissions

### Step 4: Initial Access
- [ ] Access: https://yourdomain.com/vks/
- [ ] Login with default credentials:
  - Email: admin@vks.local
  - Password: Admin@123
- [ ] Login successful? ___________

### Step 5: Security Configuration
- [ ] Change default admin password immediately
- [ ] Update admin email address
- [ ] Enable HTTPS redirects in .htaccess
- [ ] Test CSRF protection working
- [ ] Verify geolocation prompt appears

### Step 6: Branding Setup
- [ ] Update company name in settings
- [ ] Upload company logo
- [ ] Configure color scheme
- [ ] Test theme switching (dark/light)
- [ ] Verify changes appear on login page

### Step 7: CRON Jobs
- [ ] Add auto-logout CRON job (*/15 * * * *)
- [ ] Add quota reset CRON job (0 0 31 12 *)
- [ ] Test CRON jobs manually:
  ```bash
  php /path/to/vks/cron/auto-logout.php
  php /path/to/vks/cron/reset-quotas.php
  ```
- [ ] Verify CRON logs created in logs/cron.log

### Step 8: User Management
- [ ] Create test manager account
- [ ] Create test user account
- [ ] Assign manager to user
- [ ] Test role-based access

### Step 9: Feature Testing

#### Attendance
- [ ] Test punch-in with geolocation
- [ ] Test break start/end
- [ ] Test punch-out
- [ ] Verify 6/8/10 hour logic works
- [ ] Test midnight crossing scenario
- [ ] Verify auto-logout notification

#### Leave Management
- [ ] Create leave category
- [ ] Submit leave request as user
- [ ] Approve leave as manager
- [ ] Verify quota deduction
- [ ] Test comp-off addition

#### Notifications
- [ ] Test break violation notification (>75 min)
- [ ] Test leave approval notification
- [ ] Test auto-logout notification
- [ ] Verify daily reports generated

### Step 10: PWA Testing
- [ ] Test PWA installation on Android
- [ ] Test PWA installation on iOS
- [ ] Verify offline mode works
- [ ] Test service worker caching
- [ ] Check push notification permissions

### Step 11: Reports
- [ ] Generate monthly attendance report
- [ ] Export report as CSV
- [ ] Export report as PDF
- [ ] Test leave balance report
- [ ] Verify audit logs working

### Step 12: Performance
- [ ] Test page load speeds
- [ ] Verify mobile responsiveness
- [ ] Check database query performance
- [ ] Monitor PHP error logs
- [ ] Test with multiple concurrent users

## Post-Deployment

### Documentation
- [ ] Share README.md with team
- [ ] Create user guide/manual
- [ ] Document custom configurations
- [ ] Note any modifications made

### Training
- [ ] Train administrators
- [ ] Train managers
- [ ] Train end users
- [ ] Provide support contact info

### Monitoring
- [ ] Set up database backup schedule
- [ ] Monitor logs regularly:
  - logs/php_errors.log
  - logs/cron.log
- [ ] Track system usage
- [ ] Monitor disk space

### Maintenance Schedule
- [ ] Weekly: Check error logs
- [ ] Weekly: Database backup
- [ ] Monthly: Review audit logs
- [ ] Monthly: Check CRON job execution
- [ ] Quarterly: Security review
- [ ] Annually: System update check

## Troubleshooting Reference

### Common Issues
1. **500 Internal Server Error**
   - Check .htaccess syntax
   - Verify PHP version compatibility
   - Check folder permissions

2. **Database Connection Failed**
   - Verify credentials in config/database.php
   - Check if MySQL service is running
   - Confirm database exists

3. **Geolocation Not Working**
   - Ensure HTTPS is enabled
   - Check browser permissions
   - Test on different devices

4. **CRON Jobs Not Running**
   - Verify CRON syntax
   - Check file permissions (executable)
   - Test manually via SSH
   - Check CRON logs

5. **PWA Not Installing**
   - Verify manifest.json is accessible
   - Check service worker registration
   - Ensure HTTPS is enabled
   - Clear browser cache

## Support Contacts

**Technical Support:**
- Email: support@vks.local
- Phone: ___________________

**System Administrator:**
- Name: ___________________
- Email: __________________
- Phone: __________________

## Sign-Off

- [ ] Deployment completed successfully
- [ ] All checklist items verified
- [ ] System ready for production use

**Deployed By:** ___________________
**Date:** ___________________
**Signature:** ___________________

**Approved By:** ___________________
**Date:** ___________________
**Signature:** ___________________
