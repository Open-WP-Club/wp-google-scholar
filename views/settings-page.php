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
    'update_frequency' => 'weekly',
    'max_publications' => '200'
  ));
}

// Get profile data and last update info
$profile_data = get_option('scholar_profile_data');
$last_update = get_option('scholar_profile_last_update');
$has_profile_data = !empty($profile_data) && !empty($profile_data['name']);
?>

<div class="wrap">
  <h1><?php _e('Google Scholar Profile', 'scholar-profile'); ?></h1>

  <?php if (!empty($messages)): ?>
    <?php foreach ($messages as $message): ?>
      <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
        <p><?php echo esc_html($message['message']); ?></p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="scholar-admin-container">

    <!-- Main Layout: Side by Side -->
    <div class="scholar-main-layout">

      <!-- LEFT: Profile Configuration (70%) -->
      <div class="scholar-main-content">
        <div class="scholar-settings-card">
          <form method="post" action="">
            <?php wp_nonce_field('scholar_profile_settings', 'scholar_settings_nonce'); ?>

            <h2><?php _e('Profile Configuration', 'scholar-profile'); ?></h2>

            <table class="form-table" role="presentation">
              <tr>
                <th scope="row">
                  <label for="profile_id"><?php _e('Profile ID', 'scholar-profile'); ?></label>
                </th>
                <td>
                  <input type="text"
                    id="profile_id"
                    name="scholar_profile_settings[profile_id]"
                    value="<?php echo esc_attr($options['profile_id']); ?>"
                    class="regular-text"
                    placeholder="e.g., XXXXXXXXXX">
                  <p class="description">
                    <?php _e('Your Google Scholar profile ID from the URL: https://scholar.google.com/citations?user=<strong>PROFILE_ID</strong>', 'scholar-profile'); ?>
                  </p>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php _e('Display Options', 'scholar-profile'); ?></th>
                <td>
                  <fieldset>
                    <legend class="screen-reader-text"><?php _e('Display Options', 'scholar-profile'); ?></legend>

                    <label class="scholar-checkbox-label">
                      <input type="checkbox"
                        name="scholar_profile_settings[show_avatar]"
                        value="1" <?php checked('1', $options['show_avatar']); ?>>
                      <span class="scholar-checkbox-text"><?php _e('Show profile avatar', 'scholar-profile'); ?></span>
                    </label>

                    <label class="scholar-checkbox-label">
                      <input type="checkbox"
                        name="scholar_profile_settings[show_info]"
                        value="1" <?php checked('1', $options['show_info']); ?>>
                      <span class="scholar-checkbox-text"><?php _e('Show profile information', 'scholar-profile'); ?></span>
                    </label>

                    <label class="scholar-checkbox-label">
                      <input type="checkbox"
                        name="scholar_profile_settings[show_publications]"
                        value="1" <?php checked('1', $options['show_publications']); ?>>
                      <span class="scholar-checkbox-text"><?php _e('Show publications list', 'scholar-profile'); ?></span>
                    </label>

                    <label class="scholar-checkbox-label">
                      <input type="checkbox"
                        name="scholar_profile_settings[show_coauthors]"
                        value="1" <?php checked('1', $options['show_coauthors']); ?>>
                      <span class="scholar-checkbox-text"><?php _e('Show co-authors', 'scholar-profile'); ?></span>
                    </label>
                  </fieldset>
                </td>
              </tr>

              <tr>
                <th scope="row">
                  <label for="update_frequency"><?php _e('Update Frequency', 'scholar-profile'); ?></label>
                </th>
                <td>
                  <select id="update_frequency" name="scholar_profile_settings[update_frequency]">
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
                  <p class="description">
                    <?php _e('How often to automatically refresh profile data from Google Scholar.', 'scholar-profile'); ?>
                  </p>
                </td>
              </tr>

              <tr>
                <th scope="row">
                  <label for="max_publications"><?php _e('Max Publications', 'scholar-profile'); ?></label>
                </th>
                <td>
                  <select id="max_publications" name="scholar_profile_settings[max_publications]">
                    <option value="50" <?php selected($options['max_publications'] ?? '200', '50'); ?>>
                      <?php _e('50 publications', 'scholar-profile'); ?>
                    </option>
                    <option value="100" <?php selected($options['max_publications'] ?? '200', '100'); ?>>
                      <?php _e('100 publications', 'scholar-profile'); ?>
                    </option>
                    <option value="200" <?php selected($options['max_publications'] ?? '200', '200'); ?>>
                      <?php _e('200 publications (recommended)', 'scholar-profile'); ?>
                    </option>
                    <option value="500" <?php selected($options['max_publications'] ?? '200', '500'); ?>>
                      <?php _e('500 publications', 'scholar-profile'); ?>
                    </option>
                  </select>
                  <p class="description">
                    <?php _e('Maximum number of publications to fetch from Google Scholar. Higher numbers take longer to process.', 'scholar-profile'); ?>
                    <br><strong style="color: #d63638;"><?php _e('⚠️ Warning:', 'scholar-profile'); ?></strong>
                    <?php _e('Fetching large numbers of publications (500+) may temporarily trigger IP rate limiting from Google Scholar. Use higher limits sparingly and consider longer update intervals.', 'scholar-profile'); ?>
                  </p>
                </td>
              </tr>
            </table>

            <div class="scholar-form-actions">
              <?php submit_button(__('Save Settings', 'scholar-profile'), 'primary', 'submit', false); ?>
            </div>
          </form>

          <!-- Separate Refresh Form -->
          <div class="scholar-refresh-section">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
              <input type="hidden" name="action" value="refresh_scholar_profile">
              <?php wp_nonce_field('refresh_scholar_profile', 'scholar_refresh_nonce'); ?>
              <input type="submit"
                name="refresh_profile"
                class="button button-secondary"
                value="<?php esc_attr_e('Refresh Profile Data', 'scholar-profile'); ?>">
              <p class="description" style="margin-top: 8px;">
                <?php _e('Manually refresh data from Google Scholar. Large profiles may take several minutes to process.', 'scholar-profile'); ?>
              </p>
            </form>
          </div>
        </div>
      </div>

      <!-- RIGHT: Profile Info + Usage (30%) -->
      <div class="scholar-sidebar-content">

        <!-- Profile Status Card -->
        <?php if ($has_profile_data): ?>
          <div class="scholar-status-card scholar-status-active">
            <div class="scholar-status-header">
              <div class="scholar-status-info">
                <h3><?php echo esc_html($profile_data['name']); ?></h3>
                <p><?php echo esc_html($profile_data['affiliation']); ?></p>
              </div>
              <?php if (!empty($profile_data['avatar'])): ?>
                <img src="<?php echo esc_url($profile_data['avatar']); ?>"
                  alt="<?php echo esc_attr($profile_data['name']); ?>"
                  class="scholar-status-avatar">
              <?php endif; ?>
            </div>

            <div class="scholar-status-stats">
              <div class="scholar-stat">
                <span class="scholar-stat-number"><?php echo number_format($profile_data['citations']['total']); ?></span>
                <span class="scholar-stat-label"><?php _e('Citations', 'scholar-profile'); ?></span>
              </div>
              <div class="scholar-stat">
                <span class="scholar-stat-number"><?php echo count($profile_data['publications']); ?></span>
                <span class="scholar-stat-label"><?php _e('Publications', 'scholar-profile'); ?></span>
              </div>
              <div class="scholar-stat">
                <span class="scholar-stat-number"><?php echo esc_html($profile_data['citations']['h_index']); ?></span>
                <span class="scholar-stat-label">h-index</span>
              </div>
              <div class="scholar-stat">
                <span class="scholar-stat-number"><?php echo count($profile_data['coauthors']); ?></span>
                <span class="scholar-stat-label"><?php _e('Co-authors', 'scholar-profile'); ?></span>
              </div>
            </div>

            <?php if ($last_update): ?>
              <div class="scholar-status-footer">
                <span class="scholar-last-update">
                  <?php printf(
                    __('Last updated: %s', 'scholar-profile'),
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_update)
                  ); ?>
                </span>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="scholar-status-card scholar-status-empty">
            <div class="scholar-status-empty-content">
              <span class="dashicons dashicons-admin-users"></span>
              <h3><?php _e('No profile data', 'scholar-profile'); ?></h3>
              <p><?php _e('Configure your profile ID and refresh to get started.', 'scholar-profile'); ?></p>
            </div>
          </div>
        <?php endif; ?>

        <!-- Usage Instructions -->
        <div class="scholar-usage-card">
          <h3><?php _e('Basic Usage', 'scholar-profile'); ?></h3>
          <p><?php _e('Add to any post or page:', 'scholar-profile'); ?></p>
          <div class="scholar-shortcode">
            <code>[scholar_profile]</code>
            <button type="button" class="scholar-copy-btn" onclick="navigator.clipboard.writeText('[scholar_profile]')" title="<?php esc_attr_e('Copy shortcode', 'scholar-profile'); ?>">
              <span class="dashicons dashicons-admin-page"></span>
            </button>
          </div>

          <h4 style="margin: 20px 0 10px 0; font-size: 14px; font-weight: 600;"><?php _e('📊 Sorting Options', 'scholar-profile'); ?></h4>
          <p style="margin-bottom: 12px; font-size: 13px; color: #646970;">
            <?php _e('Sort publications by year, citations, or title:', 'scholar-profile'); ?>
          </p>

          <div style="background: #f6f7f7; padding: 12px; border-radius: 3px; border: 1px solid #dcdcde; margin-bottom: 8px;">
            <code style="display: block; font-size: 12px; line-height: 1.4; margin: 2px 0;">
              [scholar_profile sort_by="year" sort_order="desc"]<br>
              [scholar_profile sort_by="citations" sort_order="desc"]<br>
              [scholar_profile sort_by="title" sort_order="asc"]
            </code>
          </div>

          <p style="margin: 8px 0; font-size: 12px; color: #646970;">
            <?php _e('💡 <strong>Interactive Sorting:</strong> Readers can also click column headers to sort the table dynamically.', 'scholar-profile'); ?>
          </p>
        </div>

        <!-- Rate Limiting Notice -->
        <div class="scholar-usage-card" style="border-left: 4px solid #d63638;">
          <h3 style="color: #d63638; display: flex; align-items: center; gap: 8px;">
            <span class="dashicons dashicons-warning" style="font-size: 16px;"></span>
            <?php _e('Important Notice', 'scholar-profile'); ?>
          </h3>
          <p style="font-size: 13px; line-height: 1.4; margin-bottom: 12px;">
            <strong><?php _e('Google Scholar Rate Limiting:', 'scholar-profile'); ?></strong>
            <?php _e('Google Scholar may temporarily block your IP address if you request too much data too frequently. This is especially likely when:', 'scholar-profile'); ?>
          </p>
          <ul style="margin: 8px 0; padding-left: 20px; font-size: 12px; color: #646970;">
            <li><?php _e('Fetching 500+ publications', 'scholar-profile'); ?></li>
            <li><?php _e('Refreshing data multiple times per day', 'scholar-profile'); ?></li>
            <li><?php _e('Using daily update frequency with large profiles', 'scholar-profile'); ?></li>
          </ul>
          <p style="font-size: 12px; color: #646970; margin-top: 12px;">
            <strong><?php _e('Recommendation:', 'scholar-profile'); ?></strong>
            <?php _e('Use weekly or monthly updates for large profiles. If temporarily blocked, wait 24-48 hours before trying again.', 'scholar-profile'); ?>
          </p>
        </div>

      </div>
    </div>

  </div>
</div>