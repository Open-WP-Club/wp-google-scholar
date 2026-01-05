# Google Scholar Profile Display Plugin - Development Guide

## Project Overview

This is a WordPress plugin that fetches and displays Google Scholar profile information on WordPress websites. The plugin scrapes Google Scholar profiles and displays them using the `[scholar_profile]` shortcode.

**Plugin Version**: 1.3.5
**WordPress Compatibility**: 5.0+
**PHP Requirements**: 7.0+
**License**: GPL v2 or later

## Core Functionality

### What This Plugin Does

1. **Scrapes Google Scholar profiles** for academic publication data
2. **Displays profile information** including avatar, name, affiliation, metrics, publications, and co-authors
3. **Automatic updates** via WordPress cron scheduler (daily, weekly, monthly, yearly)
4. **Manual refresh** capability from admin settings
5. **Pagination and sorting** for publications list
6. **SEO optimization** with schema.org markup
7. **Error handling** with consecutive failure tracking

### Key Features

- Shortcode-based display: `[scholar_profile]`
- Configurable display options (show/hide sections)
- Client-side and server-side sorting
- Responsive design with mobile support
- Caches profile images in WordPress media library
- Comprehensive error logging and admin notifications

## Architecture

### File Structure

```
wp-google-scholar/
├── wp-google-scholar.php       # Main plugin file (initialization, hooks, activation)
├── includes/
│   ├── scraper.php            # Google Scholar web scraping logic
│   ├── settings.php           # Admin settings page and options handling
│   ├── shortcode.php          # [scholar_profile] shortcode rendering
│   ├── scheduler.php          # WordPress cron scheduling
│   └── seo.php               # Schema.org and SEO metadata
├── views/
│   └── settings-page.php     # Admin settings page template
├── assets/
│   ├── css/
│   │   ├── style.css         # Frontend styles
│   │   └── admin-style.css   # Admin page styles
│   └── js/
│       └── scholar-sorting.js # Client-side sorting functionality
├── languages/                 # Internationalization files
└── README.md                 # Plugin documentation
```

### Class Structure (PSR-4 Autoloading)

- **Namespace**: `WPScholar\`
- **Classes**:
  - `WPScholar\Scraper` - Handles Google Scholar scraping
  - `WPScholar\Settings` - Admin settings and configuration
  - `WPScholar\Shortcode` - Shortcode rendering and display
  - `WPScholar\Scheduler` - Cron job management
  - `WPScholar\SEO` - SEO and schema markup

## Key Components

### 1. Scraper (`includes/scraper.php`)

**Purpose**: Fetches and parses Google Scholar profile data

**Key Methods**:
- `fetch_profile($profile_id)` - Main scraping method
- `download_and_cache_image($url, $profile_id)` - Caches profile images
- Handles error detection (blocked access, profile not found, parsing errors)

**Important Notes**:
- Uses WordPress HTTP API (`wp_remote_get()`)
- Parses HTML DOM to extract data
- Implements retry logic for failed requests
- Tracks consecutive failures for error reporting
- **Scraping Challenges**: Google Scholar may block IPs with excessive requests

### 2. Settings (`includes/settings.php`)

**Purpose**: Admin interface for plugin configuration

**Settings Stored**:
- `profile_id` - Google Scholar profile ID (required)
- `show_avatar`, `show_info`, `show_publications`, `show_coauthors` - Display toggles
- `update_frequency` - Cron schedule (daily, weekly, monthly, yearly)
- `max_publications` - Limit on publications to fetch (50, 100, 200, 500)

**Options Storage**:
- Main settings: `scholar_profile_settings`
- Cached data: `scholar_profile_data`
- Last update: `scholar_profile_last_update`
- Error tracking: `scholar_profile_consecutive_failures`, `scholar_profile_last_error_details`

### 3. Shortcode (`includes/shortcode.php`)

**Purpose**: Renders profile data on frontend

**Shortcode**: `[scholar_profile]`

**Supported Parameters**:
- `per_page` - Publications per page (default: 20, range: 1-100)
- `sort_by` - Sort field (year, citations, title)
- `sort_order` - Sort direction (asc, desc)

**Example Usage**:
```
[scholar_profile per_page="15" sort_by="citations" sort_order="desc"]
```

### 4. Scheduler (`includes/scheduler.php`)

**Purpose**: Manages automatic profile updates via WordPress cron

**Hook**: `scholar_profile_update`

**Features**:
- Configurable frequency (daily, weekly, monthly, yearly)
- Activates on plugin activation
- Deactivates on plugin deactivation

### 5. SEO (`includes/seo.php`)

**Purpose**: Adds schema.org markup for better search engine visibility

**Implements**:
- Person schema
- ScholarlyArticle schema for publications
- Meta tags for academic profiles

## Development Guidelines

### WordPress Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use WordPress APIs instead of raw PHP where possible
- Sanitize inputs, escape outputs
- Use WordPress nonces for form security
- Internationalize all user-facing strings with `__()` or `_e()`

### Security Best Practices

1. **Always escape output**: Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`
2. **Sanitize input**: Use `sanitize_text_field()`, `sanitize_url()`, etc.
3. **Verify capabilities**: Check `current_user_can('manage_options')` for admin actions
4. **Nonces**: Use `wp_nonce_field()` and `wp_verify_nonce()` for form submissions
5. **Prevent direct access**: All PHP files start with `if (!defined('ABSPATH')) { exit; }`

