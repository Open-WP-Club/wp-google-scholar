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
    add_action('admin_post_clear_stale_data', array($this, 'handle_clear_stale_data'));
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

    // Rate limiting: Prevent refreshes more than once every 5 minutes
    $last_manual_refresh = get_option('scholar_profile_last_manual_refresh', 0);
    $cooldown_period = 5 * 60; // 5 minutes in seconds
    $time_since_last = time() - $last_manual_refresh;

    if ($time_since_last < $cooldown_period) {
      $minutes_remaining = ceil(($cooldown_period - $time_since_last) / 60);
      wp_redirect(add_query_arg(
        array(
          'page' => $this->page_slug,
          'refresh' => 'failed',
          'message' => 'rate_limited',
          'minutes' => $minutes_remaining
        ),
        admin_url('options-general.php')
      ));
      exit;
    }

    // Update the last manual refresh timestamp
    update_option('scholar_profile_last_manual_refresh', time());

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

    // Update status to indicate we're starting a manual refresh
    $scheduler = new Scheduler();
    $scheduler_reflection = new \ReflectionClass($scheduler);
    $update_status_method = $scheduler_reflection->getMethod('update_data_status');
    $update_status_method->setAccessible(true);
    $update_status_method->invoke($scheduler, 'updating', 'Manual refresh in progress...');

    wp_scholar_log("Starting manual refresh for profile: " . $options['profile_id']);

    $scraper = new Scraper();

    // Configure scraper limits based on settings
    $scraper_config = array(
      'max_publications' => isset($options['max_publications']) ? intval($options['max_publications']) : 200
    );
    $scraper->set_config($scraper_config);

    $data = $scraper->scrape($options['profile_id']);

    if ($data && $this->validate_scraped_data($data)) {
      update_option('scholar_profile_data', $data);
      update_option('scholar_profile_last_update', time());

      // Reset consecutive failures counter
      delete_option('scholar_profile_consecutive_failures');

      // Update status to success
      $update_status_method->invoke($scheduler, 'success', sprintf(
        'Manual refresh successful at %s - Found %d publications',
        date('Y-m-d H:i:s'),
        count($data['publications'])
      ));

      wp_scholar_log("Manual refresh successful for profile: " . $options['profile_id']);

      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'success'),
        admin_url('options-general.php')
      ));
    } else {
      // Manual refresh failed
      $consecutive_failures = get_option('scholar_profile_consecutive_failures', 0) + 1;
      update_option('scholar_profile_consecutive_failures', $consecutive_failures);

      $existing_data = get_option('scholar_profile_data');
      $has_existing_data = !empty($existing_data) && !empty($existing_data['name']);

      if ($has_existing_data) {
        $last_update = get_option('scholar_profile_last_update', 0);
        $age_days = $last_update ? ceil((time() - $last_update) / DAY_IN_SECONDS) : 'unknown';

        $update_status_method->invoke($scheduler, 'stale', sprintf(
          'Manual refresh failed. Keeping existing data from %s days ago.',
          $age_days
        ));
      } else {
        $update_status_method->invoke($scheduler, 'error', 'Manual refresh failed and no existing data available.');
      }

      wp_scholar_log("Manual refresh failed for profile: " . $options['profile_id']);

      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'failed', 'message' => 'scrape_failed'),
        admin_url('options-general.php')
      ));
    }
    exit;
  }

  /**
   * Handle clearing stale data
   */
  public function handle_clear_stale_data()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Verify nonce
    if (!isset($_POST['scholar_clear_nonce']) || !wp_verify_nonce($_POST['scholar_clear_nonce'], 'clear_stale_data')) {
      wp_die(__('Security check failed.'));
    }

    $scheduler = new Scheduler();
    $scheduler->clear_stale_data();

    wp_redirect(add_query_arg(
      array('page' => $this->page_slug, 'clear' => 'success'),
      admin_url('options-general.php')
    ));
    exit;
  }

  /**
   * Validate scraped data (same as in Scheduler)
   */
  private function validate_scraped_data($data)
  {
    if (!is_array($data)) {
      wp_scholar_log("Data validation failed: not an array");
      return false;
    }

    // Check for required fields
    $required_fields = ['name', 'publications'];
    foreach ($required_fields as $field) {
      if (!isset($data[$field]) || empty($data[$field])) {
        wp_scholar_log("Data validation failed: missing required field '$field'");
        return false;
      }
    }

    // Check if publications array is reasonable
    if (!is_array($data['publications'])) {
      wp_scholar_log("Data validation failed: publications is not an array");
      return false;
    }

    // Basic sanity check for empty publications
    if (count($data['publications']) === 0) {
      $existing_data = get_option('scholar_profile_data');
      if ($existing_data && count($existing_data['publications']) > 0) {
        wp_scholar_log("Data validation warning: new data has 0 publications but existing data has publications");
        return false;
      }
    }

    wp_scholar_log("Data validation passed: " . count($data['publications']) . " publications found");
    return true;
  }

  public function render_settings_page()
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    $options = get_option($this->option_name);
    $scheduler = new Scheduler();
    $data_status = $scheduler->get_data_status();
    $is_data_stale = $scheduler->is_data_stale();
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

    // Handle clear stale data status
    if (isset($_GET['clear'])) {
      if ($_GET['clear'] === 'success') {
        $messages[] = array(
          'type' => 'updated',
          'message' => __('✓ Stale data cleared successfully!', 'scholar-profile')
        );
      }
    }
    // Handle refresh status messages (check refresh first, before settings-updated)
    elseif (isset($_GET['refresh'])) {
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
              $existing_data = get_option('scholar_profile_data');
              if (!empty($existing_data)) {
                $message = __('Could not retrieve new data from Google Scholar, but existing data is preserved. Please check your Profile ID and try again later.', 'scholar-profile');
              } else {
                $message = __('Could not retrieve data from Google Scholar. Please check your Profile ID and try again.', 'scholar-profile');
              }
              break;
            case 'rate_limited':
              $minutes = isset($_GET['minutes']) ? intval($_GET['minutes']) : 5;
              $message = sprintf(
                __('Please wait %d more minute(s) before refreshing again. This prevents rate limiting from Google Scholar.', 'scholar-profile'),
                $minutes
              );
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

    // Add data status warning if needed
    if ($is_data_stale && !empty(get_option('scholar_profile_data'))) {
      $status_message = $data_status['message'] ?: 'Data may be outdated';
      $messages[] = array(
        'type' => 'warning',
        'message' => '⚠ ' . __('Data Status Warning: ', 'scholar-profile') . $status_message
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
