# Changelog

All notable changes to the Hey Trisha WooCommerce Chatbot plugin will be documented in this file.

## [Unreleased] - 2025-01-XX

### Added
- **WordPress Admin Dashboard Configuration**: All settings (OpenAI API key, database credentials, WordPress API credentials) can now be configured through the WordPress admin dashboard - no `.env` files needed!
- **Automatic Server Management**: Laravel API server automatically starts/stops when needed - eliminates the need for manual `php artisan serve`
- **Name-Based Editing**: Edit posts and products by name (not just ID) with chat confirmation before proceeding
- **Pure NLP SQL Generation**: Fully AI-driven SQL query generation - no hardcoded queries or table names
- **Dynamic Configuration Service**: `WordPressConfigService` fetches all configurations from WordPress database via REST API
- **Post/Product Search Service**: `PostProductSearchService` for searching posts and products by name
- **Capability Question Detection**: Smart detection of questions about bot capabilities vs. data queries
- **Improved Error Handling**: Comprehensive error handling with user-friendly messages and proper exception handling
- **Modern Chatbot UI**: Redesigned chatbot interface with animations, better UX, and responsive design
- **Separate CSS File**: All CSS moved to `assets/css/chatbot.css` for better organization
- **Health Check Endpoint**: `/api/health` endpoint for server readiness checks
- **Performance Optimizations**: Reduced timeouts, non-blocking operations, lazy-loading configurations

### Changed
- **Configuration Management**: Moved from `.env` files to WordPress database storage
- **Server Management**: Automatic server lifecycle management integrated into WordPress plugin
- **NLP Flow**: Pure NLP approach - OpenAI receives full context and generates SQL queries without hardcoded assumptions
- **Error Messages**: More user-friendly, conversational error messages instead of technical jargon
- **Response Format**: Changed from bullet-point responses to natural, conversational language
- **UI Size**: Reduced chatbot window size (360px width, 550px height) for better visibility
- **Image Paths**: Dynamic image loading using `plugin_dir_url()` for better compatibility

### Fixed
- **500 Internal Server Errors**: Fixed `BindingResolutionException` by implementing missing `PostProductSearchService`
- **Hardcoded URLs**: Removed all hardcoded site-specific URLs for open-source compatibility
- **NLP Query Detection**: Fixed capability questions being incorrectly treated as data queries
- **Database Connection**: Improved database connection handling with better error messages
- **Config Fetching**: Optimized configuration fetching with caching and fallback mechanisms
- **Timeout Issues**: Reduced API timeouts and improved request handling

### Security
- **No Hardcoded Credentials**: All credentials stored securely in WordPress database
- **Shared Token Authentication**: Secure token-based authentication between WordPress and Laravel API
- **Input Validation**: Enhanced input validation and sanitization

### Technical Improvements
- **Dependency Injection**: Proper dependency injection for all services
- **Service Layer Architecture**: Clean separation of concerns with dedicated service classes
- **Error Logging**: Comprehensive logging for debugging and monitoring
- **Code Organization**: Better code structure and organization
- **Open-Source Ready**: Removed all site-specific hardcoded values

## [1.0.0] - Initial Release

### Features
- Basic AI-powered chatbot functionality
- SQL query generation from natural language
- WordPress REST API integration
- React-based frontend
- Admin-only access

---

**Note**: This changelog follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format.

