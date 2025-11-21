# Configuration Quick Reference Guide

## 📍 Where to Add Credentials

All configuration is done in **one file**: `api/.env`

**Location**: `api/.env` (create this file if it doesn't exist)

---

## 🔑 OpenAI API Key

### Where to Add:
**File**: `api/.env`

### Configuration:
```env
OPENAI_API_KEY=sk-your-openai-api-key-here
```

### How to Get:
1. Visit https://platform.openai.com/
2. Sign up or log in
3. Go to API Keys section
4. Create a new API key
5. Copy and paste in `.env` file

### Where It's Used:
- `api/config/openai.php` - Configuration file that reads this key
- `api/app/Services/SQLGeneratorService.php` - For SQL query generation
- `api/app/Services/WordPressRequestGeneratorService.php` - For WordPress API request generation
- `api/app/Services/OpenAiService.php` - For OpenAI operations

---

## 🗄️ Database Credentials

### Where to Add:
**File**: `api/.env`

### Configuration:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

### Important Notes:
- Use the **same database** as your WordPress installation
- Ensure the database user has proper read/write permissions
- The database connection is configured in `api/config/database.php`

### Where It's Used:
- `api/config/database.php` - Database configuration file
- `api/app/Services/MySQLService.php` - For database operations
- `api/app/Http/Controllers/NLPController.php` - For query execution

---

## 🌐 WordPress API Credentials

### Where to Add:
**File**: `api/.env`

### Configuration:
```env
WORDPRESS_API_URL=http://your-wordpress-site.com
WORDPRESS_API_USER=your_wordpress_username
WORDPRESS_API_PASSWORD=your_wordpress_application_password
```

### How to Get Application Password:
1. Log in to WordPress Admin
2. Go to **Users** → **Your Profile**
3. Scroll to **Application Passwords**
4. Enter a name (e.g., "Chatbot API")
5. Click **Generate New Application Password**
6. Copy the password (shown only once)
7. Use your WordPress username and this password

### Where It's Used:
- `api/app/Services/WordPressApiService.php` - For WordPress API calls
- `api/app/Services/WordPressRequestGeneratorService.php` - For generating API requests

---

## 📝 Complete .env Example

Here's a complete example of what your `api/.env` file should look like:

```env
APP_NAME="Hey Trisha Chatbot"
APP_ENV=local
APP_KEY=base64:your-generated-key-here
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wordpress_db
DB_USERNAME=root
DB_PASSWORD=your_password

# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-api-key-here

# WordPress API Configuration
WORDPRESS_API_URL=http://localhost/wordpress
WORDPRESS_API_USER=admin
WORDPRESS_API_PASSWORD=xxxx xxxx xxxx xxxx xxxx
```

---

## 🔐 Security Notes

1. **Never commit `.env` file** to version control
2. Keep your API keys and passwords secure
3. Use different credentials for development and production
4. Regularly rotate API keys and passwords
5. Use strong passwords for database access

---

## ✅ Verification Checklist

After adding credentials, verify:

- [ ] `.env` file exists in `api/` directory
- [ ] OpenAI API key is set and valid
- [ ] Database credentials are correct and accessible
- [ ] WordPress API URL is correct
- [ ] WordPress API credentials are valid
- [ ] Laravel application key is generated (`php artisan key:generate`)

---

## 🆘 Troubleshooting

### API Key Not Working
- Check if the key is correct (no extra spaces)
- Verify the key is active on OpenAI platform
- Check Laravel logs: `api/storage/logs/laravel.log`

### Database Connection Failed
- Verify database credentials are correct
- Check if database server is running
- Ensure database user has proper permissions
- Check Laravel logs for detailed error messages

### WordPress API Not Working
- Verify WordPress API URL is correct (include http:// or https://)
- Check if Application Password is correct
- Ensure WordPress REST API is enabled
- Check Laravel logs for authentication errors

---

**Need Help?** Check the main README.md for detailed documentation.

