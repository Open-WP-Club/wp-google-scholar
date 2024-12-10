<?php
if (!defined('ABSPATH')) {
  exit;
}

// Ensure $options is available
if (!isset($options)) {
  $options = get_option('scholar_profile_settings', array(
    'profile_id' => '',
    'show_avatar' => '1',
    'show_info' => '1',
    'show_publications' => '1',
    'show_coauthors' => '1',
    'update_frequency' => 'weekly'
  ));
}
?>

<div class="wrap">
  <h2><?php _e('Google Scholar Profile Settings', 'scholar-profile'); ?></h2>
  <?php settings_errors('scholar_profile_messages'); ?>

  <!-- Manual Refresh Form -->
  <form method="post" class="scholar-refresh-form">
    <?php wp_nonce_field('refresh_profile'); ?>
    <input type="submit"
      name="refresh_profile"
      class="button button-secondary"
      value="<?php esc_attr_e('Refresh Profile Data Now', 'scholar-profile'); ?>">

    <?php
    $last_update = get_option('scholar_profile_last_update');
    if ($last_update) {
      echo '<p class="description">' .
        sprintf(
          __('Last updated: %s', 'scholar-profile'),
          date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_update)
        ) .
        '</p>';
    }
    ?>
  </form>

  <!-- Settings Form -->
  <form method="post" action="options.php" class="scholar-settings-form">
    <?php settings_fields('scholar_profile_options'); ?>

    <table class="form-table">
      <tr>
        <th scope="row"><?php _e('Profile ID', 'scholar-profile'); ?></th>
        <td>
          <input type="text"
            name="scholar_profile_settings[profile_id]"
            value="<?php echo esc_attr($options['profile_id']); ?>"
            class="regular-text">
          <p class="description">
            <?php _e('Enter your Google Scholar profile ID (found in profile URL)', 'scholar-profile'); ?>
          </p>
        </td>
      </tr>

      <tr>
        <th scope="row"><?php _e('Display Options', 'scholar-profile'); ?></th>
        <td>
          <fieldset>
            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_avatar]"
                value="1" <?php checked('1', $options['show_avatar']); ?>>
              <?php _e('Show Avatar', 'scholar-profile'); ?>
            </label><br>

            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_info]"
                value="1" <?php checked('1', $options['show_info']); ?>>
              <?php _e('Show Profile Information', 'scholar-profile'); ?>
            </label><br>

            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_publications]"
                value="1" <?php checked('1', $options['show_publications']); ?>>
              <?php _e('Show Publications', 'scholar-profile'); ?>
            </label><br>

            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_coauthors]"
                value="1" <?php checked('1', $options['show_coauthors']); ?>>
              <?php _e('Show Co-authors', 'scholar-profile'); ?>
            </label>
          </fieldset>
        </td>
      </tr>

      <tr>
        <th scope="row"><?php _e('Update Frequency', 'scholar-profile'); ?></th>
        <td>
          <select name="scholar_profile_settings[update_frequency]">
            <option value="daily" <?php selected($options['update_frequency'], 'daily'); ?>>
              <?php _e('Daily', 'scholar-profile'); ?>
            </option>
            <option value="weekly" <?php selected($options['update_frequency'], 'weekly'); ?>>
              <?php _e('Weekly', 'scholar-profile'); ?>
            </option>
            <option value="monthly" <?php selected($options['update_frequency'], 'monthly'); ?>>
              <?php _e('Monthly', 'scholar-profile'); ?>
            </option>
            <option value="yearly" <?php selected($options['update_frequency'], 'yearly'); ?>>
              <?php _e('Yearly', 'scholar-profile'); ?>
            </option>
          </select>
        </td>
      </tr>
    </table>

    <?php submit_button(__('Save Settings', 'scholar-profile')); ?>
  </form>

  <!-- Usage Instructions -->
  <div class="scholar-usage-instructions">
    <h3><?php _e('Usage Instructions', 'scholar-profile'); ?></h3>
    <p><?php _e('Use this shortcode to display your Google Scholar profile:', 'scholar-profile'); ?></p>
    <code>[scholar_profile]</code>
  </div>
</div>