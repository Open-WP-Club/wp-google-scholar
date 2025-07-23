<?php

namespace WPScholar;

class Scraper
{
  private $max_publications = 200; // Reasonable limit to prevent excessive requests
  private $page_size = 20; // Google Scholar shows 20 publications per page
  private $request_delay = 1; // Delay between requests in seconds

  private function download_to_media_library($image_url, $profile_id, $title = '')
  {
    if (empty($image_url)) {
      wp_scholar_log("Avatar download skipped: Empty image URL for profile $profile_id");
      return '';
    }

    wp_scholar_log("Starting avatar download for profile $profile_id from: $image_url");

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
        wp_scholar_log("Using cached avatar for hash: $url_hash");
        return $attachment_url;
      } else {
        // Clean up broken attachment
        wp_delete_attachment($existing_attachment[0]->ID, true);
        wp_scholar_log("Removed broken cached avatar for hash: $url_hash");
      }
    }

    // Check if we've downloaded this exact URL recently (within last 24 hours)
    $recent_download = get_transient('scholar_image_download_' . $url_hash);
    if ($recent_download) {
      wp_scholar_log("Using recent cached avatar for hash: $url_hash");
      return $recent_download;
    }

    wp_scholar_log("Downloading new avatar for hash: $url_hash from: $image_url");

    // Handle different image URL formats
    $file_array = null;
    $cleanup_temp = false;

    try {
      if (strpos($image_url, 'data:image') === 0) {
        // Handle base64 encoded images
        wp_scholar_log("Processing base64 avatar image");
        $file_array = $this->process_base64_image($image_url, $filename);
        $cleanup_temp = true;
      } else {
        // Handle external image URLs
        wp_scholar_log("Processing external avatar URL");
        $file_array = $this->process_external_image($image_url, $filename);
        $cleanup_temp = true;
      }

      if (!$file_array) {
        wp_scholar_log("Failed to process avatar image for profile $profile_id");
        return '';
      }

      // Verify the file exists and is readable
      if (!file_exists($file_array['tmp_name']) || !is_readable($file_array['tmp_name'])) {
        wp_scholar_log("Avatar temp file is not readable: " . $file_array['tmp_name']);
        return '';
      }

      // Check file size (should be reasonable for an avatar)
      $file_size = filesize($file_array['tmp_name']);
      if ($file_size === false || $file_size < 100) {
        wp_scholar_log("Avatar file too small or unreadable: {$file_size} bytes");
        if ($cleanup_temp && file_exists($file_array['tmp_name'])) {
          unlink($file_array['tmp_name']);
        }
        return '';
      }

      if ($file_size > 5 * 1024 * 1024) { // 5MB limit
        wp_scholar_log("Avatar file too large: {$file_size} bytes");
        if ($cleanup_temp && file_exists($file_array['tmp_name'])) {
          unlink($file_array['tmp_name']);
        }
        return '';
      }

      wp_scholar_log("Avatar file size OK: {$file_size} bytes");

      // Check file type more thoroughly
      $file_type = wp_check_filetype($filename);
      if (!$file_type['type'] || !in_array($file_type['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        wp_scholar_log("Invalid avatar file type: " . ($file_type['type'] ?: 'unknown'));
        if ($cleanup_temp && file_exists($file_array['tmp_name'])) {
          unlink($file_array['tmp_name']);
        }
        return '';
      }

      // Additional image validation using getimagesize
      $image_info = @getimagesize($file_array['tmp_name']);
      if (!$image_info) {
        wp_scholar_log("Avatar file is not a valid image");
        if ($cleanup_temp && file_exists($file_array['tmp_name'])) {
          unlink($file_array['tmp_name']);
        }
        return '';
      }

      wp_scholar_log("Avatar validated: {$image_info[0]}x{$image_info[1]}, type: {$image_info['mime']}");

      // Prepare attachment data with better title
      $clean_title = !empty($title) ? $title : sprintf('Scholar Avatar - %s', $profile_id);
      $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => sanitize_text_field($clean_title),
        'post_content' => '',
        'post_status' => 'inherit'
      );

      // Insert attachment into media library
      $attach_id = media_handle_sideload($file_array, 0, '', $attachment);

      if (is_wp_error($attach_id)) {
        if ($cleanup_temp && isset($file_array['tmp_name']) && file_exists($file_array['tmp_name'])) {
          unlink($file_array['tmp_name']);
        }
        wp_scholar_log('Failed to create avatar attachment: ' . $attach_id->get_error_message());
        return '';
      }

      // Add enhanced meta for better caching and tracking
      update_post_meta($attach_id, '_scholar_profile_id', $profile_id);
      update_post_meta($attach_id, '_scholar_image_url', $image_url);
      update_post_meta($attach_id, '_scholar_image_hash', $url_hash);
      update_post_meta($attach_id, '_scholar_download_time', time());
      update_post_meta($attach_id, '_scholar_image_type', 'avatar');

      // Get the attachment URL
      $attachment_url = wp_get_attachment_url($attach_id);

      if ($attachment_url) {
        // Cache the result for 24 hours
        set_transient('scholar_image_download_' . $url_hash, $attachment_url, 24 * HOUR_IN_SECONDS);
        wp_scholar_log("Successfully downloaded and cached avatar with hash: $url_hash");
        return $attachment_url;
      } else {
        wp_scholar_log("Failed to get attachment URL for avatar ID: $attach_id");
        return '';
      }
    } catch (Exception $e) {
      wp_scholar_log("Exception during avatar download: " . $e->getMessage());
      if ($cleanup_temp && isset($file_array['tmp_name']) && file_exists($file_array['tmp_name'])) {
        unlink($file_array['tmp_name']);
      }
      return '';
    }
  }

  /**
   * Process base64 encoded images
   */
  private function process_base64_image($image_url, $filename)
  {
    wp_scholar_log("Processing base64 image data");

    // Validate base64 data format
    if (!preg_match('/^data:image\/([a-zA-Z]+);base64,(.+)$/', $image_url, $matches)) {
      wp_scholar_log("Invalid base64 image format");
      return null;
    }

    $image_type = $matches[1];
    $image_data_b64 = $matches[2];

    // Validate image type
    if (!in_array(strtolower($image_type), ['jpeg', 'jpg', 'png', 'gif', 'webp'])) {
      wp_scholar_log("Unsupported base64 image type: $image_type");
      return null;
    }

    $image_data = base64_decode($image_data_b64);
    if ($image_data === false) {
      wp_scholar_log("Failed to decode base64 image data");
      return null;
    }

    // Create temporary file with proper extension
    $temp_file = wp_tempnam();
    if (!$temp_file) {
      wp_scholar_log("Failed to create temporary file for base64 image");
      return null;
    }

    $bytes_written = file_put_contents($temp_file, $image_data);
    if ($bytes_written === false) {
      wp_scholar_log("Failed to write base64 image data to temporary file");
      unlink($temp_file);
      return null;
    }

    wp_scholar_log("Base64 image written to temp file: $bytes_written bytes");

    return array(
      'name' => $filename,
      'tmp_name' => $temp_file
    );
  }

  /**
   * Process external image URLs with better error handling
   */
  private function process_external_image($image_url, $filename)
  {
    wp_scholar_log("Processing external image URL: $image_url");

    // Validate URL format
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
      wp_scholar_log("Invalid image URL format");
      return null;
    }

    // Handle Google Scholar specific avatar URLs
    $processed_url = $this->process_google_scholar_avatar_url($image_url);

    // Download with enhanced options
    $temp_file = download_url($processed_url, 300, false);

    if (is_wp_error($temp_file)) {
      $error_message = $temp_file->get_error_message();
      wp_scholar_log("Failed to download external image: $error_message");

      // Try alternative approaches for Google Scholar images
      if (
        strpos($image_url, 'googleusercontent.com') !== false ||
        strpos($image_url, 'scholar.google.com') !== false
      ) {
        wp_scholar_log("Attempting Google Scholar avatar workaround");
        return $this->try_google_scholar_avatar_workaround($image_url, $filename);
      }

      return null;
    }

    wp_scholar_log("External image downloaded to: $temp_file");

    return array(
      'name' => $filename,
      'tmp_name' => $temp_file
    );
  }

  /**
   * Process Google Scholar specific avatar URLs
   */
  private function process_google_scholar_avatar_url($url)
  {
    // Handle different Google Scholar avatar URL formats
    if (strpos($url, 'googleusercontent.com') !== false) {
      // Ensure we request a reasonable size
      if (strpos($url, '=s') !== false) {
        $url = preg_replace('/=s\d+/', '=s120', $url);
      } else {
        $url .= '=s120';
      }
      wp_scholar_log("Processed Google avatar URL: $url");
    }

    return $url;
  }

  /**
   * Workaround for Google Scholar avatar download issues
   */
  private function try_google_scholar_avatar_workaround($original_url, $filename)
  {
    wp_scholar_log("Trying Google Scholar avatar workaround");

    // Try to use WordPress HTTP API with custom headers
    $response = wp_remote_get($original_url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Cache-Control' => 'no-cache',
        'Referer' => 'https://scholar.google.com/'
      ),
      'sslverify' => false
    ));

    if (is_wp_error($response)) {
      wp_scholar_log("Workaround failed: " . $response->get_error_message());
      return null;
    }

    $image_data = wp_remote_retrieve_body($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type');

    if (empty($image_data)) {
      wp_scholar_log("Workaround got empty response body");
      return null;
    }

    // Verify content type
    if ($content_type && strpos($content_type, 'image/') !== 0) {
      wp_scholar_log("Workaround got non-image content type: $content_type");
      return null;
    }

    // Create temporary file
    $temp_file = wp_tempnam();
    if (!$temp_file) {
      wp_scholar_log("Failed to create temp file for workaround");
      return null;
    }

    $bytes_written = file_put_contents($temp_file, $image_data);
    if ($bytes_written === false) {
      wp_scholar_log("Failed to write workaround image data");
      unlink($temp_file);
      return null;
    }

    wp_scholar_log("Workaround successful: $bytes_written bytes written");

    return array(
      'name' => $filename,
      'tmp_name' => $temp_file
    );
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
      $exists = file_exists($file_path);
      wp_scholar_log("Checking cached image existence: " . ($exists ? 'EXISTS' : 'MISSING') . " - $file_path");
      return $exists;
    }

    // For external URLs, we'll assume they exist to avoid extra HTTP requests
    return true;
  }

  public function scrape($profile_id)
  {
    if (empty($profile_id)) {
      wp_scholar_log("Scrape failed: Empty profile ID");
      return false;
    }

    wp_scholar_log("Starting to scrape profile: $profile_id");

    try {
      // First, get the main profile page to extract basic info
      $main_data = $this->scrape_main_profile($profile_id);
      if (!$main_data) {
        wp_scholar_log("Failed to scrape main profile data for: $profile_id");
        return false;
      }

      // Then get all publications across multiple pages
      $all_publications = $this->scrape_all_publications($profile_id);
      $main_data['publications'] = $all_publications;

      wp_scholar_log(sprintf("Successfully scraped profile %s with %d publications", $profile_id, count($all_publications)));

      return $main_data;
    } catch (Exception $e) {
      wp_scholar_log("Exception during scraping: " . $e->getMessage());
      return false;
    }
  }

  private function scrape_main_profile($profile_id)
  {
    $url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en";

    wp_scholar_log("Fetching main profile from: $url");

    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
      )
    ));

    if (is_wp_error($response)) {
      wp_scholar_log('Error fetching main profile - ' . $response->get_error_message());
      return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
      wp_scholar_log("Main profile returned HTTP $status_code");
      return false;
    }

    $html = wp_remote_retrieve_body($response);

    if (empty($html)) {
      wp_scholar_log('Empty response from main profile');
      return false;
    }

    // Check for common error indicators
    if (
      strpos($html, 'could not be found') !== false ||
      strpos($html, 'not available') !== false ||
      strpos($html, 'error') !== false
    ) {
      wp_scholar_log('Profile appears to be unavailable or not found');
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
      if ($page_number > 25) {
        wp_scholar_log("Reached maximum page limit (25 pages)");
        break;
      }
    }

    wp_scholar_log("Total publications collected: " . count($all_publications));
    return $all_publications;
  }

  private function scrape_publications_page($profile_id, $start = 0)
  {
    $url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en&cstart=" . $start . "&pagesize=" . $this->page_size;

    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36'
      )
    ));

    if (is_wp_error($response)) {
      wp_scholar_log('Error fetching publications page - ' . $response->get_error_message());
      return array();
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
      wp_scholar_log("Publications page returned HTTP $status_code");
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

    // Validate that we extracted meaningful data
    if (empty($data['name'])) {
      wp_scholar_log("Failed to extract profile name - page may have changed structure");
      return false;
    }

    wp_scholar_log("Successfully parsed profile for: " . $data['name']);
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
      wp_scholar_log("Extracted name: " . $data['name']);
    } else {
      wp_scholar_log("Warning: Could not extract profile name");
    }

    // Now get the avatar with enhanced error handling
    $avatar_node = $xpath->query("//img[@id='gsc_prf_pup-img']")->item(0);
    if ($avatar_node) {
      $avatar_url = $avatar_node->getAttribute('src');
      wp_scholar_log("Found avatar URL: $avatar_url");

      if (!empty($avatar_url)) {
        $data['avatar'] = $this->download_to_media_library(
          $avatar_url,
          $profile_id,
          sprintf('Scholar Profile Avatar - %s', $data['name'] ?: $profile_id)
        );

        if ($data['avatar']) {
          wp_scholar_log("Avatar successfully downloaded: " . $data['avatar']);
        } else {
          wp_scholar_log("Avatar download failed for URL: $avatar_url");
        }
      }
    } else {
      wp_scholar_log("No avatar found in profile");
    }

    $affiliation_node = $xpath->query("//div[@class='gsc_prf_il']")->item(0);
    if ($affiliation_node) {
      $data['affiliation'] = trim($affiliation_node->textContent);
      wp_scholar_log("Extracted affiliation: " . $data['affiliation']);
    }

    $interests_nodes = $xpath->query("//div[@id='gsc_prf_int']//a");
    foreach ($interests_nodes as $interest) {
      $data['interests'][] = array(
        'text' => trim($interest->textContent),
        'url' => 'https://scholar.google.com' . $interest->getAttribute('href')
      );
    }
    wp_scholar_log("Extracted " . count($data['interests']) . " research interests");
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

      wp_scholar_log("Extracted citations - Total: " . $data['citations']['total'] . ", h-index: " . $data['citations']['h_index']);
    } else {
      wp_scholar_log("Warning: Could not extract citation metrics");
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
        $local_avatar = '';

        if ($avatar_url) {
          wp_scholar_log("Processing coauthor avatar for: $name");
          $local_avatar = $this->download_to_media_library(
            $avatar_url,
            $profile_id,
            sprintf('Scholar Coauthor Avatar - %s', $name)
          );

          if ($local_avatar) {
            wp_scholar_log("Coauthor avatar successfully downloaded: $local_avatar");
          } else {
            wp_scholar_log("Coauthor avatar download failed for: $name");
          }
        }

        $coauthor_data = array(
          'name' => $name,
          'profile_url' => 'https://scholar.google.com' . $link->getAttribute('href'),
          'title' => $affiliation ? trim($affiliation->textContent) : '',
          'avatar' => $local_avatar
        );

        $data['coauthors'][] = $coauthor_data;
      }
    }

    wp_scholar_log("Extracted " . count($data['coauthors']) . " coauthors");
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
      wp_scholar_log("Max publications set to: " . $this->max_publications);
    }
    if (isset($config['page_size'])) {
      $this->page_size = max(10, min(100, intval($config['page_size'])));
    }
    if (isset($config['request_delay'])) {
      $this->request_delay = max(0.5, min(5, floatval($config['request_delay'])));
    }
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
}
