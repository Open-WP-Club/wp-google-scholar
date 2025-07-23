<?php

namespace WPScholar;

class Scheduler
{
  private $hook = 'scholar_profile_update';

  public function __construct()
  {
    add_filter('cron_schedules', array($this, 'add_schedules'));
    add_action($this->hook, array($this, 'update_profile'));
  }

  public function activate()
  {
    if (!wp_next_scheduled($this->hook)) {
      wp_schedule_event(time(), 'weekly', $this->hook);
    }
  }

  public function deactivate()
  {
    wp_clear_scheduled_hook($this->hook);
  }

  public function add_schedules($schedules)
  {
    $options = get_option('scholar_profile_settings');
    $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'weekly';

    $intervals = array(
      'daily' => array(
        'interval' => 86400,
        'display' => __('Daily', 'scholar-profile')
      ),
      'weekly' => array(
        'interval' => 604800,
        'display' => __('Weekly', 'scholar-profile')
      ),
      'monthly' => array(
        'interval' => 2592000,
        'display' => __('Monthly', 'scholar-profile')
      ),
      'yearly' => array(
        'interval' => 31536000,
        'display' => __('Yearly', 'scholar-profile')
      )
    );

    if (isset($intervals[$frequency]) && !isset($schedules[$frequency])) {
      $schedules[$frequency] = $intervals[$frequency];
    }

    return $schedules;
  }

  public function update_profile()
  {
    $options = get_option('scholar_profile_settings');
    if (empty($options['profile_id'])) {
      wp_scholar_log('Scheduled update skipped: No profile ID configured');
      $this->update_data_status('error', 'No profile ID configured');
      return;
    }

    wp_scholar_log('Starting scheduled profile update for: ' . $options['profile_id']);
    $this->update_data_status('updating', 'Fetching data from Google Scholar...');

    $scraper = new Scraper();

    // Configure scraper limits based on settings
    $scraper_config = array(
      'max_publications' => isset($options['max_publications']) ? intval($options['max_publications']) : 200
    );
    $scraper->set_config($scraper_config);

    $data = $scraper->scrape($options['profile_id']);

    if ($data && $this->validate_scraped_data($data)) {
      // Store the new data
      update_option('scholar_profile_data', $data);
      update_option('scholar_profile_last_update', time());

      // Update status to success
      $this->update_data_status('success', sprintf(
        'Successfully updated at %s - Found %d publications',
        date('Y-m-d H:i:s'),
        count($data['publications'])
      ));

      // Clear any previous error status
      delete_option('scholar_profile_consecutive_failures');

      wp_scholar_log(sprintf(
        'Google Scholar Profile updated for ID: %s at %s - Found %d publications',
        $options['profile_id'],
        date('Y-m-d H:i:s'),
        count($data['publications'])
      ));
    } else {
      // Scraping failed - handle gracefully
      $this->handle_scraping_failure($options['profile_id']);
    }
  }

  /**
   * Handle scraping failures with intelligent failsafe
   */
  private function handle_scraping_failure($profile_id)
  {
    $consecutive_failures = get_option('scholar_profile_consecutive_failures', 0);
    $consecutive_failures++;
    update_option('scholar_profile_consecutive_failures', $consecutive_failures);

    $existing_data = get_option('scholar_profile_data');
    $has_existing_data = !empty($existing_data) && !empty($existing_data['name']);

    if ($has_existing_data) {
      // Keep existing data but mark as stale
      $last_update = get_option('scholar_profile_last_update', 0);
      $age_days = $last_update ? ceil((time() - $last_update) / DAY_IN_SECONDS) : 'unknown';

      $error_message = sprintf(
        'Failed to fetch new data (attempt %d). Keeping existing data from %s days ago.',
        $consecutive_failures,
        $age_days
      );

      $this->update_data_status('stale', $error_message);

      wp_scholar_log("Scheduled update failed for profile: $profile_id - keeping existing data (failure #$consecutive_failures)");
    } else {
      // No existing data to fall back to
      $this->update_data_status('error', sprintf(
        'Failed to fetch data (attempt %d). No existing data available.',
        $consecutive_failures
      ));

      wp_scholar_log("Scheduled update failed for profile: $profile_id - no existing data available (failure #$consecutive_failures)");
    }

    // If we've had too many consecutive failures, consider more drastic action
    if ($consecutive_failures >= 5) {
      $this->handle_persistent_failures($profile_id, $consecutive_failures);
    }
  }

