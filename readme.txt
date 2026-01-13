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

= Shared Hosting Support =

This plugin works seamlessly on shared hosting environments! All Laravel dependencies are pre-installed in the package. Simply:

1. Upload the plugin
2. Activate it
3. Configure your settings
4. Start chatting!

No command-line access or Composer installation required on your server.

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

The chatbot can access your WordPress database and perform actions through the WordPress REST API. It only works for administrators and respects WordPress permissions.

= Is my data secure? =

Yes! Your database credentials are stored securely in WordPress options. The OpenAI API only receives the necessary schema and query information, not your actual data.

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

== Privacy Policy ==

This plugin sends database schema information and user queries to OpenAI's API for processing. No actual database content is sent unless specifically queried. Your OpenAI API key and database credentials are stored locally in your WordPress database and never transmitted to third parties except OpenAI for query processing.

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








