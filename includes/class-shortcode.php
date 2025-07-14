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
      'sort_order' => 'desc',
      'per_page' => 20
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

    // Get current page from URL parameter
    $current_page = max(1, intval($_GET['scholar_page'] ?? 1));
    $per_page = max(1, min(100, intval($atts['per_page']))); // Limit between 1-100

    // Calculate pagination
    $total_publications = count($data['publications']);
    $total_pages = ceil($total_publications / $per_page);
    $current_page = min($current_page, $total_pages); // Ensure current page doesn't exceed total

    // Slice publications for current page
    $offset = ($current_page - 1) * $per_page;
    $paged_publications = array_slice($data['publications'], $offset, $per_page);

    // Store pagination info for JavaScript
    $pagination_data = array(
      'current_page' => $current_page,
      'total_pages' => $total_pages,
      'per_page' => $per_page,
      'total_publications' => $total_publications
    );

    ob_start();

    // Main wrapper with pagination data
    echo '<div class="scholar-profile" data-pagination=\'' . json_encode($pagination_data) . '\'>';

    // Main content section
    echo '<div class="scholar-main">';
    $this->render_header($data, $options);
    $this->render_publications($data, $options, $paged_publications, $current_page, $total_pages, $per_page);
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

  protected function render_publications($data, $options, $paged_publications, $current_page, $total_pages, $per_page)
  {
    if (!$options['show_publications'] || empty($data['publications'])) {
      return;
    }

    $total_publications = count($data['publications']);
    $start_index = ($current_page - 1) * $per_page + 1;
    $end_index = min($current_page * $per_page, $total_publications);

    echo '<div class="scholar-publications">
            <div class="scholar-publications-header">
                <h2>' . __('Publications', 'scholar-profile') . '</h2>';

    // Show publication count and pagination info
    if ($total_pages > 1) {
      echo '<div class="scholar-publications-info">
                <span class="scholar-publications-count">' .
        sprintf(
          __('Showing %d-%d of %d publications', 'scholar-profile'),
          $start_index,
          $end_index,
          $total_publications
        ) . '</span>
            </div>';
    }

    echo '</div>';

    echo '<table class="scholar-publications-table" data-sortable="true">
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

    foreach ($paged_publications as $pub) {
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

    echo '</tbody></table>';

    // Render pagination if needed
    if ($total_pages > 1) {
      $this->render_pagination($current_page, $total_pages);
    }

    echo '</div>';
  }

  protected function render_pagination($current_page, $total_pages)
  {
    $base_url = strtok($_SERVER['REQUEST_URI'], '?');
    $query_params = $_GET;

    echo '<nav class="scholar-pagination" role="navigation" aria-label="' . __('Publications pagination', 'scholar-profile') . '">
            <div class="scholar-pagination-wrapper">';

    // Previous button
    if ($current_page > 1) {
      $query_params['scholar_page'] = $current_page - 1;
      $prev_url = $base_url . '?' . http_build_query($query_params);
      echo '<a href="' . esc_url($prev_url) . '" class="scholar-pagination-btn scholar-pagination-prev" aria-label="' . __('Previous page', 'scholar-profile') . '">
                <span aria-hidden="true">‹</span>
                <span class="scholar-pagination-text">' . __('Previous', 'scholar-profile') . '</span>
            </a>';
    } else {
      echo '<span class="scholar-pagination-btn scholar-pagination-prev disabled" aria-hidden="true">
                <span aria-hidden="true">‹</span>
                <span class="scholar-pagination-text">' . __('Previous', 'scholar-profile') . '</span>
            </span>';
    }

    // Page numbers
    echo '<div class="scholar-pagination-numbers">';

    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);

    // First page + ellipsis if needed
    if ($start_page > 1) {
      $query_params['scholar_page'] = 1;
      $first_url = $base_url . '?' . http_build_query($query_params);
      echo '<a href="' . esc_url($first_url) . '" class="scholar-pagination-number" aria-label="' . __('Go to page 1', 'scholar-profile') . '">1</a>';

      if ($start_page > 2) {
        echo '<span class="scholar-pagination-ellipsis" aria-hidden="true">…</span>';
      }
    }

    // Page numbers around current page
    for ($page = $start_page; $page <= $end_page; $page++) {
      if ($page == $current_page) {
        echo '<span class="scholar-pagination-number current" aria-current="page" aria-label="' . sprintf(__('Page %d, current page', 'scholar-profile'), $page) . '">' . $page . '</span>';
      } else {
        $query_params['scholar_page'] = $page;
        $page_url = $base_url . '?' . http_build_query($query_params);
        echo '<a href="' . esc_url($page_url) . '" class="scholar-pagination-number" aria-label="' . sprintf(__('Go to page %d', 'scholar-profile'), $page) . '">' . $page . '</a>';
      }
    }

    // Last page + ellipsis if needed
    if ($end_page < $total_pages) {
      if ($end_page < $total_pages - 1) {
        echo '<span class="scholar-pagination-ellipsis" aria-hidden="true">…</span>';
      }

      $query_params['scholar_page'] = $total_pages;
      $last_url = $base_url . '?' . http_build_query($query_params);
      echo '<a href="' . esc_url($last_url) . '" class="scholar-pagination-number" aria-label="' . sprintf(__('Go to page %d', 'scholar-profile'), $total_pages) . '">' . $total_pages . '</a>';
    }

    echo '</div>';

    // Next button
    if ($current_page < $total_pages) {
      $query_params['scholar_page'] = $current_page + 1;
      $next_url = $base_url . '?' . http_build_query($query_params);
      echo '<a href="' . esc_url($next_url) . '" class="scholar-pagination-btn scholar-pagination-next" aria-label="' . __('Next page', 'scholar-profile') . '">
                <span class="scholar-pagination-text">' . __('Next', 'scholar-profile') . '</span>
                <span aria-hidden="true">›</span>
            </a>';
    } else {
      echo '<span class="scholar-pagination-btn scholar-pagination-next disabled" aria-hidden="true">
                <span class="scholar-pagination-text">' . __('Next', 'scholar-profile') . '</span>
                <span aria-hidden="true">›</span>
            </span>';
    }

    echo '</div></nav>';
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