  /**
   * Handle persistent scraping failures
   */
  private function handle_persistent_failures($profile_id, $failure_count)
  {
    wp_scholar_log("WARNING: $failure_count consecutive failures for profile: $profile_id");

    // Optionally send email notification to admin
    if ($failure_count === 5) {
      $this->send_failure_notification($profile_id, $failure_count);
    }

    // Consider clearing old data if it's very old and we can't update it
    $last_update = get_option('scholar_profile_last_update', 0);
    $data_age_days = $last_update ? (time() - $last_update) / DAY_IN_SECONDS : 999;

    if ($data_age_days > 90) { // Data older than 90 days
      wp_scholar_log("Considering data too old ($data_age_days days) - marking for manual review");
      $this->update_data_status('error', sprintf(
        'Data is %d days old and cannot be updated after %d attempts. Manual review required.',
        ceil($data_age_days),
        $failure_count
      ));
    }
  }

  /**
   * Send email notification about persistent failures
   */
  private function send_failure_notification($profile_id, $failure_count)
  {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');

    $subject = sprintf('[%s] Google Scholar Profile Update Failures', $site_name);

    $message = sprintf(
      "The Google Scholar Profile plugin has failed to update data %d consecutive times.\n\n" .
        "Profile ID: %s\n" .
        "Last successful update: %s\n" .
        "Site: %s\n\n" .
        "Please check the plugin settings and Google Scholar profile availability.\n" .
        "Admin URL: %s",
      $failure_count,
      $profile_id,
      get_option('scholar_profile_last_update') ?
        date('Y-m-d H:i:s', get_option('scholar_profile_last_update')) : 'Never',
      home_url(),
      admin_url('options-general.php?page=scholar-profile-settings')
    );

    wp_mail($admin_email, $subject, $message);
    wp_scholar_log("Failure notification sent to admin: $admin_email");
  }

  /**
   * Validate scraped data to ensure it's complete and reasonable
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

    // Basic sanity check - if someone has 0 publications, that might be suspicious
    // unless it's a new profile
    if (count($data['publications']) === 0) {
      $existing_data = get_option('scholar_profile_data');
      if ($existing_data && count($existing_data['publications']) > 0) {
        wp_scholar_log("Data validation warning: new data has 0 publications but existing data has publications");
        // This could be a scraping error - let's be cautious
        return false;
      }
    }

    wp_scholar_log("Data validation passed: " . count($data['publications']) . " publications found");
    return true;
  }

  /**
   * Update data status for tracking
   */
  private function update_data_status($status, $message = '')
  {
    $status_data = array(
      'status' => $status, // 'success', 'stale', 'error', 'updating'
      'message' => $message,
      'timestamp' => time(),
      'consecutive_failures' => get_option('scholar_profile_consecutive_failures', 0)
    );

    update_option('scholar_profile_data_status', $status_data);
    wp_scholar_log("Data status updated: $status - $message");
  }

  /**
   * Get current data status
   */
  public function get_data_status()
  {
    return get_option('scholar_profile_data_status', array(
      'status' => 'unknown',
      'message' => 'No status information available',
      'timestamp' => 0,
      'consecutive_failures' => 0
    ));
  }

  /**
   * Check if data is stale based on age and status
   */
  public function is_data_stale()
  {
    $status = $this->get_data_status();
    $last_update = get_option('scholar_profile_last_update', 0);

    // If status is explicitly stale or error
    if (in_array($status['status'], ['stale', 'error'])) {
      return true;
    }

    // If data is older than expected update frequency
    if ($last_update) {
      $options = get_option('scholar_profile_settings');
      $frequency = $options['update_frequency'] ?? 'weekly';

      $max_age = array(
        'daily' => DAY_IN_SECONDS * 2,     // 2 days tolerance
        'weekly' => WEEK_IN_SECONDS * 2,   // 2 weeks tolerance
        'monthly' => MONTH_IN_SECONDS * 2, // 2 months tolerance
        'yearly' => YEAR_IN_SECONDS * 1.5  // 1.5 years tolerance
      );

      $tolerance = $max_age[$frequency] ?? WEEK_IN_SECONDS * 2;
      $age = time() - $last_update;

      return $age > $tolerance;
    }

    return false;
  }

  /**
   * Clear stale data manually
   */
  public function clear_stale_data()
  {
    delete_option('scholar_profile_data');
    delete_option('scholar_profile_last_update');
    delete_option('scholar_profile_data_status');
    delete_option('scholar_profile_consecutive_failures');

    wp_scholar_log("Stale data cleared manually");

    return true;
  }

  public function reschedule()
  {
    $this->deactivate();
    $this->activate();
  }

  public function get_next_scheduled()
  {
    return wp_next_scheduled($this->hook);
  }

  public function force_update()
  {
    return $this->update_profile();
  }
}
