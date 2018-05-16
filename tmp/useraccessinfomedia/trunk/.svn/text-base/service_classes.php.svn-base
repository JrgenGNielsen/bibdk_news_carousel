<?php
/**
 *
 * This file is part of OpenLibrary.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * OpenLibrary is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenLibrary is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with OpenLibrary.  If not, see <http://www.gnu.org/licenses/>.
*/


// switch for new api.
//NOTICE:
//choose infomedia_swagger_class.php for new api
//choose infomedia_webservice_class for old api
define('INFOMEDIA_CLASS', 'infomedia_swagger_class.php');
// require for retrieving articles from infomedia
require_once(INFOMEDIA_CLASS);

// required as base-class for webservice
require_once("OLS_class_lib/webServiceServer_class.php");
// required for remote calls
require_once("OLS_class_lib/curl_class.php");
// required for logging
require_once("OLS_class_lib/verbose_json_class.php");
// required for mapping objects to xml
require_once("OLS_class_lib/xml_func_class.php");
// require for checking ip-range
require_once("OLS_class_lib/ip_class.php");

class infomediaWS extends webServiceServer {
  public static $NOT_LICENSED = 'service_not_licensed';
  public static $UNAVAILABLE = 'service_unavailable';
  public static $NOT_FOUND = 'library_not_found';
  public static $ERROR = 'error_in_request';

  /**
      Constructor
   */
  public function  __construct($inifile) {

    parent::__construct($inifile);
    $this->watch->start("InfomediaWS");
  }

  public function __destruct() {
    $this->watch->stop("InfomediaWS");
    verboseJson::log(TIMER, $this->watch->dump());
    parent::__destruct();
  }
  public function getArticle($params) {
    $response_xmlobj = new stdClass();
    $response_xmlobj->getArticleResponse = new stdClass();
    $response_xmlobj->getArticleResponse->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $credentials = $this->get_credentials($params);

    $error = null;
    //authenticate
    $dbc_intervals = $this->config->get_value("DBC", "ip_ranges");
    // @TODO remove 'infomedia_fra_netpunkt' - use ip-access instead
    if (($credentials['userId'] <> 'infomedia_fra_netpunkt') && !(ip_func::ip_in_interval($_SERVER['REMOTE_ADDR'], $dbc_intervals)) && !$this->authenticate($credentials, $message))
      $error = $message;

    // get articleids from opensearch
    if (!$error && $params->articleIdentifier->_value->faust) {
      // get articles from opensearch
      $this->watch->start("OPENSEARCH");
      $articles = OS::getArticleIds($params->articleIdentifier->_value->faust, $this->config->get_section('OPENSEARCH'));
      $this->watch->stop("OPENSEARCH");
    }

    $file = isset($params->articleIdentifier->_value->file) ?
      $params->articleIdentifier->_value->file : FALSE;

    if(is_string($articles)){
      $error = $articles;
    }
    elseif (!$error && $file) {
      if (is_array($file)) {
        foreach ($file as $key => $val) {
          $articles[] = $this->get_article_from_file($val->_value);
        }
      }
      else
        $articles[] = $this->get_article_from_file($file->_value);
    }
    elseif ($error) {
      return $this->set_error("getArticleResponse", $response_xmlobj, $error);
    }

    // get text for article from infomedia-webservice.
    foreach ($articles as $article) {
      $details = $this->getArticleDetails($article, $credentials);
      $response_xmlobj->getArticleResponse->_value->getArticleResponseDetails[] = $details;
    }

    return $response_xmlobj;
  }

