# Deployment Checklist - Notification & Email Fixes

## Files Modified - MUST BE DEPLOYED

### 1. Notification System Fixes

#### `api/notifications.php`
**Changes:**
- ✅ Fixed session handling (check if session already started)
- ✅ Added proper error handling for all database queries
- ✅ Wrapped all sync queries in try-catch blocks
- ✅ Fixed null user_id handling for regular users
- ✅ Added debug logging (development mode only)
- ✅ Improved error messages
- ✅ Added validation for unknown actions
- ✅ Ensured notifications array is always initialized

**Critical Fixes:**
- All table queries (complaints, tips, volunteers, events, etc.) now have try-catch blocks
- Prevents 500 errors when tables don't exist
- Better error messages for debugging

#### `index.php`
**Changes:**
- ✅ Enhanced `loadNotifications()` function with proper error handling
- ✅ Added response status checks before parsing JSON
- ✅ Added error messages for 401 (authentication) errors
- ✅ Added error states in notification dropdown
- ✅ Improved error logging to console

**Critical Fixes:**
- Now checks if API response is OK before parsing
- Shows user-friendly error messages
- Handles session expiration gracefully

### 2. Email System Fixes

#### `login.php`
**Changes:**
- ✅ Added login attempt counter alerts
- ✅ Improved email error handling and logging
- ✅ Enhanced error messages for email failures
- ✅ Better debugging information in error logs

**Critical Fixes:**
- Shows "X attempts remaining" warning before lockout
- Shows "Account Locked" message when locked
- Detailed email error logging

#### `api/forgot-password.php`
**Changes:**
- ✅ Enhanced email error handling
- ✅ Improved error logging with detailed information
- ✅ Better user feedback messages
- ✅ Added email configuration diagnostics

**Critical Fixes:**
- Detailed error logging for email failures
- Better error messages for users
- Improved debugging information

---

## Deployment Steps

### Step 1: Backup Current Files
```bash
# On production server, backup these files:
cp api/notifications.php api/notifications.php.backup
cp index.php index.php.backup
cp login.php login.php.backup
cp api/forgot-password.php api/forgot-password.php.backup
```

### Step 2: Upload Modified Files
Upload these 4 files to production server:
1. `api/notifications.php`
2. `index.php`
3. `login.php`
4. `api/forgot-password.php`

### Step 3: Verify File Permissions
```bash
# Ensure files are readable by web server
chmod 644 api/notifications.php
chmod 644 index.php
chmod 644 login.php
chmod 644 api/forgot-password.php
```

### Step 4: Test Notification System
1. Log in to the system
2. Open browser console (F12)
3. Click the notification bell
4. Check console for any errors
5. Verify notifications load (even if empty)

### Step 5: Test Email System
1. Try "Forgot Password" feature
2. Check PHP error logs for email sending status
3. Verify login attempt counter shows warnings
4. Test account lockout after 3 failed attempts

### Step 6: Check Error Logs
```bash
# Check PHP error logs for any issues
tail -f /var/log/php/error.log
# Or check your server's error log location
```

---

## Configuration Requirements

### Email Configuration (.env file)
Ensure these are set on production server:
```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=AlerTara QC
MAIL_ENCRYPTION=ssl
```

**Important:** Gmail requires App Password (not regular password)
- Enable 2-Step Verification
- Generate App Password at: https://myaccount.google.com/apppasswords

### Database Requirements
The notifications table will be created automatically if it doesn't exist.

---

## Testing Checklist

### Notification Bell
- [ ] Notification bell icon appears in header
- [ ] Clicking bell opens dropdown
- [ ] No console errors when loading notifications
- [ ] Shows "No notifications" when empty (not an error)
- [ ] Shows error message if session expired
- [ ] Badge shows unread count when notifications exist

### Login Attempt Counter
- [ ] Shows warning after 1st failed attempt: "2 attempts remaining"
- [ ] Shows warning after 2nd failed attempt: "1 attempt remaining"
- [ ] Shows "Account Locked" after 3rd failed attempt
- [ ] Account unlocks after 30 minutes

### Email Functionality
- [ ] OTP email sent for password reset
- [ ] Account lockout email sent
- [ ] Login success email sent
- [ ] Error logs show detailed email sending status

---

## Rollback Plan

If issues occur after deployment:

1. **Restore backup files:**
```bash
cp api/notifications.php.backup api/notifications.php
cp index.php.backup index.php.backup
cp login.php.backup login.php
cp api/forgot-password.php.backup api/forgot-password.php
```

2. **Clear browser cache** and test again

3. **Check error logs** for specific issues

---

## Post-Deployment Verification

### Quick Health Check
1. ✅ Login works
2. ✅ Notification bell loads without errors
3. ✅ Login attempt counter shows
4. ✅ Email configuration is working (check logs)

### Browser Console Check
Open Developer Tools (F12) → Console:
- Should see no errors when clicking notification bell
- Should see API calls to `api/notifications.php` returning 200 OK

### Network Tab Check
Open Developer Tools (F12) → Network:
- `api/notifications.php?action=sync` → Status: 200
- `api/notifications.php?action=list` → Status: 200

---

## Support & Troubleshooting

### Common Issues

**Issue:** Notification bell shows "Error loading notifications"
- **Check:** Browser console for specific error
- **Solution:** Check session, database connection, or API response

**Issue:** Emails not sending
- **Check:** PHP error logs for email errors
- **Solution:** Verify email configuration in .env file

**Issue:** Login attempt counter not showing
- **Check:** Failed attempts are being tracked in database
- **Solution:** Verify `failed_attempts` column exists in `admins` table

---

## Files Summary

| File | Purpose | Status |
|------|---------|--------|
| `api/notifications.php` | Notification API with error handling | ✅ Ready |
| `index.php` | Frontend notification loading | ✅ Ready |
| `login.php` | Login with attempt counter & email | ✅ Ready |
| `api/forgot-password.php` | Password reset with email | ✅ Ready |

**Total Files to Deploy: 4**

---

## Notes

- All changes are backward compatible
- No database migrations required (tables auto-create)
- No breaking changes to existing functionality
- All error handling is graceful (won't break existing features)

---

**Last Updated:** $(date)
**Version:** 1.0
**Status:** Ready for Production Deployment

