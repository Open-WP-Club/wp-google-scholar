<?php

namespace WPScholar;

class Scraper
{
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

    return $this->parse_html($html);
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

    // Extract data using protected methods
    $this->extract_profile_info($xpath, $data);
    $this->extract_citations($xpath, $data);
    $this->extract_publications($xpath, $data);
    $this->extract_coauthors($xpath, $data);

    return $data;
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

        // If citations > 0, add citations URL
        if ($publication['citations'] > 0 && $citations_node) {
          $publication['citations_url'] = 'https://scholar.google.com' . $citations_node->getAttribute('href');
        }

        $data['publications'][] = $publication;
      }
    }
  }

  protected function extract_profile_info($xpath, &$data)
  {
    // Extract basic profile information
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
