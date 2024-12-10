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
  <h1><?php _e('Google Scholar Profile Settings', 'scholar-profile'); ?></h1>

  <!-- Quick Start Instructions -->
  <div class="scholar-usage-instructions">
    <h2><?php _e('Quick Start Guide', 'scholar-profile'); ?></h2>
    <ol>
      <li>
        <?php _e('Find your Google Scholar Profile ID:', 'scholar-profile'); ?>
        <ul>
          <li><?php _e('Go to your Google Scholar profile page', 'scholar-profile'); ?></li>
          <li><?php _e('Look at the URL in your browser', 'scholar-profile'); ?></li>
          <li><?php _e('Find the part after "user=" (Example: https://scholar.google.com/citations?user=<strong>XXXYYYZZZ</strong>)', 'scholar-profile'); ?></li>
        </ul>
      </li>
      <li><?php _e('Enter your Profile ID in the settings below', 'scholar-profile'); ?></li>
      <li><?php _e('Customize display options as needed', 'scholar-profile'); ?></li>
      <li><?php _e('Click "Save Settings & Refresh Data"', 'scholar-profile'); ?></li>
      <li>
        <?php _e('Add to your content:', 'scholar-profile'); ?>
        <code>[scholar_profile]</code>
      </li>
    </ol>
  </div>

  <?php settings_errors('scholar_profile_messages'); ?>

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
            class="regular-text"
            placeholder="XXXYYYZZZ">
          <p class="description">
            <?php _e('Your Google Scholar profile ID (found in your profile URL after "user=")', 'scholar-profile'); ?>
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
              <?php _e('Profile Photo', 'scholar-profile'); ?>
            </label><br>

            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_info]"
                value="1" <?php checked('1', $options['show_info']); ?>>
              <?php _e('Profile Information', 'scholar-profile'); ?>
            </label><br>

            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_publications]"
                value="1" <?php checked('1', $options['show_publications']); ?>>
              <?php _e('Publications List', 'scholar-profile'); ?>
            </label><br>

            <label>
              <input type="checkbox"
                name="scholar_profile_settings[show_coauthors]"
                value="1" <?php checked('1', $options['show_coauthors']); ?>>
              <?php _e('Co-authors', 'scholar-profile'); ?>
            </label>
          </fieldset>
        </td>
      </tr>

      <tr>
        <th scope="row"><?php _e('Auto Update', 'scholar-profile'); ?></th>
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
        </td>
      </tr>
    </table>

    <div class="scholar-submit-buttons">
      <?php wp_nonce_field('refresh_profile'); ?>
      <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'scholar-profile'); ?>">
      <input type="submit" name="refresh_profile" class="button button-secondary" value="<?php esc_attr_e('Save Settings & Refresh Data', 'scholar-profile'); ?>">
    </div>
  </form>
</div>