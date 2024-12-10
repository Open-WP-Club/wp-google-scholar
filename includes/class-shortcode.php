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
    $options = get_option('scholar_profile_settings');
    $data = get_option('scholar_profile_data');

    if (!$data) {
      return '<p class="scholar-error">' . __('No profile data available. Please check the plugin settings.', 'scholar-profile') . '</p>';
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
            <h2>' . __('Publications', 'scholar-profile') . '</h2>';

    foreach ($data['publications'] as $pub) {
      echo '<div class="scholar-publication">
                <h3><a href="' . esc_url($pub['google_scholar_url']) . '" 
                   class="scholar-publication-title" 
                   target="_blank" rel="noopener noreferrer">'
        . esc_html($pub['title']) . '</a></h3>
                <div class="scholar-publication-authors">'
        . esc_html($pub['authors']) . '</div>
                <div class="scholar-publication-meta">
                    <span class="scholar-publication-venue">'
        . esc_html($pub['venue'])
        . ($pub['year'] ? ', ' . esc_html($pub['year']) : '')
        . '</span>';

      if ($pub['citations'] > 0) {
        echo '<span class="scholar-citation-count">
                        <a href="' . esc_url($pub['citations_url']) . '" 
                           target="_blank" rel="noopener noreferrer">
                           ' . sprintf(
          _n('Cited by %s', 'Cited by %s', $pub['citations'], 'scholar-profile'),
          number_format($pub['citations'])
        ) . '
                        </a>
                    </span>';
      }

      echo '</div></div>';
    }

    echo '</div>';
  }

  protected function render_metrics($data)
  {
    if (empty($data['citations'])) {
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

      if (!empty($coauthor['affiliation'])) {
        echo '<div class="scholar-coauthor-affiliation">'
          . esc_html($coauthor['affiliation']) . '</div>';
      }

      echo '</div>'; // Close scholar-coauthor-main

      if (!empty($coauthor['cited_by'])) {
        echo '<div class="scholar-coauthor-citations">'
          . sprintf(
            _n('Cited by %s', 'Cited by %s', $coauthor['cited_by'], 'scholar-profile'),
            number_format($coauthor['cited_by'])
          )
          . '</div>';
      }

      echo '</div>'; // Close scholar-coauthor-header

      // Render interests if available
      if (!empty($coauthor['interests'])) {
        echo '<div class="scholar-coauthor-interests">';
        foreach ($coauthor['interests'] as $interest) {
          echo '<span class="scholar-coauthor-interest">'
            . esc_html($interest) . '</span>';
        }
        echo '</div>';
      }

      echo '</div>'; // Close scholar-coauthor
    }

    echo '</div>';
  }
}
