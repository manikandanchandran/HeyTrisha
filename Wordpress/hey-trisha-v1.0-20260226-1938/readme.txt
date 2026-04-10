=== Hey Trisha ===
Contributors: mahakris123
Tags: chatbot, ai, openai, woocommerce, nlp
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4.3
Stable tag: 2.1.7
License: MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: hey-trisha

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

= 2.1.3 - 2026-02-26 =
* CHANGED: Email, First Name, and Last Name fields are now fully editable
* IMPROVED: Users can now update their email, first name, and last name from the settings page
* IMPROVED: Email changes are synced to the API server

= 2.1.2 - 2026-02-26 =
* FIXED: Changed API sync from POST to PUT method to match API endpoint requirements
* FIXED: Only sync API-compatible fields (openai_key) to API server
* FIXED: Improved error messages and logging for API sync failures
* IMPROVED: Username, password, and database credentials are stored locally only (not synced to API)

= 2.1.1 - 2026-02-26 =
* FIXED: AJAX configuration now embedded directly in JavaScript to fix "heytrishaPersonalDataAjax is not defined" error
* FIXED: Removed dependency on wp_localize_script for more reliable AJAX functionality

= 2.1.0 - 2026-02-26 =
* NEW: Added AJAX endpoints for fetching and updating personal data
* NEW: Settings page now uses AJAX instead of form submission for better UX
* NEW: Data is stored locally first, then synced to API server
* IMPROVED: Real-time feedback when saving personal data
* IMPROVED: Better error handling and user feedback for API sync operations
* SECURITY: All AJAX endpoints use proper nonce verification and API authentication

= 2.0.9 - 2026-02-26 =
* FIXED: Data fetching now prioritizes local storage as primary source
* FIXED: Removed non-existent /api/user endpoint, using only /api/site/info
* IMPROVED: Better handling of empty fields with proper fallback to local storage
* IMPROVED: All fields now properly populated from local storage during registration

= 2.0.8 - 2026-02-26 =
* IMPROVED: Enhanced API data fetching with multiple endpoint support and better error handling
* IMPROVED: API fetching now tries /api/user and /api/site/info endpoints for better compatibility
* IMPROVED: Better response format handling for different API response structures
* FIXED: Database credentials now properly stored during registration
* FIXED: All fields now properly pre-filled from API or local storage

= 2.0.7 - 2026-02-26 =
* NEW: Added API data fetching to pre-fill all fields from HeyTrisha API server
* NEW: Added Database Information section with DB Name, DB Username, and DB Password fields
* NEW: All personal data and keys are now automatically fetched and pre-filled from API
* IMPROVED: Settings page now syncs with API server to display current data
* IMPROVED: Database credentials can now be viewed and updated in settings

= 2.0.6 - 2026-02-26 =
* NEW: Added Personal Data & Keys section in settings page
* NEW: Email and name fields are now read-only (cannot be changed)
* NEW: Added eye icon toggle for password/key fields to show/hide values
* NEW: HeyTrisha API key is now read-only with eye icon for viewing
* NEW: Username, password, and OpenAI API key can be updated in settings
* Improved settings page with better organization and user experience

= 2.0.5 - 2026-02-26 =
* CRITICAL FIX: Added nonce verification helper function to handle expired nonces for admin users
* CRITICAL FIX: Added React 18 CDN loading to ensure chatbot widget displays correctly
* CRITICAL FIX: Improved React initialization with retry mechanism and better error handling
* Updated all AJAX handlers to use robust nonce verification
* Enhanced React rendering with fallback support for React 17 compatibility

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

= 2.1.3 =
NEW FEATURE: Email, First Name, and Last Name fields are now editable. You can update these fields from the settings page.

= 2.1.2 =
CRITICAL FIX: Fixed HTTP 403 error when syncing settings to API server. Changed API request method from POST to PUT and only syncs API-compatible fields.

= 2.1.1 =
CRITICAL FIX: Fixed JavaScript error that prevented AJAX functionality from working. Personal data save feature now works correctly.

= 2.1.0 =
NEW FEATURE: Personal data management now uses AJAX for better user experience. Data is stored locally first, then automatically synced to the API server. Real-time feedback is provided when saving settings.

= 2.0.9 =
CRITICAL FIX: Fixed data population issue. All fields now properly load from local storage. If fields are empty, please re-enter your information in the settings page.

= 2.0.8 =
IMPROVED: Enhanced API data fetching with better error handling and multiple endpoint support. All fields now properly pre-filled from API or local storage.

= 2.0.7 =
NEW FEATURE: Settings page now automatically fetches and pre-fills all data from HeyTrisha API. Added Database Information section with DB credentials fields.

= 2.0.6 =
NEW FEATURE: Added Personal Data & Keys management section. Email and name fields are now read-only for security. Eye icon toggle added for password/key fields.

= 2.0.5 =
CRITICAL UPDATE: Fixes security check errors and React loading issues. All users should update immediately.

= 1.0.0 =
Initial release of Hey Trisha chatbot plugin.

== External Services ==

This plugin requires an external AI service to function. All natural language query processing is handled by the HeyTrisha AI Engine.

**Service Provider:**
- Service Name: HeyTrisha AI Engine
- Default API URL: https://api.heytrisha.com
- Provider: HeyTrisha Technologies

**Purpose & Functionality:**
The external service processes natural language questions and generates safe SQL queries for your WordPress database. It uses OpenAI's GPT models to understand your questions and translate them into database queries.

**What Data is Transmitted:**
The plugin sends the following information to the API service:
- Your natural language questions (e.g., "Show me today's orders")
- WordPress database schema (table structure and column names only - NO actual data)
- WordPress site URL (for site identification and response routing)
- API authentication key (for secure authorization)
- OpenAI API credentials (stored securely on the API server during initial setup)

**What Data is NOT Transmitted:**
- Customer payment information or credit card data
- User passwords or authentication tokens
- Actual database records or content
- Personal identifying information (unless you specifically include it in your question)
- WordPress admin credentials

**When Data is Transmitted:**
- During initial plugin setup (one-time credential registration)
- Each time you submit a question via the chatbot interface
- When updating plugin settings
- NO automatic or background transmissions occur

**Service Terms & Privacy:**
- Terms of Service: https://heytrisha.com/terms-of-service
- Privacy Policy: https://heytrisha.com/privacy-policy
- Data Processing Agreement: Available upon request

**Self-Hosting Option:**
Advanced users can configure a custom API endpoint in the plugin settings to host their own instance of the HeyTrisha AI Engine. The engine source code and deployment instructions are available separately.

**Required for Functionality:**
This external service is mandatory for the plugin to work. The plugin acts as a lightweight client that delegates all AI processing to the external service, ensuring compatibility with WordPress.org hosting requirements.

== Privacy Policy ==

This plugin transmits data to the HeyTrisha external service. Please review the "External Services" section above for complete details on what data is sent, when, and how it is used. Your data privacy is protected in accordance with our Privacy Policy (https://heytrisha.com/privacy-policy).

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