  public function getReview($params) {
    $response_xmlobj = new stdClass();
    $response_xmlobj->getReviewResponse->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $credentials = $this->get_credentials($params);
    $error = null;

    if (!$this->authenticate($credentials, $message))
      $error = $message;

    if ($params->workIdentifier->_value) {
      if(isset($params->workIdentifier->_value->isbn)){
        $workid = $params->workIdentifier->_value->isbn;
        $type = 'isbn';
      }
      elseif (isset($params->workIdentifier->_value->faust)){
        $workid = $params->workIdentifier->_value->faust;
        $type = 'faust';
      }

      // get articles from opensearch
      $this->watch->start("OPENSEARCH");
      $articles = OS::getArticleIds($workid, $this->config->get_section('OPENSEARCH'), $type);
      $this->watch->stop("OPENSEARCH");
    }
    else{
      $error = self::$ERROR;
    }

    if (isset($error)) {
      return $this->set_error("getReviewResponse", $response_xmlobj, $error);
    }

    if (is_array($articles)) {
      $review_count = count($articles);
      foreach ($articles as $article) {
        $details = $this->getReviewDetails($article, $credentials, $review_count);
        $response_xmlobj->getReviewResponse->_value->getReviewResponseDetails[] = $details;
      }
    }

    return $response_xmlobj;
  }

  private function getReviewDetails($article, $credentials, $count){
    $article_details = array();
    if ($article->found && $article->links) {
      foreach ($article->links as $link) {
        $details = $this->review_details($article, $count);

        // double check for link_name. article-class only adds links from infomedia, but that might change...
        if ($link->link_name == "infomedia") {

          $this->watch->start("INFOMEDIA");

          if ($text = infomedia_webservice::get_article_html($this->config, $link->link_id)) {
            verboseJson::log(STAT, "hent::bib_nr:" . $credentials['libraryCode'] . " brugerid:" . $credentials['userId'] . " idnr:" . trim($article->idnr));
            $article->text = $text;
            $details->_value->imArticle = new stdClass();
            $details->_value->imArticle->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
            $details->_value->workIdentifier->_value = urldecode($link->link_id);
            $details->_value->imArticle->_cdata = "yes";
            $details->_value->imArticle->_value = $article->text;
          }
          else {
            // article was not found on infomedia; log
            $details->_value->articleVerified->_value = "false";
            verboseJson::log(WARNING, urldecode($link->link_id) . " was not found on infomedia");
          }

          $this->watch->stop("INFOMEDIA");
          $article_details = $details;
          unset($details);
        }
      }
    }
    return $article_details;
  }

  /*private function review_details($article, $count){
    $details = new stdClass();
    $details->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value = new stdClass();

    $details->_value->articleIdentifier = new stdClass();
    if ($article->found && $article->links) {
      foreach ($article->links as $link) {
        if ($link->link_name == "infomedia") {
          $details->_value->reviewsCount->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
          $details->_value->reviewsCount->_value = $count;
          $details->_value->workIdentifier->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
          $details->_value->workIdentifier->_value = urldecode($link->link_id);
          break;
        }
      }
    }
    else {
      $details->_value->workIdentifier->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
      $details->_value->workIdentifier->_value = trim($article->idnr);
    }

    return $details;
  }*/

  private function getArticleDetails($article, $credentials){
    $article_details = array();
    if ($article->found && $article->links) {
      foreach ($article->links as $link) {
        $details = $this->article_details($article);

        // double check for link_name. article-class only adds links from infomedia, but that might change...
        if ($link->link_name == "infomedia") {

          $this->watch->start("INFOMEDIA");

          if ($text = infomedia_webservice::get_article_html($this->config, $link->link_id)) {
            verboseJson::log(STAT, "hent::bib_nr:" . $credentials['libraryCode'] . " brugerid:" . $credentials['userId'] . " idnr:" . trim($article->idnr));
            $article->text = $text;
            $details->_value->imArticle = new stdClass();
            $details->_value->imArticle->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
            $details->_value->articleIdentifier->_value = urldecode($link->link_id);
            $details->_value->imArticle->_cdata = "yes";
            $details->_value->imArticle->_value = $article->text;
          }
          else {
            // article was not found on infomedia; log
            $details->_value->articleVerified->_value = "false";
            verboseJson::log(WARNING, urldecode($link->link_id) . " was not found on infomedia");
          }

          $this->watch->stop("INFOMEDIA");
          $article_details = $details;
          unset($details);
        }
      }
    }
    return $article_details;
  }

  private function set_error($action, $obj, $message) {
    $obj = new stdClass();
    $obj->$action = new stdClass();
    $obj->$action->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $obj->$action->_value = new stdClass();
    $obj->$action->_value->error = new stdClass();
    $obj->$action->_value->error->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $obj->$action->_value->error->_value = $message;
    return $obj;
  }

