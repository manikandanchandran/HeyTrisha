# HeyTrisha
Plug and Play AI tool for Wordpress

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

- 🤖 **AI-Powered Chatbot**: Uses OpenAI GPT-4 to understand natural language queries
- 📊 **SQL Query Generation**: Automatically converts natural language to SQL queries
- 🔌 **WordPress REST API Integration**: Handles WordPress and WooCommerce operations
- ⚛️ **React Frontend**: Modern, responsive chatbot interface
- 🔐 **Admin-Only Access**: Chatbot only visible to WordPress administrators
- 📝 **Query Detection**: Automatically detects fetch vs. create/update operations

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

### Step 3: Configure the Laravel API

Create or edit the `.env` file in the `api` directory with your configuration (see [Configuration](#configuration) section below).

### Step 4: Set Up the Laravel API Server

The Laravel API needs to be running. You can use one of these methods:

**Option A: Using PHP Built-in Server (Development)**
```bash
cd api
php artisan serve
```
This will start the server at `http://localhost:8000`

**Option B: Using Apache/Nginx (Production)**
Configure your web server to point to the `api/public` directory.

### Step 5: Update Frontend API URL

Update the API endpoint in `assets/js/chatbot.js` (line 418) to match your Laravel API URL:
```javascript
let response = await fetch("http://localhost:8000/api/query", {
```

Replace `http://localhost:8000` with your actual API URL.

## ⚙️ Configuration

All configuration is done through the `.env` file located in the `api` directory.

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

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards for PHP
- Use ESLint for JavaScript/React code
- Write clear commit messages
- Add comments for complex logic
- Test thoroughly before submitting PR

## 📝 License

This project is open source. Please specify your license here.

## 🆘 Support

For issues, questions, or contributions:
- Open an issue on GitHub
- Contact: [Your Contact Information]

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [OpenAI API Documentation](https://platform.openai.com/docs)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [WooCommerce REST API Documentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)

---

**Made with ❤️ by Manikandan Chandran**
**www.HeyTrisha.com**
**www.manikandanchandran.com**

