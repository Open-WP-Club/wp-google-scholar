<?php

/**
 * Plugin Name: Google Scholar Profile Display
 * Plugin URI: https://openwpclub.com/
 * Description: Displays Google Scholar profile information using shortcode [scholar_profile]
 * Version: 1.4.0
 * Author: OpenWPClub.com
 * Author URI: https://openwpclub.com/
 * License: GPL v2 or later
 * Text Domain: scholar-profile
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('WP_SCHOLAR_VERSION', '1.4.0');
define('WP_SCHOLAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SCHOLAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_SCHOLAR_MAX_CONSECUTIVE_FAILURES', 5);

// Autoload classes
spl_autoload_register('wp_scholar_autoload');

function wp_scholar_autoload($class)
{
  $prefix = 'WPScholar\\';
  $base_dir = WP_SCHOLAR_PLUGIN_DIR . 'includes/';

  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    return;
  }

  $relative_class = substr($class, $len);
  $file = $base_dir . str_replace('\\', '/', strtolower($relative_class)) . '.php';

  if (file_exists($file)) {
    require $file;
  }
}

// Initialize plugin
add_action('plugins_loaded', 'wp_scholar_init');

function wp_scholar_init()
{
  // Load text domain
  load_plugin_textdomain('scholar-profile', false, dirname(plugin_basename(__FILE__)) . '/languages');

  // Initialize classes
  new WPScholar\Settings();
  new WPScholar\Shortcode();
  new WPScholar\Scheduler();
  new WPScholar\SEO(); // Initialize SEO class

  // Enqueue styles
  add_action('wp_enqueue_scripts', 'wp_scholar_enqueue_styles');
}

// Enqueue frontend styles
function wp_scholar_enqueue_styles()
{
  wp_enqueue_style(
    'scholar-profile-styles',
    WP_SCHOLAR_PLUGIN_URL . 'assets/css/style.css',
    array(),
    WP_SCHOLAR_VERSION
  );

  wp_enqueue_script(
    'scholar-profile-sorting',
    WP_SCHOLAR_PLUGIN_URL . 'assets/js/scholar-sorting.js',
    array(),
    WP_SCHOLAR_VERSION,
    true
  );
}

// Enqueue admin styles
add_action('admin_enqueue_scripts', 'wp_scholar_enqueue_admin_styles');

function wp_scholar_enqueue_admin_styles($hook)
{
  // Only load on our settings page
  if ('settings_page_scholar-profile-settings' !== $hook) {
    return;
  }

  wp_enqueue_style(
    'scholar-profile-admin-styles',
    WP_SCHOLAR_PLUGIN_URL . 'assets/css/admin-style.css',
    array(),
    WP_SCHOLAR_VERSION
  );
}

// Register plugin assets directory
function wp_scholar_register_assets()
{
  // Create necessary directories if they don't exist
  $assets_dir = WP_SCHOLAR_PLUGIN_DIR . 'assets';
  $css_dir = $assets_dir . '/css';
  $js_dir = $assets_dir . '/js';

  if (!file_exists($assets_dir)) {
    wp_mkdir_p($assets_dir);
  }
  if (!file_exists($css_dir)) {
    wp_mkdir_p($css_dir);
  }
  if (!file_exists($js_dir)) {
    wp_mkdir_p($js_dir);
  }
}

// Run assets setup on plugin activation
register_activation_hook(__FILE__, 'wp_scholar_register_assets');

// Activation hook
register_activation_hook(__FILE__, 'wp_scholar_activate');

function wp_scholar_activate()
{
  // Log activation for debugging
  wp_scholar_log("Google Scholar Profile plugin activated - Version: " . WP_SCHOLAR_VERSION);

  // Create assets directories
  wp_scholar_register_assets();

  // Activate scheduler
  $scheduler = new WPScholar\Scheduler();
  $scheduler->activate();

  // Set default options if they don't exist
  if (!get_option('scholar_profile_settings')) {
    update_option('scholar_profile_settings', array(
      'profile_id' => '',
      'show_avatar' => '1',
      'show_info' => '1',
      'show_publications' => '1',
      'show_coauthors' => '1',
      'update_frequency' => 'weekly',
      'max_publications' => '200'
    ));
    wp_scholar_log("Default plugin settings created");
  } else {
    // Add new setting to existing options if it doesn't exist
    $options = get_option('scholar_profile_settings');
    $updated = false;

    if (!isset($options['max_publications'])) {
      $options['max_publications'] = '200';
      $updated = true;
    }

    if ($updated) {
      update_option('scholar_profile_settings', $options);
      wp_scholar_log("Plugin settings updated with new options");
    }
  }

  // Clear any stale error details on activation
  delete_option('scholar_profile_last_error_details');
  wp_scholar_log("Cleared any existing error details on activation");
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_scholar_deactivate');

function wp_scholar_deactivate()
{
  wp_scholar_log("Google Scholar Profile plugin deactivated");

  $scheduler = new WPScholar\Scheduler();
  $scheduler->deactivate();

  // Clear any scheduled error notifications
  wp_clear_scheduled_hook('scholar_profile_cleanup_errors');
}

// Uninstall hook (clean up on uninstall)
register_uninstall_hook(__FILE__, 'wp_scholar_uninstall');

function wp_scholar_uninstall()
{
  // Remove all plugin options
  delete_option('scholar_profile_settings');
  delete_option('scholar_profile_data');
  delete_option('scholar_profile_last_update');
  delete_option('scholar_profile_last_manual_refresh');
  delete_option('scholar_profile_data_status');
  delete_option('scholar_profile_consecutive_failures');
  delete_option('scholar_profile_last_error_details');

  // Remove all cached images from media library
  $attachments = get_posts(array(
    'post_type' => 'attachment',
    'meta_key' => '_scholar_profile_id',
    'posts_per_page' => -1,
    'fields' => 'ids'
  ));

  foreach ($attachments as $attachment_id) {
    wp_delete_attachment($attachment_id, true);
  }

  // Clean up any scheduled events
  wp_clear_scheduled_hook('scholar_profile_update');
  wp_clear_scheduled_hook('scholar_profile_cleanup_errors');

  // Log uninstall for debugging purposes
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[Google Scholar Profile] Plugin uninstalled and all data removed');
  }
}

// Enhanced debug logging function
function wp_scholar_log($message, $level = 'info')
{
  if (WP_DEBUG === true && WP_DEBUG_LOG === true) {
    $timestamp = current_time('Y-m-d H:i:s');
    $formatted_message = sprintf(
      '[%s] [Google Scholar Profile] [%s] %s',
      $timestamp,
      strtoupper($level),
      is_string($message) ? $message : print_r($message, true)
    );

    error_log($formatted_message);
  }
}

// Add admin notice for persistent errors (shown to admins only)
add_action('admin_notices', 'wp_scholar_admin_notices');

function wp_scholar_admin_notices()
{
  // Only show to users who can manage options
  if (!current_user_can('manage_options')) {
    return;
  }

  // Check for persistent error conditions
  $error_details = get_option('scholar_profile_last_error_details');
  $consecutive_failures = get_option('scholar_profile_consecutive_failures', 0);
  $options = get_option('scholar_profile_settings');

  // Show notice for persistent failures
  if ($consecutive_failures >= WP_SCHOLAR_MAX_CONSECUTIVE_FAILURES && !empty($options['profile_id'])) {
    $current_screen = get_current_screen();

    // Don't show on the plugin's own settings page (to avoid duplicate notices)
    if ($current_screen && $current_screen->id !== 'settings_page_scholar-profile-settings') {
      $error_type = isset($error_details['type']) ? $error_details['type'] : 'unknown';
      $settings_url = admin_url('options-general.php?page=scholar-profile-settings');

      $notice_message = sprintf(
        __('Google Scholar Profile: %d consecutive update failures detected. ', 'scholar-profile'),
        $consecutive_failures
      );

      // Add specific guidance based on error type
      switch ($error_type) {
        case 'blocked_access':
          $notice_message .= __('Your server IP appears to be blocked by Google Scholar.', 'scholar-profile');
          break;
        case 'profile_not_found':
          $notice_message .= __('The configured profile could not be found.', 'scholar-profile');
          break;
        default:
          $notice_message .= __('Please check your configuration.', 'scholar-profile');
          break;
      }

      $notice_message .= sprintf(
        ' <a href="%s">%s</a>',
        esc_url($settings_url),
        __('View Settings', 'scholar-profile')
      );

      echo '<div class="notice notice-warning"><p>' . wp_kses($notice_message, array(
        'a' => array('href' => array())
      )) . '</p></div>';
    }
  }
}

// Add cleanup scheduled task for error details (optional housekeeping)
add_action('init', 'wp_scholar_init_cleanup_task');

function wp_scholar_init_cleanup_task()
{
  if (!wp_next_scheduled('scholar_profile_cleanup_errors')) {
    wp_schedule_event(time(), 'weekly', 'scholar_profile_cleanup_errors');
  }
}

// Clean up old error details periodically
add_action('scholar_profile_cleanup_errors', 'wp_scholar_cleanup_errors');

function wp_scholar_cleanup_errors()
{
  $error_details = get_option('scholar_profile_last_error_details');
  $consecutive_failures = get_option('scholar_profile_consecutive_failures', 0);

  // If no recent failures and we have old error details, clean them up
  if ($consecutive_failures === 0 && $error_details) {
    delete_option('scholar_profile_last_error_details');
    wp_scholar_log("Cleaned up old error details - no recent failures");
  }
}

// Add helpful links to plugin page
add_filter('plugin_row_meta', 'wp_scholar_plugin_row_meta', 10, 2);

function wp_scholar_plugin_row_meta($links, $file)
{
  if (plugin_basename(__FILE__) === $file) {
    $row_meta = array(
      'docs' => '<a href="https://github.com/Open-WP-Club/wp-google-scholar" target="_blank">' . __('Documentation', 'scholar-profile') . '</a>',
      'support' => '<a href="https://github.com/Open-WP-Club/wp-google-scholar/issues" target="_blank">' . __('Support', 'scholar-profile') . '</a>',
    );
    return array_merge($links, $row_meta);
  }
  return $links;
}
