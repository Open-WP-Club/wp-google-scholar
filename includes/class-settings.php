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
    register_setting(
      'scholar_profile_options',
      $this->option_name,
      array(
        'sanitize_callback' => array($this, 'sanitize_settings')
      )
    );
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
    $data = $scraper->scrape($options['profile_id']);

    if ($data) {
      update_option('scholar_profile_data', $data);
      update_option('scholar_profile_last_update', time());
      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'success'),
        admin_url('options-general.php')
      ));
    } else {
      wp_redirect(add_query_arg(
        array('page' => $this->page_slug, 'refresh' => 'failed'),
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

    // Handle refresh status messages
    if (isset($_GET['refresh'])) {
      if ($_GET['refresh'] === 'success') {
        add_settings_error(
          'scholar_profile_messages',
          'profile_updated',
          __('Profile data refreshed successfully!', 'scholar-profile'),
          'updated'
        );
      } elseif ($_GET['refresh'] === 'failed') {
        $message = isset($_GET['message']) && $_GET['message'] === 'no_profile_id'
          ? __('Please set a Profile ID before refreshing.', 'scholar-profile')
          : __('Failed to refresh profile data. Please check your Profile ID and try again.', 'scholar-profile');

        add_settings_error(
          'scholar_profile_messages',
          'profile_update_failed',
          $message,
          'error'
        );
      }
    }

    include WP_SCHOLAR_PLUGIN_DIR . 'views/settings-page.php';
  }

  public function sanitize_settings($input)
  {
    $sanitized = array();
    $sanitized['profile_id'] = sanitize_text_field($input['profile_id']);
    $sanitized['show_avatar'] = isset($input['show_avatar']) ? '1' : '0';
    $sanitized['show_info'] = isset($input['show_info']) ? '1' : '0';
    $sanitized['show_publications'] = isset($input['show_publications']) ? '1' : '0';
    $sanitized['show_coauthors'] = isset($input['show_coauthors']) ? '1' : '0';
    $sanitized['update_frequency'] = sanitize_text_field($input['update_frequency']);

    // Add settings error if profile ID is empty
    if (empty($sanitized['profile_id'])) {
      add_settings_error(
        'scholar_profile_messages',
        'profile_id_missing',
        __('Profile ID is required.', 'scholar-profile'),
        'error'
      );
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
