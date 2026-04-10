# HeyTrisha API - Shared Hosting Setup Guide

This guide is for users with **shared hosting** who cannot use command line tools.

## Step 1: Upload API Files

1. Upload the entire `api` folder to your server (via FTP/cPanel File Manager)
2. Place it in your desired location (e.g., `public_html/api` or `public_html/heytrisha-api`)

## Step 2: Set Up Database (No Command Line Required!)

### Option A: Using Web-Based Installer (Easiest)

1. **Upload the installer:**
   - Upload `database-installer.php` to your API directory
   - Example: `public_html/api/database-installer.php`

2. **Access via browser:**
   - Visit: `https://yourdomain.com/api/database-installer.php`
   - Enter the installer password (default: `heytrisha_setup_2026_CHANGE_ME`)
   - **IMPORTANT:** Change the password in the file before uploading!

3. **Enter database credentials:**
   - Database Host: Usually `localhost` (check with your hosting provider)
   - Database Port: Usually `3306`
   - Database Name: Create one in cPanel or use existing
   - Database Username: Your MySQL username
   - Database Password: Your MySQL password

4. **Click "Install Database"**
   - The installer will create the database and all tables
   - You'll see a success message with created tables

5. **Delete the installer file:**
   - **CRITICAL:** Delete `database-installer.php` immediately after setup for security!

### Option B: Using phpMyAdmin (Manual)

1. **Create database in cPanel:**
   - Go to cPanel → MySQL Databases
   - Create a new database: `heytrisha_api`
   - Note the full database name (usually includes your username prefix)

2. **Import SQL file:**
   - Go to phpMyAdmin
   - Select your database
   - Click **Import** tab
   - Choose `database/setup.sql` file
   - Click **Go**

3. **Verify tables:**
   - You should see `sites` and `migrations` tables

## Step 3: Configure Laravel Environment

1. **Find your API directory on server:**
   - Example: `public_html/api` or `public_html/heytrisha-api`

2. **Create/Edit `.env` file:**
   - Copy `.env.example` to `.env` (if it exists)
   - Or create a new `.env` file with these contents:

```env
APP_NAME="HeyTrisha API"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com/api

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

LOG_CHANNEL=stack
LOG_LEVEL=error
```

3. **Generate Application Key:**
   - If you have access to a file manager with terminal/SSH:
     ```bash
     php artisan key:generate
     ```
   - If not, you can generate a key manually:
     - Visit: `https://yourdomain.com/api/key-generator.php` (if you create one)
     - Or use online Laravel key generator and paste into `.env`

## Step 4: Set File Permissions

Using cPanel File Manager:

1. Navigate to your API directory
2. Set permissions:
   - `storage/` folder: **755** or **775**
   - `bootstrap/cache/` folder: **755** or **775**
   - `.env` file: **644** (readable, but not executable)

## Step 5: Configure Web Server

### For Apache (.htaccess should work automatically)

The `.htaccess` file in `public/` directory should handle routing automatically.

### For Nginx (if you have access)

You may need to configure Nginx to point to the `public` directory.

## Step 6: Test API

1. **Health Check:**
   - Visit: `https://yourdomain.com/api/public/api/health`
   - Should return JSON with status: "ok"

2. **Test Registration:**
   - Use Postman or your WordPress plugin
   - Endpoint: `POST https://yourdomain.com/api/public/api/register`

## Step 7: Update WordPress Plugin Settings

In your WordPress plugin settings:
- **API Server URL:** `https://yourdomain.com/api/public`
- **API Key:** Will be generated after registration

## Troubleshooting

### Error: "500 Internal Server Error"
- Check `.env` file exists and has correct permissions
- Check database credentials are correct
- Check Laravel logs: `storage/logs/laravel.log`

### Error: "Database connection failed"
- Verify database credentials in `.env`
- Check database host (may need to use `localhost` or IP address)
- Verify database user has proper permissions

### Error: "APP_KEY is required"
- Generate key: `php artisan key:generate`
- Or manually add to `.env`: `APP_KEY=base64:...`

### Can't access installer
- Check file permissions (should be 644)
- Check PHP version (needs PHP 7.4+)
- Check if file was uploaded correctly

## Security Checklist

- ✅ Delete `database-installer.php` after setup
- ✅ Change installer password before uploading
- ✅ Set `.env` file permissions to 644
- ✅ Don't expose `.env` file publicly
- ✅ Use HTTPS for API endpoints
- ✅ Keep Laravel updated

## File Structure on Server

```
public_html/
└── api/                          (or heytrisha-api/)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    │   ├── migrations/
    │   └── setup.sql
    ├── public/
    │   └── index.php            (Entry point)
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env                      (Create this)
    ├── .htaccess
    └── database-installer.php    (Delete after setup!)
```

## Need Help?

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check PHP error logs in cPanel
3. Verify database connection using phpMyAdmin
4. Test API endpoint: `/api/health`

## Next Steps

After database setup:
1. ✅ Database created and tables installed
2. ⏭️ Configure `.env` file
3. ⏭️ Generate APP_KEY
4. ⏭️ Test API endpoints
5. ⏭️ Register from WordPress plugin


