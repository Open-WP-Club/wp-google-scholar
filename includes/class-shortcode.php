<?php

namespace WPScholar;

class Shortcode
{
  public function __construct()
  {
    add_shortcode('scholar_profile', array($this, 'render_profile'));
  }

  public function render_profile($atts)
  {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
      'sort_by' => '',
      'sort_order' => 'desc'
    ), $atts, 'scholar_profile');

    $options = get_option('scholar_profile_settings');
    $data = get_option('scholar_profile_data');

    if (!$data) {
      return '<p class="scholar-error">' . __('No profile data available. Please check the plugin settings.', 'scholar-profile') . '</p>';
    }

    // Apply sorting if specified
    if (!empty($atts['sort_by']) && !empty($data['publications'])) {
      $data['publications'] = $this->sort_publications($data['publications'], $atts['sort_by'], $atts['sort_order']);
    }

    ob_start();

    // Main wrapper
    echo '<div class="scholar-profile">';

    // Main content section
    echo '<div class="scholar-main">';
    $this->render_header($data, $options);
    $this->render_publications($data, $options);
    echo '</div>'; // Close main section

    // Sidebar
    echo '<div class="scholar-sidebar">';
    $this->render_metrics($data);
    $this->render_coauthors($data, $options);
    echo '</div>'; // Close sidebar

    echo '</div>'; // Close profile wrapper

    return ob_get_clean();
  }

  protected function render_header($data, $options)
  {
    if (!$options['show_info']) {
      return;
    }

    echo '<div class="scholar-header">';

    // Avatar
    if ($options['show_avatar'] && !empty($data['avatar'])) {
      echo '<div class="scholar-avatar">
                <img src="' . esc_url($data['avatar']) . '" 
                     alt="' . esc_attr($data['name']) . ' profile photo">
              </div>';
    }

    // Basic info
    echo '<div class="scholar-basic-info">
            <h1 class="scholar-name">' . esc_html($data['name']) . '</h1>
            <div class="scholar-affiliation">' . esc_html($data['affiliation']) . '</div>';

    // Research interests
    if (!empty($data['interests'])) {
      echo '<div class="scholar-interests">
              <h3>' . __('Research Interests', 'scholar-profile') . '</h3>
              <div class="scholar-fields">';
      foreach ($data['interests'] as $interest) {
        echo '<span class="scholar-field">' . esc_html($interest) . '</span>';
      }
      echo '</div></div>';
    }

    echo '</div></div>';
  }

  protected function render_publications($data, $options)
  {
    if (!$options['show_publications'] || empty($data['publications'])) {
      return;
    }

    echo '<div class="scholar-publications">
            <h2>' . __('Publications', 'scholar-profile') . '</h2>
            <table class="scholar-publications-table" data-sortable="true">
                <thead>
                    <tr>
                        <th class="publication-title sortable" data-sort="title" tabindex="0" role="button" aria-label="' . __('Sort by title', 'scholar-profile') . '">
                            <span class="sort-label">' . __('Title', 'scholar-profile') . '</span>
                            <span class="sort-arrow" aria-hidden="true"></span>
                        </th>
                        <th class="publication-year sortable" data-sort="year" tabindex="0" role="button" aria-label="' . __('Sort by year', 'scholar-profile') . '">
                            <span class="sort-label">' . __('Year', 'scholar-profile') . '</span>
                            <span class="sort-arrow" aria-hidden="true"></span>
                        </th>
                        <th class="publication-citations sortable" data-sort="citations" tabindex="0" role="button" aria-label="' . __('Sort by citations', 'scholar-profile') . '">
                            <span class="sort-label">' . __('Cited by', 'scholar-profile') . '</span>
                            <span class="sort-arrow" aria-hidden="true"></span>
                        </th>
                    </tr>
                </thead>
                <tbody class="publications-tbody">';

    foreach ($data['publications'] as $pub) {
      echo '<tr>
                <td class="publication-info">
                    <a href="' . esc_url($pub['google_scholar_url']) . '" 
                       class="scholar-publication-title" 
                       target="_blank" rel="noopener noreferrer">'
        . esc_html($pub['title']) . '</a>
                    <div class="scholar-publication-authors">'
        . esc_html($pub['authors']) . '</div>
                    <div class="scholar-publication-venue">'
        . esc_html($pub['venue']) . '</div>
                </td>
                <td class="publication-year" data-year="' . esc_attr($pub['year']) . '">'
        . esc_html($pub['year']) . '</td>
                <td class="publication-citations" data-citations="' . esc_attr($pub['citations']) . '">';

      if ($pub['citations'] > 0) {
        echo '<a href="' . esc_url($pub['citations_url']) . '" 
                     target="_blank" rel="noopener noreferrer">'
          . number_format($pub['citations']) . '</a>';
      } else {
        echo '0';
      }

      echo '</td></tr>';
    }

    echo '</tbody></table></div>';
  }

  protected function render_metrics($data)
  {
    // Check if citations data exists and has non-zero values
    if (
      empty($data['citations']) ||
      ($data['citations']['total'] === 0 &&
        $data['citations']['h_index'] === 0 &&
        $data['citations']['i10_index'] === 0 &&
        $data['citations']['since_2019'] === 0 &&
        $data['citations']['h_index_2019'] === 0 &&
        $data['citations']['i10_index_2019'] === 0)
    ) {
      return;
    }

    echo '<div class="scholar-metrics-box">
            <h2 class="scholar-metrics-title">' . __('Citations', 'scholar-profile') . '</h2>
            <table class="scholar-metrics-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>' . __('All', 'scholar-profile') . '</th>
                        <th>' . __('Since 2019', 'scholar-profile') . '</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . __('Citations', 'scholar-profile') . '</td>
                        <td>' . number_format($data['citations']['total']) . '</td>
                        <td>' . number_format($data['citations']['since_2019']) . '</td>
                    </tr>
                    <tr>
                        <td>h-index</td>
                        <td>' . esc_html($data['citations']['h_index']) . '</td>
                        <td>' . esc_html($data['citations']['h_index_2019']) . '</td>
                    </tr>
                    <tr>
                        <td>i10-index</td>
                        <td>' . esc_html($data['citations']['i10_index']) . '</td>
                        <td>' . esc_html($data['citations']['i10_index_2019']) . '</td>
                    </tr>
                </tbody>
            </table>
          </div>';
  }

  protected function render_coauthors($data, $options)
  {
    if (!$options['show_coauthors'] || empty($data['coauthors'])) {
      return;
    }

    echo '<div class="scholar-coauthors">
            <h2 class="scholar-section-title">' . __('Co-authors', 'scholar-profile') . '</h2>';

    foreach ($data['coauthors'] as $coauthor) {
      echo '<div class="scholar-coauthor">
                <div class="scholar-coauthor-header">';

      if (!empty($coauthor['avatar'])) {
        echo '<img src="' . esc_url($coauthor['avatar']) . '" 
                   alt="' . esc_attr($coauthor['name']) . '" 
                   class="scholar-coauthor-avatar">';
      }

      echo '<div class="scholar-coauthor-main">
                    <a href="' . esc_url($coauthor['profile_url']) . '" 
                       class="scholar-coauthor-name"
                       target="_blank" rel="noopener noreferrer">'
        . esc_html($coauthor['name']) . '</a>';

      if (!empty($coauthor['title'])) {
        echo '<div class="scholar-coauthor-affiliation">'
          . esc_html($coauthor['title']) . '</div>';
      }

      echo '</div></div></div>';
    }

    echo '</div>';
  }

  /**
   * Sort publications by specified field and order
   */
  protected function sort_publications($publications, $sort_by, $sort_order = 'desc')
  {
    if (empty($publications) || !is_array($publications)) {
      return $publications;
    }

    $valid_sorts = array('title', 'year', 'citations');
    if (!in_array($sort_by, $valid_sorts)) {
      return $publications;
    }

    $sort_order = strtolower($sort_order) === 'asc' ? 'asc' : 'desc';

    usort($publications, function ($a, $b) use ($sort_by, $sort_order) {
      $value_a = $a[$sort_by] ?? '';
      $value_b = $b[$sort_by] ?? '';

      switch ($sort_by) {
        case 'year':
          $value_a = intval($value_a);
          $value_b = intval($value_b);
          break;
        case 'citations':
          $value_a = intval($value_a);
          $value_b = intval($value_b);
          break;
        case 'title':
          $value_a = strtolower(trim($value_a));
          $value_b = strtolower(trim($value_b));
          break;
      }

      if ($value_a == $value_b) {
        return 0;
      }

      if ($sort_order === 'asc') {
        return ($value_a < $value_b) ? -1 : 1;
      } else {
        return ($value_a > $value_b) ? -1 : 1;
      }
    });

    return $publications;
  }
}