  private function get_article_from_file($file) {
    $article = new article();
    $article->found = true;
    $link = new link();
    $link->link_name = "infomedia";
    $link->link_id = $file;
    $article->links[] = $link;
    return $article;
  }

  private function article_details($article) {
    $details = new stdClass();
    $details->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value = new stdClass();
    $details->_value->articleVerified = new stdClass();
    $details->_value->articleVerified->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->articleVerified->_value = "false";
    $details->_value->articleIdentifier = new stdClass();
    if ($article->found && $article->links) {
      foreach ($article->links as $link) {
          if ($link->link_name == "infomedia") {
          $details->_value->articleVerified->_value = "true";
          $details->_value->articleIdentifier->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
          $details->_value->articleIdentifier->_value = urldecode($link->link_id);
          break;
        }
      }
    }
    else {
      $details->_value->articleIdentifier->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
      $details->_value->articleIdentifier->_value = trim($article->idnr);
    }

    return $details;
  }

  private function get_credentials($params) {
    $credentials['libraryCode'] = $params->libraryCode->_value;
    $credentials['userPinCode'] = isset($params->userPinCode->_value) ? $params->userPinCode->_value : '';
    $credentials['userId'] = $params->userId->_value;
    $credentials['ip'] = isset($params->userIp->_value) ? $params->userIp->_value : '';
    return $credentials;
  }

  private function review_details($article, $count) {
    $details = new stdClass();
    $details->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->workIdentifier->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->reviewsCount->_namespace = "http://oss.dbc.dk/ns/useraccessinfomedia";

    if ($article->found && $article->links)
      $details->_value->reviewsCount->_value = $count;
    else
      $details->_value->reviewsCount->_value = 0;

    return $details;
  }

  private function authenticate($credentials, &$message) {

    // pjo 21-09-10 if ip is in range 195.231.241.129 - 195.231.241.159
    // it comes from SkoDa and is allowed
    if ($ip = $credentials['ip']) {
      $intervals = $this->config->get_value("SkoDa", "ip_ranges");

      if (ip_func::ip_in_interval($ip, $intervals)) {
        return true;
      }
    }

    $flag = true;
    // borrower-check
    $this->watch->start("borchk");
    $reply = borchk::check_borrower($this->config, $credentials);
    $this->watch->stop("borchk");

    if (strtolower($reply) != "ok") {
      $flag = false;
      $message = $reply;
    }

    // library-check
    if ($flag) {
      $this->watch->start("FORS");

      if (! FORS::authenticate($this->config, $credentials['libraryCode'])) {
        $flag = false;
        $message = self::$NOT_LICENSED;
      }

      $this->watch->stop("FORS");
    }

    return $flag;
  }
}


/**
    Handles requests to borchk webservice
*/
class borchk {
  public static function check_borrower($config, $credentials) {
    $BORCHK = $config->get_section("BORCHK");

    if (! $params = self::get_url_parameters($credentials))
      return "error_in_request";

    $url = $BORCHK['url'] . $BORCHK['action'] . $params;

    // use curl_class for borchk
    $curl = new curl();
    $curl->set_url($url);
    $xml = $curl->get();

    if ($errormessage = helpFunc::check_curl($curl)) {
      return $errormessage;
    }

    $dom = new DOMDocument();
    $dom -> loadXML($xml);
    $nodelist = $dom->getElementsByTagName('requestStatus');
    // return status for the request
    $message = $nodelist->item(0)->nodeValue;
    return $message;
  }

  private static function get_url_parameters($credentials) {
    if (!$credentials['userId'])
      return false;

    $ret = '';

    $ret .= '&libraryCode=DK-' . $credentials['libraryCode'];
    $ret .= '&userId=' . $credentials['userId'];
    $ret .= '&userPincode=' . $credentials['userPinCode'];
    return $ret;
  }
}

/** *
    Handles requests to FORS webservice
 *
 */
class FORS {


  // TODO cache
  public static function authenticate($config, $group) {

    /** @var inifile $config */
    $fors = $config->get_section("FORSRIGHTS");
    if (!$group) {
      return false;
    }

    if (self::forsAuthenticate($fors, $group)) {
      return true;
    }

    return false;
  }

