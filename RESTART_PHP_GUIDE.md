# How to Restart PHP on Production Server

## Find Your Web Server Setup

Run these commands to identify your setup:

```bash
# Check what web server is running
systemctl list-units --type=service | grep -E "(apache|nginx|httpd|php)"

# Check Apache status
systemctl status apache2
systemctl status httpd

# Check Nginx status  
systemctl status nginx

# Check PHP-FPM services (different versions)
systemctl status php-fpm
systemctl status php8.3-fpm
systemctl status php8.2-fpm
systemctl status php8.1-fpm
systemctl status php8.0-fpm
systemctl status php7.4-fpm

# Check what's listening on port 80/443
netstat -tlnp | grep -E ":(80|443)"
# OR
ss -tlnp | grep -E ":(80|443)"
```

## Common Restart Commands

### If using Apache:
```bash
# Try these variations:
systemctl restart apache2
systemctl restart httpd
service apache2 restart
service httpd restart
/etc/init.d/apache2 restart
```

### If using Nginx + PHP-FPM:
```bash
# Restart PHP-FPM (try different version numbers):
systemctl restart php8.3-fpm
systemctl restart php8.2-fpm
systemctl restart php8.1-fpm
systemctl restart php-fpm

# Restart Nginx:
systemctl restart nginx
```

### Alternative: Touch PHP files to clear opcache
If you can't restart the service, you can touch the PHP files to force reload:

```bash
# Touch the notifications.php file to force PHP to reload it
touch api/notifications.php
touch api/test-notifications.php

# This forces PHP to re-read the file (if opcache is configured to check file mtime)
```

### Manual opcache clear (if opcache is enabled)
```bash
# Create a simple PHP script to clear opcache
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'Opcache cleared'; } else { echo 'Opcache not enabled'; }"
```

## Quick Diagnostic

Run this to see what's actually running:

```bash
# Check running processes
ps aux | grep -E "(apache|nginx|php-fpm|httpd)" | head -10

# Check listening ports
lsof -i :80 -i :443 | head -10
```

