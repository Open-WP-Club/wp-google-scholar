<?php

/**
 * Plugin Name: Google Scholar Profile Display
 * Plugin URI: https://openwpclub.com
 * Description: Displays Google Scholar profile information using shortcode [scholar_profile]
 * Version: 1.1.0
 * Author: OpenWPClub.com
 * License: GPL v2 or later
 * Text Domain: scholar-profile
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('WP_SCHOLAR_VERSION', '1.1.0');
define('WP_SCHOLAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SCHOLAR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload WPScholar classes
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

// Plugin Class
class WP_Scholar_Plugin
{
  private static $instance = null;

  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    add_action('plugins_loaded', array($this, 'init'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
  }

  public function init()
  {
    // Load text domain
    load_plugin_textdomain('scholar-profile', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize classes
    new WPScholar\Settings();
    new WPScholar\Shortcode();
    new WPScholar\Scheduler();
  }

  public function enqueue_styles()
  {
    wp_enqueue_style(
      'scholar-profile-styles',
      WP_SCHOLAR_PLUGIN_URL . 'assets/css/style.css',
      array(),
      WP_SCHOLAR_VERSION
    );
  }

  public function enqueue_admin_styles($hook)
  {
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

  public static function activate()
  {
    // Create necessary directories
    self::register_assets();

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
        'update_frequency' => 'weekly'
      ));
    }

    // Create upload directory for profile images
    $upload_dir = wp_upload_dir();
    wp_mkdir_p($upload_dir['basedir'] . '/scholar-profiles');
  }

  public static function deactivate()
  {
    $scheduler = new WPScholar\Scheduler();
    $scheduler->deactivate();
  }

  private static function register_assets()
  {
    $assets_dir = WP_SCHOLAR_PLUGIN_DIR . 'assets';
    $css_dir = $assets_dir . '/css';

    if (!file_exists($assets_dir)) {
      wp_mkdir_p($assets_dir);
    }
    if (!file_exists($css_dir)) {
      wp_mkdir_p($css_dir);
    }
  }
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('WP_Scholar_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Scholar_Plugin', 'deactivate'));

// Initialize plugin
add_action('plugins_loaded', array('WP_Scholar_Plugin', 'get_instance'));

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
  $settings_link = sprintf(
    '<a href="%s">%s</a>',
    admin_url('options-general.php?page=scholar-profile-settings'),
    __('Settings', 'scholar-profile')
  );
  array_unshift($links, $settings_link);
  return $links;
});
