<?php

namespace WPScholar;

class Scraper
{
  public function scrape($profile_id)
  {
    if (empty($profile_id)) {
      return false;
    }

    // Get main profile data
    $main_url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en";
    $main_html = $this->fetch_url($main_url);

    if (!$main_html) {
      return false;
    }

    $data = $this->parse_html($main_html);

    // Get co-authors data from the co-authors tab
    $coauthors_url = "https://scholar.google.com/citations?user=" . urlencode($profile_id) . "&hl=en&view_op=list_colleagues";
    $coauthors_html = $this->fetch_url($coauthors_url);

    if ($coauthors_html) {
      $this->parse_coauthors_page($coauthors_html, $data);
    }

    return $data;
  }

  private function fetch_url($url)
  {
    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      )
    ));

    if (is_wp_error($response)) {
      error_log('Google Scholar Profile Plugin: Error fetching URL - ' . $response->get_error_message());
      return false;
    }

    return wp_remote_retrieve_body($response);
  }

  private function parse_html($html)
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

    $this->extract_profile_info($xpath, $data);
    $this->extract_citations($xpath, $data);
    $this->extract_publications($xpath, $data);

    return $data;
  }

  private function parse_coauthors_page($html, &$data)
  {
    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    @$doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new \DOMXPath($doc);

    // Get all co-author entries from the co-authors page
    $coauthors = $xpath->query("//div[@class='gsc_1usr']");

    foreach ($coauthors as $coauthor) {
      // Extract name and profile link
      $name_link = $xpath->query(".//h3[@class='gsc_1usr_name']/a", $coauthor)->item(0);

      // Extract affiliation
      $affiliation = $xpath->query(".//div[@class='gsc_1usr_aff']", $coauthor)->item(0);

      // Extract interests
      $interests_nodes = $xpath->query(".//span[@class='gsc_1usr_int']", $coauthor);
      $interests = array();
      foreach ($interests_nodes as $interest) {
        $interests[] = trim($interest->textContent);
      }

      // Extract metrics
      $cited_by = $xpath->query(".//div[@class='gsc_1usr_cby']", $coauthor)->item(0);

      if ($name_link) {
        $coauthor_data = array(
          'name' => trim($name_link->textContent),
          'profile_url' => 'https://scholar.google.com' . $name_link->getAttribute('href'),
          'affiliation' => $affiliation ? trim($affiliation->textContent) : '',
          'interests' => $interests,
          'cited_by' => $cited_by ? (int) preg_replace('/\D/', '', $cited_by->textContent) : 0
        );

        // Get profile image URL from hidden thumbnail
        $img = $xpath->query(".//div[@class='gsc_1usr_photo']/a/img", $coauthor)->item(0);
        if ($img) {
          $coauthor_data['avatar'] = $img->getAttribute('src');
        }

        $data['coauthors'][] = $coauthor_data;
      }
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
          'citations' => $citations_node ? intval($citations_node->textContent) : 0
        );

        if ($publication['citations'] > 0 && $citations_node) {
          $publication['citations_url'] = 'https://scholar.google.com' . $citations_node->getAttribute('href');
        }

        $data['publications'][] = $publication;
      }
    }
  }

  protected function extract_profile_info($xpath, &$data)
  {
    $avatar_node = $xpath->query("//img[@id='gsc_prf_pup-img']")->item(0);
    if ($avatar_node) {
      $data['avatar'] = $avatar_node->getAttribute('src');
    }

    $name_node = $xpath->query("//div[@id='gsc_prf_in']")->item(0);
    if ($name_node) {
      $data['name'] = trim($name_node->textContent);
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
}
