<?php

/**
 * Class infomedia_swagger
 *
 * Handle infomedias new api
 */
class infomedia_webservice {
  /**
   * Get article-text as html from infomedia-webservice.
   * var $config
   */
  public static function get_article_html($config, $fileId, $try_again=FALSE, $count = 0) {
    /** @var $config inifile  * */
    $info = $config->get_section("infomedia_swagger");
    $url = $info['getArticle'] . self::decode_file_id($fileId);

    verbose::log(DEBUG, 'infomedia_swagger:: GET ARTICLE URL :' . $url);
    $curl = new curl();
    $curl->set_url($url);

    // get authorization token
    $token = self::authenticate($config, $try_again);
    $curl->set_option(CURLOPT_HTTPHEADER, array('Authorization:bearer ' . $token));

// TESTING - proxy MUST be set in working copy
    $proxy = $config->get_section('proxy');
    if ($proxy['domain_and_port']) {
      $curl->set_proxy($proxy['domain_and_port']);
    }

    $xml = $curl->get();

    if($count > 1) {
      verbose::log(ERROR, 'infomedia_swagger:: RECURSION BREAK');
      return FALSE;
    }

    $message = FALSE;
    $message = self::parse_json($xml);
    $reauthenticate = ($message == 'TRY AGAIN');
    if($reauthenticate){
      // NOTICE recursion  
        return self::get_article_html($config, $fileId, TRUE, ++$count);
    }
    if(!empty($message)){
      verbose::log(DEBUG, 'infomedia_swagger:: Message from infomedia : ' . $message);
    }

    $errormessage = '';
    if ($errormessage = helpFunc::check_curl($curl)) {
      verbose::log(ERROR, 'infomedia_swagger:: curl status is: ' . $errormessage);
      return false;
    }

    return self::parse_for_html($xml);
  }



 private static function parse_json($result) {
   $json = json_decode($result);
   // check if message is set
   if (isset($json->Message)) {
     if ($json->Message == 'Authorization has been denied for this request.') {
       verbose::log(STAT, 'infomedia_swagger::reauthenticate : ' . $json->Message);
       return 'TRY AGAIN';
     }
     // if there is a message infomedia has something to say - return it
     return $json->Message;
   }
   return FALSE;
 }

 /**
   * fileID is of the form INF\2007\09\26\e0b692db.xml
   * Get the last part of fileId
   * @param $fileId
   */
  private static function decode_file_id($fileId) {
    $id = urldecode($fileId);
    $parts = explode('\\', $id);
    $idPart = array_pop($parts);
    $part = explode('.', $idPart);
    return current($part);
  }


  /**
   * Parse given infomedia-xml and extract relevant fields. Generate and return HTML
   */
  private static function parse_for_html(&$json) {
    $result = json_decode($json);
    if ($result === FALSE) {
      verbose::log(ERROR, 'infomedia_swagger:: bad result: ' . $json);
      return FALSE;
    }

    $HTML = '';
    $prefix = "infomedia_";
    foreach ($result->Articles as $article) {
      // get subheadline (Heading)
      $text = isset($article->Heading) ? $article->Heading : '';
      $HTML .= '<div class="infomedia_HeadLine">' . $text . '</div>' . "\n";
      $HTML .= '<div class="infomedia_SubHeadLine">' . $text . '</div>' . "\n";
      // get byline (caption)
      $text = isset($article->Authors) ? implode($article->Authors, ',') : '';
      $HTML .= '<div class="infomedia_ByLine">' . $text . '</div>' . "\n";
      // get Dateline (PublishDate)
      $text = isset($article->PublishDate) ? helpFunc::danish_date("d M Y", strtotime($article->PublishDate)) : '';
      $HTML .= '<div class="infomedia_DateLine">' . $text . '</div>' . "\n";
      // get paper (Source)
      $text = isset($article->Source) ? $article->Source : '';
      $HTML .= '<div class="infomedia_paper">' . $text . '</div>' . "\n";
      // get headline (SubHeading)
      $text = isset($article->SubHeading) ? $article->SubHeading : '';
      $HTML .= '<div class="infomedia_hedline">' . $text . '</div>' . "\n";
      // get text (BodyText)
      $text = isset($article->BodyText) ? $article->BodyText : '';

      $HTML .= '<div class="infomedia_text">' . $text . '</div>' . "\n";

    }

// add logo and diclaimer
    $HTML .= '<div class="infomedia_logo">' . "\n";
    $HTML .= '<img src="infomedia_logo.gif" alt="logo"/>' . "\n";
    $HTML .= '<p>Alt materiale i Infomedia er omfattet af lov om ophavsret og må ikke kopieres uden særlig tilladelse.</p>' . "\n";
    $HTML .= '</div>';
    return $HTML;
  }

  /**
   * @param $config inifile
   */
  private static function authenticate($config, $try_again) {
    // use temp dir as cache folder
    $file = sys_get_temp_dir(). '/infomedia_swagger_token.txt';

    $token = @file_get_contents($file);
    if ($token === FALSE || $try_again) {
      // no token yet
      $swagger = $config->get_section('infomedia_swagger');
      /** @var $curl curl */
      $curl = new curl();

      $proxy = $config->get_section('proxy');
      if ($proxy['domain_and_port']) {
        $curl->set_proxy($proxy['domain_and_port']);
      }
      
      $curl->set_option(CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
      ));
      $curl->set_url($swagger['authenticate']);
      $post = array(
        'grant_type' => 'password',
        'username' => $swagger['user'],
        'password' => $swagger['pass']
      );
      $url_params = http_build_query($post);
      $curl->set_option(CURLOPT_POST, count($post));
      $curl->set_option(CURLOPT_POSTFIELDS, $url_params);

      $result = $curl->get();
      self::check_curl($curl);

      $token = self::parse_token($result);
      if($token){
        verbose::log(DEBUG, 'infomedia_swagger::cache token : ' . $token);
        file_put_contents($file, $token);
      }
    }

    return $token;

  }


  /**
   * @param $curl curl
   */
  private static function check_curl($curl) {
    $status = $curl->get_status();

    // check http_code
    if ($status['http_code'] != '200') {
      verbose::log(ERROR, 'infomedia_swagger::curl returned: ' . $status['http_code']);
      return FALSE;
    }

    //@TODO more checks
    return TRUE;

  }

  /**
   * @param $result
   */
  private static function parse_token($result) {
    $json = json_decode($result);
    // $result is json
    if ($json === FALSE) {
      verbose::log(ERROR, 'infomedia_swagger::could not parse json : ' . $result);
      return FALSE;
    }
    // check for errors
    if (isset($json->error)) {
      verbose::log(ERROR, 'infomedia_swagger::error on authenficate : ' . $json->error);
      return FALSE;
    }
    // hopefulle everything is good now
    if(!empty($json->access_token)){
      // all is good
      return $json->access_token;
    }
    else{
      // something is wrong
      verbose::log(ERROR, 'infomedia_swagger::no access token : ' . json_encode($json));
    }

    return FALSE;
  }
}
?>
