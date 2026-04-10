# HeyTrisha API Database Setup Guide

This guide will help you set up the database for the HeyTrisha API backend.

## Prerequisites

- MySQL 5.7+ or MariaDB 10.2+
- Database user with CREATE DATABASE privileges
- PHP 7.4+ with PDO MySQL extension

## Method 1: Using SQL Script (Quick Setup)

### Step 1: Create Database Manually

1. **Access your MySQL/MariaDB:**
   ```bash
   mysql -u root -p
   ```

2. **Run the setup script:**
   ```bash
   mysql -u root -p < database/setup.sql
   ```
   
   Or copy and paste the contents of `database/setup.sql` into your MySQL client.

3. **Verify the database was created:**
   ```sql
   SHOW DATABASES;
   USE heytrisha_api;
   SHOW TABLES;
   DESCRIBE sites;
   ```

### Step 2: Configure Laravel Environment

1. **Copy the environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` file and set database credentials:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=heytrisha_api
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   ```

3. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

## Method 2: Using Laravel Migrations (Recommended)

### Step 1: Create Database Manually

Create the database using phpMyAdmin, MySQL command line, or cPanel:

```sql
CREATE DATABASE `heytrisha_api` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 2: Configure Environment

1. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` file:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=heytrisha_api
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   ```

3. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

### Step 3: Run Migrations

```bash
php artisan migrate
```

This will create all required tables including:
- `sites` table (with all user account and database credential fields)
- `migrations` table (tracks migration status)

### Step 4: Verify Setup

```bash
php artisan migrate:status
```

You should see all migrations listed as "Ran".

## Method 3: Using cPanel (For Shared Hosting)

### Step 1: Create Database

1. Log into cPanel
2. Go to **MySQL Databases**
3. Create a new database: `heytrisha_api`
4. Create a database user and assign it to the database
5. Note down the database name, username, and password

### Step 2: Import SQL Script

1. Go to **phpMyAdmin** in cPanel
2. Select your database (`heytrisha_api`)
3. Click **Import** tab
4. Choose `database/setup.sql` file
5. Click **Go**

### Step 3: Configure Laravel

1. Upload files to your server
2. Edit `.env` file with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_cpanel_db_name
   DB_USERNAME=your_cpanel_db_user
   DB_PASSWORD=your_cpanel_db_password
   ```

3. Set permissions:
   ```bash
   chmod -R 755 storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

## Database Structure

### Sites Table

The `sites` table stores:
- **Site Information:** `site_url`, `email`
- **User Account:** `username`, `password` (hashed), `first_name`, `last_name`
- **Database Credentials:** `db_name`, `db_username`, `db_password` (encrypted)
- **API Keys:** `api_key_hash` (SHA-256 hash), `openai_key` (encrypted)
- **Version Info:** `wordpress_version`, `woocommerce_version`, `plugin_version`
- **Status:** `is_active`, `query_count`, `last_query_at`

## Security Notes

1. **Database Password:** The `db_password` field is encrypted using Laravel's Crypt facade
2. **API Keys:** API keys are hashed (SHA-256) before storage
3. **OpenAI Keys:** OpenAI API keys are encrypted before storage
4. **User Passwords:** User passwords are hashed using Laravel's Hash facade

## Troubleshooting

### Error: "Access denied for user"
- Check database username and password in `.env`
- Verify user has proper permissions

### Error: "Table already exists"
- If using SQL script, tables may already exist
- Use `CREATE TABLE IF NOT EXISTS` or drop existing tables first

### Error: "Migration failed"
- Check database connection in `.env`
- Verify database exists
- Check Laravel logs: `storage/logs/laravel.log`

### Error: "Class 'PDO' not found"
- Install PHP PDO extension: `sudo apt-get install php-mysql` (Ubuntu) or `brew install php-mysql` (Mac)

## Testing Database Connection

Run this command to test:
```bash
php artisan tinker
```

Then in tinker:
```php
DB::connection()->getPdo();
// Should return: PDO object
```

## Next Steps

After database setup:
1. Configure your API server URL in WordPress plugin settings
2. Test registration endpoint: `POST /api/register`
3. Check database for registered sites: `SELECT * FROM sites;`

## Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify database credentials
3. Ensure all migrations ran successfully
4. Check PHP error logs


