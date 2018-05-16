<?php

// required for Zsearch
require_once("includes/search_func.phpi");



/**
 * Handles requests to infomedia
 */
class infomedia_webservice {
  /**
   * Get article-text as html from infomedia-webservice.
   */
  public static function get_article_html($config, $fileName, $verbose = null) {
    $info = $config->get_section("infomedia");
    $url = $info['webservice'] . $info['GetArticleRaw'] . $info['filepath'] . urldecode($fileName);

// TODO errorcheck
    $curl = new curl();
    $curl->set_url($url);

// TESTING - proxy MUST be set in working copy
    $proxy = $config->get_section('proxy');

    if ($proxy['domain_and_port']) {
      $curl->set_proxy($proxy['domain_and_port']);
    }

    $xml = $curl->get();

    if ($errormessage = helpFunc::check_curl($curl)) {
      return false;
    }

    return self::parse_for_html($xml);
  }

  /**
   * Parse given infomedia-xml and extract relevant fields. Generate and return HTML
   */
  private static function parse_for_html(&$xml, $verbose = null) {
// use libxm to suppress error reports for dom..
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    if (!$dom->loadXML(trim($xml))) {
      if ($errors = libxml_get_errors()) {
        foreach ($errors as $error) {
          $message .= $error->message;
        }

        verbose::log(WARNING, "error in xml " . $message);

// TODO return a proper error
        return false;
      }
    }

    $HTML = '';
    $prefix = "infomedia_";

// first get metadata headline, author, date etc.
    $xpath = new DOMXPath($dom);
    $query = "/NewsML/NewsItem/NewsComponent/NewsComponent/NewsLines/*";
    $nodelist = $xpath->query($query);

    foreach ($nodelist as $node) {
      if ($node->tagName == 'DateLine') {
        $HTML .= '<div class="' . $prefix . $node->tagName . '">' . helpFunc::danish_date("d M Y", strtotime($node->nodeValue)) . '</div>' . "\n";
      } else {
        $HTML .= '<div class="' . $prefix . $node->tagName . '">' . $node->nodeValue . '</div>' . "\n";
      }
    }

// add name of provider (newspaper)
    $query = "/NewsML/NewsItem/NewsComponent/AdministrativeMetadata/Source/Party/@FormalName";
    $nodelist = $xpath->query($query);

    if ($nodelist && $paper = $nodelist->item(0)->nodeValue) {
      $HTML .= '<div class="' . $prefix . 'paper">' . $paper . '</div>' . "\n";
    }

// get the 'real' headline and sub-headline; and yes 'hedline' is the way it is
    $query = "/NewsML/NewsItem/NewsComponent/NewsComponent/ContentItem/DataContent/nitf/body/body.head/hedline";
    $nodelist = $xpath->query($query);

    foreach ($nodelist as $node) {
      $HTML .= '<div class="' . $prefix . $node->tagName . '">' . trim($node->nodeValue) . '</div>' . "\n";
    }

// get all block-elements
    $query = "/NewsML/NewsItem/NewsComponent/NewsComponent/ContentItem/DataContent/nitf/body/body.content/block";
    $nodelist = $xpath->query($query);
    $HTML .= '<div class="' . $prefix . 'text">' . "\n";

    // dump childnodes of block; it is already html-format
    foreach ($nodelist as $block) {
      foreach ($block->childNodes as $node) {
        $HTML .= $dom->saveXML($node);
      }
    }

    $HTML .= '</div>' . "\n";

// add logo and diclaimer
    $HTML .= '<div class="infomedia_logo">' . "\n";
    $HTML .= '<img src="infomedia_logo.gif" alt="logo"/>' . "\n";
    $HTML .= '<p>Alt materiale i Infomedia er omfattet af lov om ophavsret og må ikke kopieres uden særlig tilladelse.</p>' . "\n";
    $HTML .= '</div>';
    return $HTML;
  }
}
?>
