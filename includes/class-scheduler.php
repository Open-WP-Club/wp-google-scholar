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
      return;
    }

    wp_scholar_log('Starting scheduled profile update for: ' . $options['profile_id']);

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

      // Log the update with publication count
      wp_scholar_log(sprintf(
        'Google Scholar Profile updated for ID: %s at %s - Found %d publications',
        $options['profile_id'],
        date('Y-m-d H:i:s'),
        count($data['publications'])
      ));
    } else {
      wp_scholar_log('Scheduled update failed for profile: ' . $options['profile_id']);
    }
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
