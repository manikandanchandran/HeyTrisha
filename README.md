# Hey Trisha WooCommerce Chatbot

An intelligent WordPress chatbot plugin that uses OpenAI to convert natural language queries into SQL queries and WordPress REST API requests. Built with React frontend and Laravel backend.

## 📋 Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Folder Structure](#folder-structure)
- [Architecture](#architecture)
- [Usage](#usage)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)

## ✨ Features

- 🤖 **AI-Powered Chatbot**: Uses OpenAI GPT-3.5-turbo/GPT-4 to understand natural language queries
- 📊 **Pure NLP SQL Generation**: Fully AI-driven SQL query generation from natural language - no hardcoded queries
- 🔌 **WordPress REST API Integration**: Handles WordPress and WooCommerce operations
- ⚛️ **Modern React Frontend**: Beautiful, responsive chatbot interface with animations
- 🔐 **Admin-Only Access**: Chatbot only visible to WordPress administrators
- 📝 **Smart Query Detection**: Automatically detects fetch vs. create/update operations and capability questions
- ⚙️ **WordPress Admin Configuration**: All settings managed through WordPress admin dashboard (no .env files needed)
- 🚀 **Automatic Server Management**: Laravel API server starts/stops automatically - no manual setup required
- ✏️ **Name-Based Editing**: Edit posts and products by name (not just ID) with chat confirmation
- 🎯 **Dynamic Configuration**: No hardcoded values - fully dynamic and open-source ready
- 🛡️ **Robust Error Handling**: Comprehensive error handling with user-friendly messages

## 📦 Requirements

- WordPress 5.0 or higher
- WooCommerce (optional, for WooCommerce features)
- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer (for Laravel dependencies)
- Node.js and npm (for React development)
- OpenAI API Key

## 🚀 Installation

### Step 1: Install the Plugin

1. Clone or download this repository to your WordPress plugins directory:
   ```bash
   wp-content/plugins/heytrisha-woo/
   ```

2. Activate the plugin through the WordPress admin panel:
   - Go to **Plugins** → **Installed Plugins**
   - Find "Hey Trisha Woocommerce Chatbot"
   - Click **Activate**

### Step 2: Install Laravel Dependencies

1. Navigate to the `api` directory:
   ```bash
   cd api
   ```

2. Install PHP dependencies using Composer:
   ```bash
   composer install
   ```

3. Copy the environment file:
   ```bash
   cp .env.example .env
   ```
   
   **Note**: If `.env.example` doesn't exist, create a `.env` file manually (see Configuration section below).

4. Generate Laravel application key:
   ```bash
   php artisan key:generate
   ```

### Step 3: Configure the Plugin

**All configuration is done through the WordPress Admin Dashboard - no .env files needed!**

1. Go to **HeyTrisha Settings** in your WordPress admin menu
2. Configure the following:
   - **OpenAI API Key**: Your OpenAI API key for AI functionality
   - **Database Credentials**: Your WordPress database connection details
   - **WordPress API Credentials**: Your WordPress username and application password
   - **Shared Access Token**: A secure token for API communication (auto-generated)

3. The Laravel API server will **automatically start** when you visit the settings page
4. You can manually control the server using the "Start Server", "Stop Server", and "Restart Server" buttons

**Note**: The plugin automatically manages the Laravel API server - no manual `php artisan serve` needed!

## ⚙️ Configuration

**All configuration is now done through the WordPress Admin Dashboard!** No need to edit `.env` files manually.

### WordPress Admin Configuration

1. Navigate to **HeyTrisha Settings** in your WordPress admin menu
2. Fill in the following settings:

#### OpenAI API Key
- Enter your OpenAI API key
- Get your key from [OpenAI Platform](https://platform.openai.com/)

#### Database Credentials
- **Database Host**: Usually `127.0.0.1` or `localhost`
- **Database Port**: Usually `3306`
- **Database Name**: Your WordPress database name
- **Database User**: Your database username
- **Database Password**: Your database password

#### WordPress API Credentials
- **WordPress API URL**: Your WordPress site URL (e.g., `http://localhost/wordpress`)
- **WordPress API Username**: Your WordPress username
- **WordPress API Password**: Generate an Application Password from **Users → Your Profile → Application Passwords**

#### Shared Access Token
- A secure token for API communication
- Auto-generated, but you can customize it
- Used for secure communication between WordPress and Laravel API

### Legacy .env Configuration (Optional)

If you prefer using `.env` files, you can still create `api/.env` with the following:

### 📍 Where to Add Credentials

**File Location**: `api/.env`

If the `.env` file doesn't exist, create it in the `api` directory.

### 🔑 OpenAI API Key Configuration

Add your OpenAI API key to enable AI functionality:

```env
OPENAI_API_KEY=sk-your-openai-api-key-here
```

**How to get an OpenAI API Key:**
1. Visit [OpenAI Platform](https://platform.openai.com/)
2. Sign up or log in
3. Navigate to API Keys section
4. Create a new API key
5. Copy the key and paste it in the `.env` file

**Configuration File**: The API key is read from `api/config/openai.php` which references `env('OPENAI_API_KEY')`.

### 🗄️ Database Credentials Configuration

Add your MySQL database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

**Configuration File**: Database settings are in `api/config/database.php` which reads from these environment variables.

**Important Notes:**
- The database should be the same WordPress database you're using
- Make sure the database user has proper permissions to read/write
- For production, use secure credentials and consider using environment-specific configs

### 🌐 WordPress API Credentials Configuration

Add your WordPress REST API credentials:

```env
WORDPRESS_API_URL=http://your-wordpress-site.com
WORDPRESS_API_USER=your_wordpress_username
WORDPRESS_API_PASSWORD=your_wordpress_application_password
```

**How to get WordPress Application Password:**
1. Go to WordPress Admin → **Users** → **Your Profile**
2. Scroll down to **Application Passwords**
3. Enter a name (e.g., "Chatbot API")
4. Click **Generate New Application Password**
5. Copy the generated password (it will only be shown once)
6. Use your WordPress username and this application password

**Configuration Usage**: These credentials are used in:
- `api/app/Services/WordPressApiService.php`
- `api/app/Services/WordPressRequestGeneratorService.php`

### 📝 Complete .env Example

Here's a complete example of what your `api/.env` file should look like:

```env
APP_NAME="Hey Trisha Chatbot"
APP_ENV=local
APP_KEY=base64:your-generated-key-here
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wordpress_db
DB_USERNAME=root
DB_PASSWORD=your_password

OPENAI_API_KEY=sk-your-openai-api-key-here

WORDPRESS_API_URL=http://localhost/wordpress
WORDPRESS_API_USER=admin
WORDPRESS_API_PASSWORD=xxxx xxxx xxxx xxxx xxxx
```

## 📁 Folder Structure

```
heytrisha-woo/
│
├── api/                          # Laravel Backend API
│   ├── app/
│   │   ├── Console/
│   │   ├── Exceptions/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── NLPController.php      # Main controller handling queries
│   │   │   │   └── WordPressApiController.php
│   │   │   ├── Kernel.php
│   │   │   └── Middleware/
│   │   ├── Models/
│   │   ├── Providers/
│   │   └── Services/                      # Core business logic
│   │       ├── MySQLService.php           # Database operations
│   │       ├── OpenAiService.php         # OpenAI integration
│   │       ├── SQLGeneratorService.php   # SQL query generation
│   │       ├── WordPressApiService.php   # WordPress API calls
│   │       └── WordPressRequestGeneratorService.php
│   ├── config/
│   │   ├── app.php
│   │   ├── database.php                   # Database configuration
│   │   └── openai.php                     # OpenAI configuration
│   ├── routes/
│   │   └── api.php                        # API routes
│   ├── database/
│   │   └── migrations/
│   ├── public/
│   │   └── index.php                      # Laravel entry point
│   ├── storage/
│   │   └── logs/                          # Application logs
│   ├── .env                               # ⚠️ Configuration file (create this)
│   ├── composer.json
│   └── artisan
│
├── assets/                      # Plugin assets
│   ├── css/
│   │   └── chatbot.css
│   ├── img/
│   │   ├── bot.jpeg
│   │   ├── boticon.jpg
│   │   └── heytrisha.jpeg
│   └── js/
│       ├── chatbot.js                    # Main chatbot script
│       └── chatbot-react-app/             # React source (optional)
│
├── chatbot/                     # Built React app (optional)
│   └── static/
│
├── heytrisha-woo.php            # Main plugin file
├── test-page.html               # Testing page
└── README.md                    # This file
```

### 📂 Key Files Explained

#### Backend (Laravel API)

1. **`api/app/Http/Controllers/NLPController.php`**
   - Main controller that handles user queries
   - Routes queries to SQL or WordPress API based on query type
   - Detects fetch operations vs. create/update operations

2. **`api/app/Services/SQLGeneratorService.php`**
   - Generates SQL queries from natural language using OpenAI
   - Takes user query and database schema as input

3. **`api/app/Services/MySQLService.php`**
   - Fetches database schema dynamically
   - Executes SQL queries safely

4. **`api/app/Services/WordPressRequestGeneratorService.php`**
   - Generates WordPress REST API requests using OpenAI
   - Converts natural language to API endpoint + payload

5. **`api/app/Services/WordPressApiService.php`**
   - Sends requests to WordPress REST API
   - Handles authentication

6. **`api/routes/api.php`**
   - Defines API endpoints
   - Main endpoint: `POST /api/query`

#### Frontend (WordPress Plugin)

1. **`heytrisha-woo.php`**
   - Main plugin file
   - Enqueues React and chatbot scripts
   - Creates chatbot container div

2. **`assets/js/chatbot.js`**
   - React-based chatbot component
   - Handles user interactions
   - Communicates with Laravel API

## 🏗️ Architecture

### How It Works

1. **User Query**: Administrator types a natural language query in the chatbot
2. **Query Detection**: The system determines if it's a fetch (SELECT) or create/update operation
3. **AI Processing**:
   - **For Fetch Operations**: 
     - Gets database schema
     - Generates SQL query using OpenAI
     - Executes SQL query
     - Returns results
   - **For Create/Update Operations**:
     - Generates WordPress REST API request using OpenAI
     - Sends request to WordPress API
     - Returns response
4. **Response Display**: Results are formatted and displayed in the chatbot

### Technology Stack

- **Backend**: Laravel 10 (PHP 8.1+)
- **Frontend**: React 18 (via CDN)
- **AI**: OpenAI GPT-4
- **Database**: MySQL
- **API**: WordPress REST API

## 💻 Usage

### For Administrators

1. Log in to WordPress as an administrator
2. Navigate to any admin page
3. Look for the chatbot widget in the bottom-right corner
4. Type your query in natural language, for example:
   - "Show me the last 10 products"
   - "List all posts published this month"
   - "Create a new product named 'Laptop' priced at $1200"
   - "Add a post titled 'My Journey'"

### Example Queries

**Fetch Operations (SQL):**
- "Show me all products"
- "List the last 5 orders"
- "Display all users"
- "Get products with price less than 100"

**Create/Update Operations (WordPress API):**
- "Create a new post titled 'Hello World'"
- "Add a product named 'Widget' priced at 50"
- "Update product ID 123 with price 99"

## 🔧 Development

### Setting Up Development Environment

1. **Clone the repository**
2. **Install backend dependencies**:
   ```bash
   cd api
   composer install
   ```

3. **Install frontend dependencies** (if modifying React app):
   ```bash
   cd assets/js/chatbot-react-app
   npm install
   ```

4. **Build React app** (if modifying):
   ```bash
   npm run build
   ```

5. **Start Laravel development server**:
   ```bash
   cd api
   php artisan serve
   ```

### Testing

1. Ensure the Laravel API is running
2. Open WordPress admin panel
3. The chatbot should appear in the bottom-right corner
4. Test with various queries

### Debugging

- **Laravel Logs**: Check `api/storage/logs/laravel.log`
- **Browser Console**: Check for JavaScript errors
- **Network Tab**: Monitor API requests to `/api/query`

## 🤝 Contributing

Contributions are welcome! This is an open-source project and we appreciate any contributions.

### How to Contribute

1. **Fork the repository** from [https://github.com/manikandanchandran/HeyTrisha](https://github.com/manikandanchandran/HeyTrisha)
2. **Create a feature branch**:
   ```bash
   git checkout -b feature/AmazingFeature
   ```
3. **Make your changes** and test thoroughly
4. **Commit your changes**:
   ```bash
   git commit -m 'Add some AmazingFeature'
   ```
5. **Push to your branch**:
   ```bash
   git push origin feature/AmazingFeature
   ```
6. **Open a Pull Request** on the original repository

### Development Guidelines

- **Code Standards**: Follow PSR-12 coding standards for PHP
- **JavaScript**: Use ESLint for JavaScript/React code
- **Commit Messages**: Write clear, descriptive commit messages
- **Documentation**: Add comments for complex logic
- **Testing**: Test thoroughly before submitting PR
- **No Hardcoded Values**: Ensure all values are dynamic and configurable
- **Error Handling**: Include proper error handling and user-friendly messages
- **Security**: Never commit sensitive data or credentials

For detailed contribution guidelines, see [CONTRIBUTING.md](CONTRIBUTING.md).

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For issues, questions, or contributions:
- **GitHub Issues**: [Open an issue](https://github.com/manikandanchandran/HeyTrisha/issues)
- **Pull Requests**: [Contribute code](https://github.com/manikandanchandran/HeyTrisha/pulls)
- **Documentation**: See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [OpenAI API Documentation](https://platform.openai.com/docs)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [WooCommerce REST API Documentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)

---

**Made with ❤️ by WIncredible Technologies**




