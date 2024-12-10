<?php
/**
 * Plugin Name: Google Scholar Profile Display
 * Description: Displays Google Scholar profile information using shortcode [scholar_profile]
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add settings menu
add_action('admin_menu', 'scholar_profile_menu');
function scholar_profile_menu() {
    add_options_page(
        'Google Scholar Profile Settings',
        'Scholar Profile',
        'manage_options',
        'scholar-profile-settings',
        'scholar_profile_settings_page'
    );
}

// Register settings
add_action('admin_init', 'scholar_profile_register_settings');
function scholar_profile_register_settings() {
    register_setting('scholar_profile_options', 'scholar_profile_settings');
}

// Settings page HTML
function scholar_profile_settings_page() {
    $options = get_option('scholar_profile_settings', array(
        'profile_id' => '',
        'show_avatar' => '1',
        'show_info' => '1',
        'show_publications' => '1',
        'update_frequency' => 'weekly'
    ));
    ?>
    <div class="wrap">
        <h2>Google Scholar Profile Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('scholar_profile_options'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Profile ID</th>
                    <td>
                        <input type="text" name="scholar_profile_settings[profile_id]" 
                               value="<?php echo esc_attr($options['profile_id']); ?>" class="regular-text">
                        <p class="description">Enter your Google Scholar profile ID (found in profile URL)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Display Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="scholar_profile_settings[show_avatar]" 
                                   value="1" <?php checked('1', $options['show_avatar']); ?>>
                            Show Avatar
                        </label><br>
                        <label>
                            <input type="checkbox" name="scholar_profile_settings[show_info]" 
                                   value="1" <?php checked('1', $options['show_info']); ?>>
                            Show Profile Information
                        </label><br>
                        <label>
                            <input type="checkbox" name="scholar_profile_settings[show_publications]" 
                                   value="1" <?php checked('1', $options['show_publications']); ?>>
                            Show Publications
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Update Frequency</th>
                    <td>
                        <select name="scholar_profile_settings[update_frequency]">
                            <option value="daily" <?php selected($options['update_frequency'], 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($options['update_frequency'], 'weekly'); ?>>Weekly</option>
                            <option value="monthly" <?php selected($options['update_frequency'], 'monthly'); ?>>Monthly</option>
                            <option value="yearly" <?php selected($options['update_frequency'], 'yearly'); ?>>Yearly</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Schedule profile updates
register_activation_hook(__FILE__, 'scholar_profile_activation');
function scholar_profile_activation() {
    if (!wp_next_scheduled('scholar_profile_update')) {
        wp_schedule_event(time(), 'weekly', 'scholar_profile_update');
    }
}

register_deactivation_hook(__FILE__, 'scholar_profile_deactivation');
function scholar_profile_deactivation() {
    wp_clear_scheduled_hook('scholar_profile_update');
}

// Update frequency handler
add_filter('cron_schedules', 'scholar_profile_cron_schedules');
function scholar_profile_cron_schedules($schedules) {
    $options = get_option('scholar_profile_settings');
    $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'weekly';
    
    $intervals = array(
        'daily' => 86400,
        'weekly' => 604800,
        'monthly' => 2592000,
        'yearly' => 31536000
    );
    
    if (!isset($schedules[$frequency])) {
        $schedules[$frequency] = array(
            'interval' => $intervals[$frequency],
            'display' => ucfirst($frequency)
        );
    }
    
    return $schedules;
}

// Scraping function
function scholar_profile_scrape($profile_id) {
    $url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en";
    
    $args = array(
        'timeout' => 30,
        'headers' => array(
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        )
    );
    
    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $html = wp_remote_retrieve_body($response);
    
    // Using DOMDocument for parsing
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    
    $data = array(
        'avatar' => '',
        'name' => '',
        'affiliation' => '',
        'interests' => array(),
        'publications' => array()
    );
    
    // Get profile image
    $avatar_node = $xpath->query("//img[@id='gsc_prf_pup-img']")->item(0);
    if ($avatar_node) {
        $data['avatar'] = $avatar_node->getAttribute('src');
    }
    
    // Get name
    $name_node = $xpath->query("//div[@id='gsc_prf_in']")->item(0);
    if ($name_node) {
        $data['name'] = trim($name_node->textContent);
    }
    
    // Get affiliation
    $affiliation_node = $xpath->query("//div[@class='gsc_prf_il']")->item(0);
    if ($affiliation_node) {
        $data['affiliation'] = trim($affiliation_node->textContent);
    }
    
    // Get publications
    $publications = $xpath->query("//tr[@class='gsc_a_tr']");
    foreach ($publications as $pub) {
        $title_node = $xpath->query(".//a[@class='gsc_a_at']", $pub)->item(0);
        $authors_node = $xpath->query(".//div[@class='gs_gray']", $pub)->item(0);
        $year_node = $xpath->query(".//span[@class='gsc_a_h gsc_a_hc gs_ibl']", $pub)->item(0);
        
        if ($title_node) {
            $data['publications'][] = array(
                'title' => trim($title_node->textContent),
                'authors' => $authors_node ? trim($authors_node->textContent) : '',
                'year' => $year_node ? trim($year_node->textContent) : '',
                'link' => $title_node->getAttribute('href')
            );
        }
    }
    
    return $data;
}

// Scheduled update hook
add_action('scholar_profile_update', 'scholar_profile_do_update');
function scholar_profile_do_update() {
    $options = get_option('scholar_profile_settings');
    if (empty($options['profile_id'])) {
        return;
    }
    
    $data = scholar_profile_scrape($options['profile_id']);
    if ($data) {
        update_option('scholar_profile_data', $data);
    }
}

// Shortcode function
add_shortcode('scholar_profile', 'scholar_profile_shortcode');
function scholar_profile_shortcode($atts) {
    $options = get_option('scholar_profile_settings');
    $data = get_option('scholar_profile_data');
    
    if (!$data) {
        return 'No profile data available.';
    }
    
    $output = '<div class="scholar-profile">';
    
    // Avatar
    if ($options['show_avatar'] && !empty($data['avatar'])) {
        $output .= '<div class="scholar-avatar">
                       <img src="' . esc_url($data['avatar']) . '" alt="' . esc_attr($data['name']) . '">
                   </div>';
    }
    
    // Profile information
    if ($options['show_info']) {
        $output .= '<div class="scholar-info">
                       <h2>' . esc_html($data['name']) . '</h2>
                       <p>' . esc_html($data['affiliation']) . '</p>
                   </div>';
    }
    
    // Publications
    if ($options['show_publications'] && !empty($data['publications'])) {
        $output .= '<div class="scholar-publications">
                       <h3>Publications</h3>
                       <ul>';
        
        foreach ($data['publications'] as $pub) {
            $output .= '<li>
                           <strong>' . esc_html($pub['title']) . '</strong><br>
                           ' . esc_html($pub['authors']) . '<br>
                           ' . esc_html($pub['year']) . '
                       </li>';
        }
        
        $output .= '</ul></div>';
    }
    
    $output .= '</div>';
    
    return $output;
}

// Add some basic styles
add_action('wp_head', 'scholar_profile_styles');
function scholar_profile_styles() {
    ?>
    <style>
        .scholar-profile {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .scholar-avatar img {
            max-width: 150px;
            height: auto;
            border-radius: 50%;
        }
        .scholar-info {
            margin: 20px 0;
        }
        .scholar-publications ul {
            list-style: none;
            padding: 0;
        }
        .scholar-publications li {
            margin-bottom: 15px;
        }
    </style>
    <?php
}