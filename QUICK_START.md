# VKS Attendance System - Quick Start Guide

## 🚀 5-Minute Setup

### Step 1: Upload Files (2 minutes)
1. Connect to Hostinger via File Manager or FTP
2. Navigate to `public_html/`
3. Create folder: `vks`
4. Upload all files to `public_html/vks/`

### Step 2: Database Setup (2 minutes)
1. Hostinger Panel → Databases → Create Database
2. Note your credentials
3. Open phpMyAdmin → Select database → Import
4. Choose `database_schema.sql` → Click "Go"
5. ✅ Done! 15 tables created

### Step 3: Configure (1 minute)
1. Edit `config/database.php`:
```php
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```
2. Save file

### Step 4: Access System
1. Go to: `https://yourdomain.com/vks/`
2. Login:
   - Email: `admin@vks.local`
   - Password: `Admin@123`
3. ✅ You're in!

### Step 5: Secure Your System
1. Change admin password immediately
2. Update company branding
3. Create your first user

## 📱 Features Overview

### For Users
- **Punch In/Out:** Click button, allow location, done!
- **Breaks:** Start/end breaks throughout the day
- **Leave Requests:** Submit and track leave requests
- **Dashboard:** See your attendance history

### For Managers
- **Approvals:** Review and approve team leave requests
- **Team View:** Monitor team attendance
- **Comp-Offs:** Add compensation leave for team members
- **Reports:** View break violations and daily reports

### For Admins
- **Full Control:** Manage users, settings, categories
- **Branding:** Customize logo, colors, company name
- **Analytics:** Comprehensive reports and audit logs
- **Settings:** Configure system behavior

## 🎯 Business Rules (Built-In)

### Attendance Status
- **< 6 hours** = Half Day
- **6-8 hours** = Short Day
- **≥ 8 hours** = Full Day
- **≥ 10 hours** = Auto Logout (forced)

### Break Violations
- Total breaks **> 75 minutes** triggers:
  - Manager notification
  - Dashboard flag
  - Daily report entry

### Leave Quotas
- Annual reset: **December 31st**
- Half-day leaves: **Deduct 0.5 days**
- No overlapping with existing attendance
- Comp-offs managed by managers

## 💡 Pro Tips

1. **Enable HTTPS:**
   - Uncomment HTTPS redirect in `.htaccess`
   - Geolocation requires HTTPS

2. **Mobile Usage:**
   - Install as PWA for best experience
   - Works offline, syncs when online

3. **CRON Jobs:**
   - Set up both CRON jobs (see README)
   - Test manually before scheduling

4. **Regular Backups:**
   - Weekly database backups via phpMyAdmin
   - Keep at least 4 weeks of backups

5. **Monitor Logs:**
   - Check `logs/php_errors.log` weekly
   - Review `logs/cron.log` for CRON issues

## 🔧 Common Tasks

### Add a New User
1. Admin → Users → Add New
2. Fill details, assign manager
3. User gets email credentials (if email configured)

### Create Leave Category
1. Admin → Leave Categories → Add New
2. Set quotas (monthly/quarterly/annual)
3. Save → Auto-assigned to all users

### Add Comp-Off
1. Manager → Team → Select User
2. Click "Add Comp-Off"
3. Enter days and reason
4. User notified automatically

### Generate Report
1. Reports → Select Type
2. Choose date range
3. Export as CSV or PDF

## 📞 Need Help?

### Documentation
- Full README: `/vks/README.md`
- Deployment Checklist: `/vks/DEPLOYMENT_CHECKLIST.md`

### Common Issues
- **Can't login?** Check database credentials
- **Geolocation denied?** Must use HTTPS or localhost
- **500 error?** Check folder permissions (755)
- **CRON not running?** Test manually via SSH

### Support
- Email: support@vks.local
- Check logs in `logs/` folder

## ✅ Quick Verification

After setup, verify:
- ✅ Login works
- ✅ Geolocation prompts on login
- ✅ Can punch in/out
- ✅ Can start/end breaks
- ✅ Dashboard shows data
- ✅ Settings page accessible
- ✅ PWA installs on mobile

**If all checked, you're ready to go!** 🎉

---

**System Version:** 1.0  
**Last Updated:** February 15, 2026  
**Developed By:** VKS Solutions
