=== Hey Trisha - AI-Powered WordPress & WooCommerce Chatbot ===
Contributors: mahakris123
Tags: chatbot, ai, openai, woocommerce, nlp, artificial intelligence
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

AI-powered chatbot using OpenAI GPT for WordPress and WooCommerce. Natural language queries, product management, and intelligent responses.

== Description ==

Hey Trisha is an intelligent AI-powered chatbot for WordPress and WooCommerce that uses OpenAI's GPT models to understand natural language queries and provide intelligent responses. Perfect for managing your WordPress site through conversational commands.

= Key Features =

* 🤖 **Natural Language Processing** - Ask questions in plain English
* 📊 **Database Queries** - Get data from your WordPress database using natural language
* 🛍️ **WooCommerce Integration** - Manage products, orders, and customers
* ✏️ **Content Management** - Create, update, and delete posts/products via chat
* 🔒 **Secure** - Administrator-only access with proper authentication
* 🌐 **Shared Hosting Compatible** - Works on any WordPress hosting environment
* ⚡ **Fast** - Optimized for performance with smart caching

= How It Works =

1. Install and activate the plugin
2. Configure your OpenAI API key and database credentials in settings
3. The chatbot appears in your WordPress admin for administrators
4. Ask questions or give commands in natural language
5. The AI generates appropriate SQL queries or WordPress API requests
6. Get instant, intelligent responses

= Example Queries =

* "Show me all orders from last week"
* "What are my top-selling products?"
* "Create a new post about AI technology"
* "Update the price of Product XYZ to $99"
* "How many users registered this month?"

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4.3 or higher (PHP 8.0+ recommended)
* MySQL 5.7 or higher
* OpenAI API key ([Get one here](https://platform.openai.com/))
* **For Development:** Composer (automatically handled on shared hosting)

= How It Processes Queries =

When you ask a question, the plugin:

1. Sends your natural language query to OpenAI's API
2. OpenAI generates appropriate database queries or WordPress REST API requests
3. The plugin executes these queries **locally** on your WordPress site
4. Results are formatted and displayed in the chat interface

**Important Security Note:** The plugin never sends your actual database content to external services during query generation. Only your question and the database structure (table/column names) are shared with OpenAI to understand your intent.

= Shared Hosting Support =

This plugin works seamlessly on shared hosting environments! All dependencies (Laravel framework) are bundled with the plugin and run through your existing web server. Simply:

1. Upload the plugin
2. Activate it
3. Configure your OpenAI API key and database settings
4. Start chatting!

No command-line access, Composer installation, or separate server required.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Hey Trisha"
4. Click "Install Now" and then "Activate"
5. Go to HeyTrisha Chatbot in the admin menu
6. Configure your OpenAI API key and database credentials
7. The chatbot will appear in your admin pages

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the downloaded ZIP file and click "Install Now"
5. Activate the plugin
6. Go to HeyTrisha Chatbot → Settings
7. Configure your OpenAI API key and database credentials

= Configuration =

After activation:

1. **OpenAI API Key**: Get your API key from [OpenAI Platform](https://platform.openai.com/)
2. **Database Credentials**: Enter your WordPress database connection details
3. **WordPress API**: Generate an Application Password from Users → Your Profile → Application Passwords
4. Save settings and start using the chatbot!

== Frequently Asked Questions ==

= Do I need technical knowledge to use this plugin? =

No! Simply install, configure your API keys, and start chatting. The AI handles all the technical complexity.

= Does this work on shared hosting? =

Yes! The plugin is specifically optimized for shared hosting environments. All dependencies are pre-installed.

= What data does the chatbot have access to? =

The chatbot can access your WordPress database (using the credentials you provide in settings) and perform actions through the WordPress REST API. Important notes:
* Only administrators can use the chatbot (requires 'manage_options' capability)
* We strongly recommend configuring read-only database credentials to prevent any modifications
* All queries are executed locally on your server - no database content is sent to external services

= Is my data secure? =

Yes! Security measures include:
* Database credentials are stored in an encrypted database table (not plain text)
* OpenAI API only receives your query and database schema (table/column names), not actual data content
* All database queries are executed locally on your WordPress server
* Administrator-only access with proper WordPress capability checks
* We recommend using read-only database credentials for additional security

= Does this require a separate server? =

No! On shared hosting, the Laravel API runs through your existing web server. On VPS/dedicated servers, it can optionally run as a separate process for better performance.

= What's the cost? =

The plugin is free! You only pay for OpenAI API usage based on your query volume. Typical usage costs pennies per month.

= Can I use this with WooCommerce? =

Yes! The chatbot has full WooCommerce integration for managing products, orders, and customers.

= What happens if I ask something the bot can't understand? =

The AI will provide a helpful response and suggest what kinds of questions you can ask.

== Screenshots ==

1. Chatbot interface in WordPress admin
2. Natural language query example
3. Database query results
4. Settings page
5. Shared hosting detection

== Changelog ==

= 1.0.0 - 2025-12-17 =
* Initial release
* Natural language processing with OpenAI GPT
* WordPress and WooCommerce integration
* Shared hosting support
* Dynamic configuration management
* Secure API authentication
* Automatic dependency handling
* Name-based product/post editing
* Conversational response formatting

== Upgrade Notice ==

= 1.0.0 =
Initial release of Hey Trisha chatbot plugin.

== External Services ==

This plugin relies on the following third-party external services:

= OpenAI API =

**What it is:** Hey Trisha uses OpenAI's GPT models to process natural language queries and generate intelligent responses.

**What data is sent:**
* Your natural language query/question
* Database schema information (table names and column names only, not actual data)
* Context about the type of operation requested

**When data is sent:**
* Each time you submit a query through the chatbot interface
* Data is only sent when an administrator actively uses the chatbot

**Service Provider:** OpenAI, L.L.C.
* [Terms of Use](https://openai.com/terms/)
* [Privacy Policy](https://openai.com/privacy/)
* [API Terms](https://openai.com/policies/api-terms/)

= Hey Trisha Website =

**What it is:** The plugin author's website provides documentation, terms and conditions, and support information.

**What data is sent:** No data is sent to heytrisha.com during plugin operation. The website is only linked for informational purposes (Terms and Conditions, support).

* [Terms and Conditions](https://heytrisha.com/terms-and-conditions)
* [Website](https://heytrisha.com)

== Privacy Policy ==

This plugin sends database schema information (table names and column structure only) and user queries to OpenAI's API for natural language processing. The actual content of your database records is only sent when you specifically query for that data.

**Data Storage:**
* Your OpenAI API key is stored securely in an encrypted database table
* Database credentials are stored locally and are never transmitted to any third party
* Chat history is stored locally in your WordPress database

**User Tracking:**
* This plugin does not track users
* No analytics or telemetry data is collected
* All processing happens between your WordPress site and OpenAI's API

**Important:** For security, we strongly recommend using read-only database credentials when configuring this plugin.

== Support ==

For support, please visit:
* [GitHub Repository](https://github.com/mahakris123/HeyTrisha)
* [Report Issues](https://github.com/mahakris123/HeyTrisha/issues)
* [Documentation](https://github.com/mahakris123/HeyTrisha#readme)

== Credits ==

Developed by mahakris123
Built with Laravel, React, and OpenAI

== License ==

This plugin is licensed under the MIT License. See LICENSE file for details.







