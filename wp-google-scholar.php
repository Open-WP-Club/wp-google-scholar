<?php

/**
 * Plugin Name: Google Scholar Profile Display
 * Plugin URI: https://openwpclub.com/
 * Description: Displays Google Scholar profile information using shortcode [scholar_profile]
 * Version: 1.3.0
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
define('WP_SCHOLAR_VERSION', '1.3.0');
define('WP_SCHOLAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SCHOLAR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
  $prefix = 'WPScholar\\';
  $base_dir = WP_SCHOLAR_PLUGIN_DIR . 'includes/';

  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    return;
  }

  $relative_class = substr($class, $len);
  $file = $base_dir . 'class-' . str_replace('\\', '/', strtolower($relative_class)) . '.php';

  if (file_exists($file)) {
    require $file;
  }
});

// Initialize plugin
function wp_scholar_init()
{
  // Load text domain
  load_plugin_textdomain('scholar-profile', false, dirname(plugin_basename(__FILE__)) . '/languages');

  // Initialize classes
  new WPScholar\Settings();
  new WPScholar\Shortcode();
  new WPScholar\Scheduler();

  // Enqueue styles
  add_action('wp_enqueue_scripts', 'wp_scholar_enqueue_styles');
}
add_action('plugins_loaded', 'wp_scholar_init');

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
add_action('admin_enqueue_scripts', 'wp_scholar_enqueue_admin_styles');

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
register_activation_hook(__FILE__, function () {
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
  } else {
    // Add new setting to existing options if it doesn't exist
    $options = get_option('scholar_profile_settings');
    if (!isset($options['max_publications'])) {
      $options['max_publications'] = '200';
      update_option('scholar_profile_settings', $options);
    }
  }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
  $scheduler = new WPScholar\Scheduler();
  $scheduler->deactivate();
});

// Debug logging function
function wp_scholar_log($message)
{
  if (WP_DEBUG === true && WP_DEBUG_LOG === true) {
    if (is_array($message) || is_object($message)) {
      error_log('[Google Scholar Profile] ' . print_r($message, true));
    } else {
      error_log('[Google Scholar Profile] ' . $message);
    }
  }
}
