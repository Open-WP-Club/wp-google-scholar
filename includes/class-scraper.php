<?php

namespace WPScholar;

class Scraper
{
  private $max_publications = 200; // Reasonable limit to prevent excessive requests
  private $page_size = 20; // Google Scholar shows 20 publications per page
  private $request_delay = 1; // Delay between requests in seconds

  private function download_to_media_library($image_url, $profile_id, $title = '')
  {
    if (empty($image_url)) return '';

    // Generate a consistent filename based on URL hash for better caching
    $url_hash = md5($image_url);
    $filename = 'scholar-' . $profile_id . '-' . $url_hash;

    // Determine file extension
    $extension = '.jpg'; // Default
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $image_url, $matches)) {
      $extension = strtolower($matches[0]);
    }
    $filename .= $extension;

    // Enhanced cache check - look for existing images by URL hash and profile ID
    $existing_attachment = get_posts(array(
      'post_type' => 'attachment',
      'meta_query' => array(
        'relation' => 'AND',
        array(
          'key' => '_scholar_profile_id',
          'value' => $profile_id
        ),
        array(
          'key' => '_scholar_image_hash',
          'value' => $url_hash
        )
      ),
      'posts_per_page' => 1
    ));

    if (!empty($existing_attachment)) {
      $attachment_url = wp_get_attachment_url($existing_attachment[0]->ID);
      if ($attachment_url && $this->verify_image_exists($attachment_url)) {
        wp_scholar_log("Using cached image for hash: $url_hash");
        return $attachment_url;
      } else {
        // Clean up broken attachment
        wp_delete_attachment($existing_attachment[0]->ID, true);
        wp_scholar_log("Removed broken cached image for hash: $url_hash");
      }
    }

    // Check if we've downloaded this exact URL recently (within last 24 hours)
    $recent_download = get_transient('scholar_image_download_' . $url_hash);
    if ($recent_download) {
      wp_scholar_log("Skipping recent download for hash: $url_hash");
      return $recent_download;
    }

    wp_scholar_log("Downloading new image for hash: $url_hash from: $image_url");

    // Prepare image data
    if (strpos($image_url, 'data:image') === 0) {
      // Handle base64 encoded images
      $data = explode(',', $image_url);
      if (count($data) < 2) {
        wp_scholar_log("Invalid base64 image data");
        return '';
      }

      $image_data = base64_decode($data[1]);
      if ($image_data === false) {
        wp_scholar_log("Failed to decode base64 image");
        return '';
      }

      // Create temporary file
      $tmp = wp_tempnam();
      file_put_contents($tmp, $image_data);

      $file_array = array(
        'name' => $filename,
        'tmp_name' => $tmp
      );
    } else {
      // Download external image with better error handling
      $temp_file = download_url($image_url, 300, false);
      if (is_wp_error($temp_file)) {
        wp_scholar_log('Failed to download image: ' . $temp_file->get_error_message());
        return '';
      }

      $file_array = array(
        'name' => $filename,
        'tmp_name' => $temp_file
      );
    }

    // Check file type
    $file_type = wp_check_filetype($filename);
    if (!$file_type['type']) {
      unlink($file_array['tmp_name']);
      wp_scholar_log("Invalid file type for: $filename");
      return '';
    }

    // Prepare attachment data with better title
    $clean_title = !empty($title) ? $title : sprintf('Scholar Image - %s', $profile_id);
    $attachment = array(
      'post_mime_type' => $file_type['type'],
      'post_title' => sanitize_text_field($clean_title),
      'post_content' => '',
      'post_status' => 'inherit'
    );

    // Insert attachment into media library
    $attach_id = media_handle_sideload($file_array, 0, '', $attachment);

    if (is_wp_error($attach_id)) {
      if (isset($file_array['tmp_name']) && file_exists($file_array['tmp_name'])) {
        unlink($file_array['tmp_name']);
      }
      wp_scholar_log('Failed to create attachment: ' . $attach_id->get_error_message());
      return '';
    }

    // Add enhanced meta for better caching and tracking
    update_post_meta($attach_id, '_scholar_profile_id', $profile_id);
    update_post_meta($attach_id, '_scholar_image_url', $image_url);
    update_post_meta($attach_id, '_scholar_image_hash', $url_hash);
    update_post_meta($attach_id, '_scholar_download_time', time());

    // Get the attachment URL
    $attachment_url = wp_get_attachment_url($attach_id);

    if ($attachment_url) {
      // Cache the result for 24 hours
      set_transient('scholar_image_download_' . $url_hash, $attachment_url, 24 * HOUR_IN_SECONDS);
      wp_scholar_log("Successfully cached image with hash: $url_hash");
      return $attachment_url;
    }

    return '';
  }

  /**
   * Verify that a cached image still exists and is accessible
   */
  private function verify_image_exists($url)
  {
    if (empty($url)) return false;

    // For local URLs, check if file exists
    if (strpos($url, home_url()) === 0) {
      $upload_dir = wp_upload_dir();
      $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
      return file_exists($file_path);
    }

    // For external URLs, we'll assume they exist to avoid extra HTTP requests
    return true;
  }

  /**
   * Clean up old cached images for a profile (optional method for maintenance)
   */
  public function cleanup_old_images($profile_id, $days_old = 30)
  {
    $old_attachments = get_posts(array(
      'post_type' => 'attachment',
      'meta_query' => array(
        array(
          'key' => '_scholar_profile_id',
          'value' => $profile_id
        ),
        array(
          'key' => '_scholar_download_time',
          'value' => time() - ($days_old * DAY_IN_SECONDS),
          'compare' => '<'
        )
      ),
      'posts_per_page' => -1
    ));

    $deleted_count = 0;
    foreach ($old_attachments as $attachment) {
      if (wp_delete_attachment($attachment->ID, true)) {
        $deleted_count++;
      }
    }

    if ($deleted_count > 0) {
      wp_scholar_log("Cleaned up $deleted_count old images for profile: $profile_id");
    }

    return $deleted_count;
  }

  public function scrape($profile_id)
  {
    if (empty($profile_id)) {
      return false;
    }

    wp_scholar_log("Starting to scrape profile: $profile_id");

    // First, get the main profile page to extract basic info
    $main_data = $this->scrape_main_profile($profile_id);
    if (!$main_data) {
      wp_scholar_log("Failed to scrape main profile data");
      return false;
    }

    // Then get all publications across multiple pages
    $all_publications = $this->scrape_all_publications($profile_id);
    $main_data['publications'] = $all_publications;

    wp_scholar_log(sprintf("Successfully scraped %d publications", count($all_publications)));

    return $main_data;
  }

  private function scrape_main_profile($profile_id)
  {
    $url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en";

    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      )
    ));

    if (is_wp_error($response)) {
      wp_scholar_log('Error fetching main profile - ' . $response->get_error_message());
      return false;
    }

    $html = wp_remote_retrieve_body($response);

    if (empty($html)) {
      wp_scholar_log('Empty response from main profile');
      return false;
    }

    return $this->parse_main_profile_html($html, $profile_id);
  }

  private function scrape_all_publications($profile_id)
  {
    $all_publications = array();
    $current_start = 0;
    $page_number = 1;

    while (count($all_publications) < $this->max_publications) {
      wp_scholar_log("Fetching publications page $page_number (start: $current_start)");

      $publications = $this->scrape_publications_page($profile_id, $current_start);

      if (empty($publications)) {
        wp_scholar_log("No more publications found on page $page_number");
        break;
      }

      $all_publications = array_merge($all_publications, $publications);

      // If we got fewer publications than the page size, we've reached the end
      if (count($publications) < $this->page_size) {
        wp_scholar_log("Reached end of publications (got " . count($publications) . " on page $page_number)");
        break;
      }

      $current_start += $this->page_size;
      $page_number++;

      // Add delay between requests to be respectful to Google Scholar
      if ($page_number > 1) {
        sleep($this->request_delay);
      }

      // Safety check to prevent infinite loops
      if ($page_number > 20) {
        wp_scholar_log("Reached maximum page limit (20 pages)");
        break;
      }
    }

    return $all_publications;
  }

  private function scrape_publications_page($profile_id, $start = 0)
  {
    $url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en&cstart=" . $start . "&pagesize=" . $this->page_size;

    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      )
    ));

    if (is_wp_error($response)) {
      wp_scholar_log('Error fetching publications page - ' . $response->get_error_message());
      return array();
    }

    $html = wp_remote_retrieve_body($response);

    if (empty($html)) {
      wp_scholar_log('Empty response from publications page');
      return array();
    }

    return $this->extract_publications_from_html($html);
  }

  private function parse_main_profile_html($html, $profile_id)
  {
    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    @$doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new \DOMXPath($doc);

    $data = array(
      'avatar' => '',
      'name' => '',
      'affiliation' => '',
      'interests' => array(),
      'publications' => array(), // Will be populated separately
      'citations' => array(
        'total' => 0,
        'h_index' => 0,
        'i10_index' => 0,
        'since_2019' => 0,
        'h_index_2019' => 0,
        'i10_index_2019' => 0
      ),
      'coauthors' => array()
    );

    $this->extract_profile_info($xpath, $data, $profile_id);
    $this->extract_citations($xpath, $data);
    $this->extract_coauthors($xpath, $data, $profile_id);

    return $data;
  }

  private function extract_publications_from_html($html)
  {
    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    @$doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new \DOMXPath($doc);

    $publications = array();
    $publication_rows = $xpath->query("//tr[@class='gsc_a_tr']");

    foreach ($publication_rows as $pub) {
      $title_node = $xpath->query(".//a[@class='gsc_a_at']", $pub)->item(0);
      $authors_node = $xpath->query(".//div[@class='gs_gray'][1]", $pub)->item(0);
      $venue_node = $xpath->query(".//div[@class='gs_gray'][2]", $pub)->item(0);
      $year_node = $xpath->query(".//span[@class='gsc_a_h gsc_a_hc gs_ibl']", $pub)->item(0);
      $citations_node = $xpath->query(".//a[@class='gsc_a_ac gs_ibl']", $pub)->item(0);

      if ($title_node) {
        $publication = array(
          'title' => trim($title_node->textContent),
          'link' => 'https://scholar.google.com' . $title_node->getAttribute('href'),
          'google_scholar_url' => 'https://scholar.google.com' . $title_node->getAttribute('href'),
          'authors' => $authors_node ? trim($authors_node->textContent) : '',
          'venue' => $venue_node ? trim($venue_node->textContent) : '',
          'year' => $year_node ? trim($year_node->textContent) : '',
          'citations' => $citations_node ? intval($citations_node->textContent) : 0,
          'citations_url' => $citations_node ? 'https://scholar.google.com' . $citations_node->getAttribute('href') : '',
          'citations_by_year_url' => $citations_node ? 'https://scholar.google.com' . $citations_node->getAttribute('href') . '&view_op=view_citation_years' : ''
        );

        $publications[] = $publication;
      }
    }

    return $publications;
  }

  protected function extract_profile_info($xpath, &$data, $profile_id)
  {
    // Get the name first as we need it for the avatar title
    $name_node = $xpath->query("//div[@id='gsc_prf_in']")->item(0);
    if ($name_node) {
      $data['name'] = trim($name_node->textContent);
    }

    // Now get the avatar
    $avatar_node = $xpath->query("//img[@id='gsc_prf_pup-img']")->item(0);
    if ($avatar_node) {
      $avatar_url = $avatar_node->getAttribute('src');
      $data['avatar'] = $this->download_to_media_library(
        $avatar_url,
        $profile_id,
        sprintf('Scholar Profile Avatar - %s', $data['name'] ?: $profile_id)
      );
    }

    $affiliation_node = $xpath->query("//div[@class='gsc_prf_il']")->item(0);
    if ($affiliation_node) {
      $data['affiliation'] = trim($affiliation_node->textContent);
    }

    $interests_nodes = $xpath->query("//div[@id='gsc_prf_int']//a");
    foreach ($interests_nodes as $interest) {
      $data['interests'][] = array(
        'text' => trim($interest->textContent),
        'url' => 'https://scholar.google.com' . $interest->getAttribute('href')
      );
    }
  }

  protected function extract_citations($xpath, &$data)
  {
    // Get all citation table cells
    $table_cells = $xpath->query("//table[@id='gsc_rsb_st']//td[@class='gsc_rsb_std']");

    if ($table_cells->length >= 6) {
      // All time citations
      $data['citations']['total'] = intval(trim($table_cells->item(0)->textContent));
      $data['citations']['h_index'] = intval(trim($table_cells->item(2)->textContent));
      $data['citations']['i10_index'] = intval(trim($table_cells->item(4)->textContent));

      // Since 2019 citations
      $data['citations']['since_2019'] = intval(trim($table_cells->item(1)->textContent));
      $data['citations']['h_index_2019'] = intval(trim($table_cells->item(3)->textContent));
      $data['citations']['i10_index_2019'] = intval(trim($table_cells->item(5)->textContent));
    }
  }

  protected function extract_publications($xpath, &$data)
  {
    // This method is now deprecated in favor of extract_publications_from_html
    // Keeping for backwards compatibility but not used in new flow
    $publications = $xpath->query("//tr[@class='gsc_a_tr']");

    foreach ($publications as $pub) {
      $title_node = $xpath->query(".//a[@class='gsc_a_at']", $pub)->item(0);
      $authors_node = $xpath->query(".//div[@class='gs_gray'][1]", $pub)->item(0);
      $venue_node = $xpath->query(".//div[@class='gs_gray'][2]", $pub)->item(0);
      $year_node = $xpath->query(".//span[@class='gsc_a_h gsc_a_hc gs_ibl']", $pub)->item(0);
      $citations_node = $xpath->query(".//a[@class='gsc_a_ac gs_ibl']", $pub)->item(0);

      if ($title_node) {
        $publication = array(
          'title' => trim($title_node->textContent),
          'link' => 'https://scholar.google.com' . $title_node->getAttribute('href'),
          'google_scholar_url' => 'https://scholar.google.com' . $title_node->getAttribute('href'),
          'authors' => $authors_node ? trim($authors_node->textContent) : '',
          'venue' => $venue_node ? trim($venue_node->textContent) : '',
          'year' => $year_node ? trim($year_node->textContent) : '',
          'citations' => $citations_node ? intval($citations_node->textContent) : 0,
          'citations_url' => $citations_node ? 'https://scholar.google.com' . $citations_node->getAttribute('href') : '',
          'citations_by_year_url' => $citations_node ? 'https://scholar.google.com' . $citations_node->getAttribute('href') . '&view_op=view_citation_years' : ''
        );

        $data['publications'][] = $publication;
      }
    }
  }

  protected function extract_coauthors($xpath, &$data, $profile_id)
  {
    $coauthors = $xpath->query("//div[contains(@class, 'gsc_rsb_aa')]");

    foreach ($coauthors as $coauthor) {
      $link = $xpath->query(".//a", $coauthor)->item(0);
      $affiliation = $xpath->query(".//div[contains(@class, 'gsc_rsb_a_ext')]", $coauthor)->item(0);
      $img = $xpath->query(".//img", $coauthor)->item(0);

      if ($link) {
        $name = trim($link->textContent);
        $avatar_url = $img ? $img->getAttribute('src') : '';
        $local_avatar = $this->download_to_media_library(
          $avatar_url,
          $profile_id,
          sprintf('Scholar Coauthor Avatar - %s', $name)
        );

        $coauthor_data = array(
          'name' => $name,
          'profile_url' => 'https://scholar.google.com' . $link->getAttribute('href'),
          'title' => $affiliation ? trim($affiliation->textContent) : '',
          'avatar' => $local_avatar
        );

        $data['coauthors'][] = $coauthor_data;
      }
    }
  }

  // Public method to get configuration
  public function get_config()
  {
    return array(
      'max_publications' => $this->max_publications,
      'page_size' => $this->page_size,
      'request_delay' => $this->request_delay
    );
  }

  // Public method to set configuration
  public function set_config($config)
  {
    if (isset($config['max_publications'])) {
      $this->max_publications = max(20, min(500, intval($config['max_publications'])));
    }
    if (isset($config['page_size'])) {
      $this->page_size = max(10, min(100, intval($config['page_size'])));
    }
    if (isset($config['request_delay'])) {
      $this->request_delay = max(0.5, min(5, floatval($config['request_delay'])));
    }
  }
}
