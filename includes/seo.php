<?php

namespace WPScholar;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class SEO
{
  private $has_output = false; // Prevent duplicate output

  public function __construct()
  {
    add_action('wp_head', array($this, 'output_scholar_tags'), 15); // After SEO plugins
  }

  /**
   * Add SEO enhancements for scholar profile
   */
  public function add_profile_seo($data, $options)
  {
    if (empty($data) || !is_array($data)) {
      return;
    }

    // Store scholar data globally for output
    global $scholar_profile_seo_data;
    $scholar_profile_seo_data = $data;
  }

  /**
   * Output only Scholar-specific SEO tags
   */
  public function output_scholar_tags()
  {
    global $scholar_profile_seo_data;

    if (empty($scholar_profile_seo_data) || $this->has_output) {
      return;
    }

    $data = $scholar_profile_seo_data;
    $this->has_output = true;

    echo "\n<!-- Scholar Profile Academic Tags -->\n";

    // Academic-specific meta tags that SEO plugins don't handle
    if (!empty($data['name'])) {
      echo '<meta name="citation_author" content="' . esc_attr($data['name']) . '">' . "\n";
    }

    if (!empty($data['affiliation'])) {
      echo '<meta name="citation_author_institution" content="' . esc_attr($data['affiliation']) . '">' . "\n";
    }

    // Research interests as academic keywords
    if (!empty($data['interests']) && is_array($data['interests'])) {
      $keywords = array_map(function ($interest) {
        return is_array($interest) ? $interest['text'] : $interest;
      }, $data['interests']);

      if (!empty($keywords)) {
        echo '<meta name="citation_keywords" content="' . esc_attr(implode('; ', $keywords)) . '">' . "\n";
      }
    }

    // Citation metrics
    if (!empty($data['citations']['total'])) {
      echo '<meta name="citation_total" content="' . esc_attr($data['citations']['total']) . '">' . "\n";
      echo '<meta name="citation_h_index" content="' . esc_attr($data['citations']['h_index'] ?? 0) . '">' . "\n";
    }

    // Profile-specific Open Graph properties (academic focus)
    if (!empty($data['name']) && !empty($data['affiliation'])) {
      echo '<meta property="profile:username" content="' . esc_attr(sanitize_title($data['name'])) . '">' . "\n";
    }
  }

  /**
   * Add JSON-LD structured data
   */
  public function add_structured_data($data, $publications = array())
  {
    if (empty($data) || !is_array($data)) {
      return;
    }

    // Add to footer to avoid conflicts
    add_action('wp_footer', function () use ($data, $publications) {
      $this->output_structured_data($data, $publications);
    });
  }

  /**
   * Output JSON-LD structured data
   */
  private function output_structured_data($data, $publications)
  {
    if ($this->has_output) {
      return;
    }

    echo "\n<!-- Scholar Profile Structured Data -->\n";

    // Person schema with academic focus
    $person_schema = array(
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $data['name'] ?? '',
      'jobTitle' => 'Researcher'
    );

    if (!empty($data['affiliation'])) {
      $person_schema['affiliation'] = array(
        '@type' => 'Organization',
        'name' => $data['affiliation']
      );
    }

    if (!empty($data['avatar'])) {
      $person_schema['image'] = $data['avatar'];
    }

    if (!empty($data['interests'])) {
      $keywords = array_map(function ($interest) {
        return is_array($interest) ? $interest['text'] : $interest;
      }, $data['interests']);
      $person_schema['knowsAbout'] = array_slice($keywords, 0, 5);
    }

    // Add citation metrics as achievements
    if (!empty($data['citations']['total'])) {
      $person_schema['award'] = sprintf(
        '%s citations, h-index: %s',
        number_format($data['citations']['total']),
        $data['citations']['h_index'] ?? 0
      );
    }

    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($person_schema, JSON_UNESCAPED_SLASHES);
    echo "\n" . '</script>' . "\n";

    // Publications as ItemList (simplified)
    if (!empty($publications) && count($publications) > 0) {
      $itemlist_schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Publications',
        'numberOfItems' => count($publications),
        'description' => sprintf('%s academic publications', count($publications))
      );

      echo '<script type="application/ld+json">' . "\n";
      echo wp_json_encode($itemlist_schema, JSON_UNESCAPED_SLASHES);
      echo "\n" . '</script>' . "\n";
    }
  }
}