### Common WordPress Functions Used

- `get_option()`, `update_option()`, `delete_option()` - Options API
- `wp_remote_get()`, `wp_remote_retrieve_body()` - HTTP API
- `wp_upload_bits()` - File uploads
- `wp_schedule_event()`, `wp_clear_scheduled_hook()` - Cron API
- `add_action()`, `add_filter()` - Hooks
- `esc_html()`, `esc_attr()`, `esc_url()` - Output escaping
- `sanitize_text_field()` - Input sanitization
- `__()`, `_e()` - Translation

### Error Handling

The plugin implements comprehensive error tracking:

1. **Consecutive failure counter** (`scholar_profile_consecutive_failures`)
2. **Error details** (`scholar_profile_last_error_details`) with error type and timestamp
3. **Admin notices** when 5+ consecutive failures occur
4. **Debug logging** via `wp_scholar_log()` function (requires `WP_DEBUG` and `WP_DEBUG_LOG`)

**Error Types**:
- `blocked_access` - Server IP blocked by Google Scholar
- `profile_not_found` - Invalid profile ID or profile doesn't exist
- `connection_error` - Network issues
- `parse_error` - HTML parsing failed

### Testing Considerations

When working on this plugin:

1. **Test with different profile IDs** - Some profiles may have edge cases
2. **Test pagination** - Ensure proper handling of large publication lists
3. **Test error conditions** - Blocked access, network failures
4. **Test cron scheduling** - Verify automatic updates work correctly
5. **Test on different WordPress versions** - Maintain compatibility with 5.0+
6. **Mobile responsiveness** - Check display on various screen sizes

### Common Tasks

#### Adding a New Display Option

1. Add field to `includes/settings.php` in settings registration
2. Update default options in `wp-google-scholar.php` activation hook
3. Check option in `includes/shortcode.php` render method
4. Update views/settings-page.php template if adding UI

#### Modifying Scraper Logic

1. Edit `includes/scraper.php`
2. Test thoroughly with multiple profiles
3. Update error handling as needed
4. Consider rate limiting to avoid IP blocks

#### Adding Schema Markup

1. Edit `includes/seo.php`
2. Follow [schema.org](https://schema.org) specifications
3. Test with [Google Rich Results Test](https://search.google.com/test/rich-results)

## Important Notes

### Google Scholar Scraping

- **Rate Limiting**: Google may block IPs that make too many requests
- **HTML Structure**: Google Scholar HTML can change, breaking the scraper
- **No Official API**: This plugin relies on web scraping (fragile)
- **Best Practice**: Use conservative update frequencies (weekly/monthly)

### WordPress Hooks

The plugin uses these WordPress hooks:
- `plugins_loaded` - Initialize plugin
- `wp_enqueue_scripts` - Frontend assets
- `admin_enqueue_scripts` - Admin assets
- `admin_menu` - Settings page
- `admin_init` - Settings registration
- `scholar_profile_update` - Cron hook for updates
- `admin_notices` - Error notifications

### Database Storage

All data stored in `wp_options` table:
- `scholar_profile_settings` - Plugin configuration
- `scholar_profile_data` - Cached profile data (serialized array)
- `scholar_profile_last_update` - Timestamp of last successful update
- `scholar_profile_consecutive_failures` - Error counter
- `scholar_profile_last_error_details` - Error information

### Asset Management

- **CSS/JS Versioning**: Uses `WP_SCHOLAR_VERSION` constant for cache busting
- **Image Caching**: Profile avatars stored in WordPress media library
- **Conditional Loading**: Admin assets only load on settings page

## Debugging

### Enable Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

### Common Issues

1. **"Profile not found"**: Invalid profile ID in settings
2. **"Access blocked"**: IP blocked by Google Scholar (use VPN or wait)
3. **Cron not running**: Check WordPress cron with WP-Crontrol plugin
4. **Images not loading**: Check media library upload permissions

## Contributing

When making changes:
1. Test thoroughly with multiple scenarios
2. Update version number in main plugin file
3. Add detailed commit messages
4. Update README.md if adding features
5. Check for security vulnerabilities (XSS, SQL injection, etc.)

## Version History Reference

- **1.3.5**: Current version
- **1.0.0**: Initial release

Check git log for detailed changelog.
