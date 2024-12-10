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
      return;
    }

    $scraper = new Scraper();
    $data = $scraper->scrape($options['profile_id']);

    if ($data) {
      update_option('scholar_profile_data', $data);
      update_option('scholar_profile_last_update', time());

      // Log the update
      error_log(sprintf(
        'Google Scholar Profile updated for ID: %s at %s',
        $options['profile_id'],
        date('Y-m-d H:i:s')
      ));
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