  private static function forsAuthenticate($defaults, $group) {
    $url = $defaults['url'];
    $params = $defaults['defaults'];
    $query = http_build_query($params);
    $url = $url . $query;

    $curl = new curl();
    $curl->set_url($url);
    $xml = $curl->get();
    $customers = self::parseForsList($xml);
    if (empty($customers)) {
      return FALSE;
    }
    if (in_array($group, $customers)) {
      return TRUE;
    }
    return FALSE;
  }

  private static function parseForsList($xml) {

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    if (!$dom->loadXML($xml)) {
      if ($errors = libxml_get_errors()) {
        foreach ($errors as $error) {
          // @TODO set error for user
          print_r($error);
        }
        echo $xml;
      }
      return false;
    }
    $customers = array();
    $nodelist = $dom->getElementsByTagName("customer");
    foreach ($nodelist as $node) {
      $customers[] = $node->nodeValue;;
    }
    return $customers;
  }
}


/**
 * ********** NOTICE this class is not in use yet. when we get infomediaID
 * in url this class should replace NEP
 *
 * Class OS
 */
class OS {
  /**
   * @param string $identifier
   *   The id to look for
   * @param array $defaults
   *   config settings from
   * @param string $type
   *   what type of identifier (faust or isbn)
   * @return array|string
   */
  public static function getArticleIds($identifier, $defaults, $type='faust') {
    if(!is_array($identifier)){
      $identifier = array($identifier);
    }

    $url = $defaults['url'];
    $params = $defaults['defaults'];
    $params['query'] = self::generateQuery($identifier, $type);

    $query =  http_build_query($params);
    $url = $url . $query;

    $curl = new curl();
    $curl->set_url($url);
    $response = $curl->get();

    $message = helpFunc::check_curl($curl);
    if(!empty($message)){
      // Log
      verboseJson::log(ERROR, $url . ': returned curl error: ' . $message);
      // set a message for user
      return 'Internal error. Code: ' . $message . ' - please contact administrator if problem persists';
    }

    $response = json_decode($response);
    return self::parseForArticleIds($response);
  }

  private static function generateQuery(array $identifiers, $type){
    $query = '';
    foreach($identifiers as $identifier){
      if(strlen($query > 1)){
        $query .= ' OR ';
      }
      if($type === 'faust') {
        $query .= 'rec.id=' . $identifier->_value;
      }
      elseif ($type === 'isbn'){
        $query .= 'term.isbn=' . $identifier->_value;
      }
    }
    return $query;
  }

  private static function parseForArticleIds(stdClass $response){
    $articles = array();
    $searchresults = $response->searchResponse->result->searchResult;
    if(!is_array($searchresults)){
      $searchresults = array($searchresults);
    }

    foreach($searchresults as $searchresult){
      $relations = $searchresult->collection->object[0]->relations->relation;
      $more_articles = self::parseRelations($relations);
      $articles = array_merge($articles, $more_articles);
    }
    return $articles;
  }

  private static function parseRelations($relations) {
    $articles = array();
    if(!is_array($relations)){
      $relations = array($relations);
    }

    foreach ($relations as $relation) {
      $type = $relation->relationType->{'$'};
      switch($type){
        case 'dbcaddi:hasReview':
          $reviews = self::parseReview($relation);
          $articles = array_merge($articles, $reviews);
          break;
        case 'dbcaddi:hasOnlineAccess':
          $onliners = self::parseOnlineAccess($relation);
          $articles = array_merge($articles, $onliners);
          break;
        default:
          break;
      }
    }
    return $articles;
  }



  private static function parseOnlineAccess($relation) {
    $articles = array();
    $url = $relation->relationUri->{'$'};
    if(($articleID = self::parseUrlForInfomediaId($url)) !== FALSE){
      $articles[] = self::get_article_from_file($articleID);
    }
    return $articles;
  }

  private static function parseUrlForInfomediaId($url){
    $query = explode('&', $url);
    foreach ($query as $param) {
      if (strpos($param, 'infomediaId') !== FALSE) {
        $parts = explode('=', $param);
        $articleId = $parts[1];
        return $articleId;
      }
    }
    return FALSE;
  }

