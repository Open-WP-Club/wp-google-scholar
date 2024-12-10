<?php

namespace WPScholar;

class Scraper
{
  private function download_to_media_library($image_url, $profile_id, $title = '')
  {
    if (empty($image_url)) return '';

    // Generate a unique filename
    $filename = sanitize_file_name(basename($image_url));
    if (!preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)) {
      $filename .= '.jpg';
    }

    // Check if image already exists in media library by searching for the filename
    $existing_attachment = get_posts(array(
      'post_type' => 'attachment',
      'meta_key' => '_scholar_profile_id',
      'meta_value' => $profile_id,
      'meta_query' => array(
        array(
          'key' => '_scholar_image_url',
          'value' => $image_url
        )
      ),
      'posts_per_page' => 1
    ));

    if (!empty($existing_attachment)) {
      $attachment_url = wp_get_attachment_url($existing_attachment[0]->ID);
      if ($attachment_url) {
        return $attachment_url;
      }
    }

    // Prepare image data
    if (strpos($image_url, 'data:image') === 0) {
      // Handle base64 encoded images
      $data = explode(',', $image_url);
      $image_data = base64_decode($data[1]);

      // Create temporary file
      $tmp = wp_tempnam();
      file_put_contents($tmp, $image_data);

      $file_array = array(
        'name' => $filename,
        'tmp_name' => $tmp
      );
    } else {
      // Download external image
      $temp_file = download_url($image_url);
      if (is_wp_error($temp_file)) {
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
      return '';
    }

    // Prepare attachment data
    $attachment = array(
      'post_mime_type' => $file_type['type'],
      'post_title' => !empty($title) ? $title : pathinfo($filename, PATHINFO_FILENAME),
      'post_content' => '',
      'post_status' => 'inherit'
    );

    // Insert attachment into media library
    $attach_id = media_handle_sideload($file_array, 0, '', $attachment);

    if (is_wp_error($attach_id)) {
      unlink($file_array['tmp_name']);
      return '';
    }

    // Add custom meta to track the profile ID and original URL
    update_post_meta($attach_id, '_scholar_profile_id', $profile_id);
    update_post_meta($attach_id, '_scholar_image_url', $image_url);

    // Get the attachment URL
    $attachment_url = wp_get_attachment_url($attach_id);

    return $attachment_url ?: '';
  }

  public function scrape($profile_id)
  {
    if (empty($profile_id)) {
      return false;
    }

    $url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en";

    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      )
    ));

    if (is_wp_error($response)) {
      error_log('Google Scholar Profile Plugin: Error fetching profile - ' . $response->get_error_message());
      return false;
    }

    $html = wp_remote_retrieve_body($response);

    if (empty($html)) {
      return false;
    }

    return $this->parse_html($html, $profile_id);
  }

  private function parse_html($html, $profile_id)
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
      'publications' => array(),
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
    $this->extract_publications($xpath, $data);
    $this->extract_coauthors($xpath, $data, $profile_id);

    return $data;
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
      $data['interests'][] = trim($interest->textContent);
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
}
