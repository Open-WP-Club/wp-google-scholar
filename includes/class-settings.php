<?php

namespace WPScholar;

class Settings
{
  private $option_name = 'scholar_profile_settings';
  private $page_slug = 'scholar-profile-settings';

  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_menu_page'));
    add_action('admin_init', array($this, 'register_settings'));
    add_action('admin_init', array($this, 'handle_form_submission'));
    add_action('admin_post_refresh_scholar_profile', array($this, 'handle_manual_refresh'));
    add_filter(
      'plugin_action_links_' . plugin_basename(WP_SCHOLAR_PLUGIN_DIR . 'wp-google-scholar.php'),
      array($this, 'add_settings_link')
    );
  }

  public function add_menu_page()
  {
    add_options_page(
      __('Google Scholar Profile Settings', 'scholar-profile'),
      __('Scholar Profile', 'scholar-profile'),
      'manage_options',
      $this->page_slug,
      array($this, 'render_settings_page')
    );
  }

  public function register_settings()
  {
    // Since we're handling all form processing manually, we don't need WordPress 
    // to register or process the settings. This prevents the double processing
    // that was causing checkbox values to be overridden.

    // WordPress admin pages work fine without register_setting when using custom handlers
  }

  public function handle_form_submission()
  {
    // Check if this is our settings form submission
    if (!isset($_POST['scholar_profile_settings']) || !isset($_POST['scholar_settings_nonce'])) {
      return;
    }

    // Verify user permissions
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['scholar_settings_nonce'], 'scholar_profile_settings')) {
      wp_die(__('Security check failed.'));
    }

    // Sanitize and validate settings
    $input = $_POST['scholar_profile_settings'];
    $validation_errors = array();

    // Validate profile ID format
    if (!empty($input['profile_id'])) {
      $profile_id = sanitize_text_field(trim($input['profile_id']));

      // Check length (Google Scholar IDs are typically 12 characters, but allow some variation)
      if (strlen($profile_id) < 8 || strlen($profile_id) > 20) {
        $validation_errors[] = __('Profile ID should be between 8-20 characters long.', 'scholar-profile');
      }
      // Check format - only allow letters, numbers, underscores, and hyphens
      elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $profile_id)) {
        $validation_errors[] = __('Profile ID can only contain letters, numbers, underscores, and hyphens.', 'scholar-profile');
      }
      // Check for common user mistakes
      elseif (strpos($profile_id, 'user=') !== false) {
        $validation_errors[] = __('Please enter only the Profile ID, not the full URL. Remove "user=" part.', 'scholar-profile');
      } elseif (strpos($profile_id, 'scholar.google.com') !== false) {
        $validation_errors[] = __('Please enter only the Profile ID, not the full URL.', 'scholar-profile');
      }
    }

    // If there are validation errors, redirect back with errors
    if (!empty($validation_errors)) {
      $error_message = implode(' ', $validation_errors);
      wp_redirect(add_query_arg(
        array(
          'page' => $this->page_slug,
          'settings-error' => urlencode($error_message)
        ),
        admin_url('options-general.php')
      ));
      exit;
    }

    // Get current settings to preserve any values not in the form
    $current_settings = get_option($this->option_name, array());

    // Sanitize and save settings
    $sanitized = $this->sanitize_settings($input, $current_settings);
    update_option($this->option_name, $sanitized);

    // Check if scheduler needs to be rescheduled
    $scheduler = new Scheduler();
    $scheduler->reschedule();

    // Redirect back to settings page with success message
    wp_redirect(add_query_arg(
      array('page' => $this->page_slug, 'settings-updated' => 'true'),
      admin_url('options-general.php')
    ));
    exit;
  }

  public function handle_manual_refresh()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Verify nonce
    if (!isset($_POST['scholar_refresh_nonce']) || !wp_verify_nonce($_POST['scholar_refresh_nonce'], 'refresh_scholar_profile')) {
      wp_die(__('Security check failed.'));
    }

    $options = get_option($this->option_name);
    if (empty($options['profile_id'])) {
      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'failed', 'message' => 'no_profile_id'),
        admin_url('options-general.php')
      ));
      exit;
    }

    $scraper = new Scraper();

    // Configure scraper limits based on settings
    $scraper_config = array(
      'max_publications' => isset($options['max_publications']) ? intval($options['max_publications']) : 200
    );
    $scraper->set_config($scraper_config);

    $data = $scraper->scrape($options['profile_id']);

    if ($data) {
      update_option('scholar_profile_data', $data);
      update_option('scholar_profile_last_update', time());

      // Clean redirect with only refresh parameter
      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'success'),
        admin_url('options-general.php')
      ));
    } else {
      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'failed', 'message' => 'scrape_failed'),
        admin_url('options-general.php')
      ));
    }
    exit;
  }

  public function render_settings_page()
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    $options = get_option($this->option_name);
    $messages = array();

    // Debug: Log all URL parameters when DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
      wp_scholar_log('Settings page URL parameters: ' . print_r($_GET, true));
    }

    // Handle settings validation errors
    if (isset($_GET['settings-error'])) {
      $messages[] = array(
        'type' => 'error',
        'message' => '⚠ ' . urldecode($_GET['settings-error'])
      );
    }

    // Handle refresh status messages (check refresh first, before settings-updated)
    if (isset($_GET['refresh'])) {
      if ($_GET['refresh'] === 'success') {
        $messages[] = array(
          'type' => 'updated',
          'message' => __('✓ Profile data refreshed successfully!', 'scholar-profile')
        );
      } elseif ($_GET['refresh'] === 'failed') {
        $message = __('Failed to refresh profile data. Please try again.', 'scholar-profile');

        if (isset($_GET['message'])) {
          switch ($_GET['message']) {
            case 'no_profile_id':
              $message = __('Please enter a Profile ID before refreshing.', 'scholar-profile');
              break;
            case 'scrape_failed':
              $message = __('Could not retrieve data from Google Scholar. Please check your Profile ID and try again.', 'scholar-profile');
              break;
          }
        }

        $messages[] = array(
          'type' => 'error',
          'message' => '⚠ ' . $message
        );
      }
    }
    // Only check for settings-updated if refresh is NOT set
    elseif (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
      $messages[] = array(
        'type' => 'updated',
        'message' => __('✓ Settings saved successfully!', 'scholar-profile')
      );
    }

    include WP_SCHOLAR_PLUGIN_DIR . 'views/settings-page.php';
  }

  public function sanitize_settings($input, $current_settings = array())
  {
    $sanitized = array();

    // Profile ID
    $sanitized['profile_id'] = sanitize_text_field(trim($input['profile_id'] ?? ''));

    // Display Options - Handle checkboxes properly
    // If checkbox is checked, it will be in $input. If unchecked, it won't be present.
    $sanitized['show_avatar'] = isset($input['show_avatar']) ? '1' : '0';
    $sanitized['show_info'] = isset($input['show_info']) ? '1' : '0';
    $sanitized['show_publications'] = isset($input['show_publications']) ? '1' : '0';
    $sanitized['show_coauthors'] = isset($input['show_coauthors']) ? '1' : '0';

    // Update Frequency
    $sanitized['update_frequency'] = sanitize_text_field($input['update_frequency'] ?? 'weekly');

    // Max Publications
    $sanitized['max_publications'] = isset($input['max_publications']) ? intval($input['max_publications']) : 200;

    // Validate update frequency
    $valid_frequencies = array('daily', 'weekly', 'monthly', 'yearly');
    if (!in_array($sanitized['update_frequency'], $valid_frequencies)) {
      $sanitized['update_frequency'] = 'weekly';
    }

    // Validate max publications
    $valid_max_pubs = array(50, 100, 200, 500);
    if (!in_array($sanitized['max_publications'], $valid_max_pubs)) {
      $sanitized['max_publications'] = 200;
    }

    return $sanitized;
  }

  public function add_settings_link($links)
  {
    $settings_link = sprintf(
      '<a href="%s">%s</a>',
      admin_url('options-general.php?page=' . $this->page_slug),
      __('Settings', 'scholar-profile')
    );
    array_unshift($links, $settings_link);
    return $links;
  }
}