  private static function parseReview($relation) {
    $articles = array();
    $objects = isset($relation->relationObject->object->relations->relation) ?
      $relation->relationObject->object->relations->relation : array();
    foreach ($objects as $object) {
      $url = $object->relationUri->{'$'};
      if(($articleID = self::parseUrlForInfomediaId($url)) !== FALSE){
        $articles[] = self::get_article_from_file($articleID);
      }
    }
    return $articles;
  }

  private static function get_article_from_file($file) {
    $article = new article();
    $article->found = true;
    $link = new link();
    $link->link_name = "infomedia";
    $link->link_id = $file;
    $article->links[] = $link;
    return $article;
  }
}


/**
    Construct an article from NEP::get_infomedia_article xml
*/
class article {
  public $found;
  public $avis;
  public $mattype;
  public $fortit;
  public $kort;
  public $detaljeret;
  public $bestil;
  public $lokaliseringer;
  public $materiale;
  public $klausuleret;
  public $links;
  public $idnr;
  public $ejer;
  public $text;

  public function __construct($xml = null) {
    if ($xml) {
      libxml_use_internal_errors(true);
      $dom = new DOMDocument();

      if (!$dom->loadXML($xml)) {
        if ($errors = libxml_get_errors()) {
          foreach ($errors as $error) {
            print_r($error);
          }
          echo $xml;
        }

        return;
      }

      $this->found = true;
      $this->mattype = $dom->getElementsByTagName('mattype')->item(0)->nodeValue;
      $this->fortit = $dom->getElementsByTagName('fortit')->item(0)->nodeValue;
      $this->kort = $dom->getElementsByTagName('kort')->item(0)->nodeValue;
      $this->detaljeret = $dom->getElementsByTagName('detaljeret')->item(0)->nodeValue;
      $this->bestil = $dom->getElementsByTagName('bestil')->item(0)->nodeValue;
      $this->lokaliseringer = $dom->getElementsByTagName('lokaliseringer')->item(0)->nodeValue;
      $this->materiale = $dom->getElementsByTagName('materiale')->item(0)->nodeValue;
      $this->klausuleret = $dom->getElementsByTagName('klausuleret')->item(0)->nodeValue;
      // links is an array of link
      $nodelist = $dom->getElementsByTagName('link');

      foreach ($nodelist as $ref) {
        $link = new link();
        $link->link_name = $ref->getElementsByTagName('link_name')->item(0)->nodeValue;
        $link->link_id = $ref->getElementsByTagName('link_id')->item(0)->nodeValue;
        $link->link_replace = $ref->getElementsByTagName('link_replace')->item(0)->nodeValue;

        // only choose articles from infomedia.. or what?
        // TODO check
        if ($link->link_name == "infomedia")
          $this->links[] = $link;
      }

      $this->idnr = $dom->getElementsByTagName('idnr')->item(0)->nodeValue;
      $this->ejer = $dom->getElementsByTagName('ejer')->item(0)->nodeValue;
    }
    else
      $this->found = false;
  }

  public function num_links() {
    return count($this->links);
  }
}


/** Properties for article-links*/
class link {
  public $link_name;
  public $link_id;
  public $link_replace;
}


/**
   Help functions
 */
class helpFunc {
  public static function check_curl($curl) {
    // error-check curl
    $status = $curl->get_status();

    // check http-code
    if ($status['http_code'] > 400) {
      return $status['http_code'];
    }

    // check curl error field
    if ($status['error'])
      return $status['error'];

    return "";
  }

  public static function danish_date($format, $time) {
    $date = date($format, $time);
    $YMH = date_parse($date);
    $months = Array("",
                    "Januar",
                    "Februar",
                    "Marts",
                    "April",
                    "Maj",
                    "Juni",
                    "Juli",
                    "August",
                    "September",
                    "Oktober",
                    "November",
                    "December");
    $ret = $YMH['day'] . '.' . ' ' . $months[$YMH['month']] . ' ' . $YMH['year'];
    return $ret;
  }
}
?>
