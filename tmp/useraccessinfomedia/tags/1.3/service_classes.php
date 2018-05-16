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

// required as base-class for webservice
require_once("OLS_class_lib/webServiceServer_class.php");
// required for remote calls
require_once("OLS_class_lib/curl_class.php");
// required for database call
require_once("OLS_class_lib/oci_class.php");
// required for logging
require_once("OLS_class_lib/verbose_class.php");
// required for Zsearch
require_once("includes/search_func.phpi");
// required for mapping objects to xml
require_once("OLS_class_lib/xml_func_class.php");
// require for checking ip-range
require_once("OLS_class_lib/ip_class.php");


class infomediaWS extends webServiceServer
{
  private $operation;
  // protected $output_type;

  private $error_enum=array("service_not_licensed"
			    ,"service_unavailable"
			    ,"library_not_found"
			    ,"error_in_request");
  /** 
      Constructor
   */
  public function  __construct($inifile)
  {
    parent::__construct($inifile);
    $this->watch->start("InfomediaWS");
  }

  public function __destruct()
  {
    $this->watch->stop("InfomediaWS");
    //$this->verbose->log(TIMER, $this->watch->dump());
    verbose::log(TIMER, $this->watch->dump());
    parent::__destruct();
  }
  
  public function checkArticle($params)
  {
     // prepare response-object
    $response_xmlobj->checkArticleResponse->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia"; 

    $credentials=$this->get_credentials($params);
    //$this->verbose->log(STAT,"biblioteksnummer:".$credentials['librarycode']);
    verbose::log(STAT,"biblioteksnummer:".$credentials['librarycode']);
    $error=null;
    //   if( !$this->authenticate($credentials,$message) )
    // $error=$message;

    //authentication was succesfull; continue

    // set ccl for zsearch
    if( !$error )
      {
	$ccl=$this->get_article_ccl($params->articleIdentifier->_value->faust);
	if( empty($ccl) )
	  $error="error_in_request";
      }
    
    if( $error )
      return $this->set_error("checkArticleResponse",$response_xmlobj,$error);    

    $this->watch->start("NEP");
    $articles = NEP::get_articles_one_by_one($ccl, $this->verbose);
    $this->watch->stop("NEP");
        
    foreach( $articles as $article )
      $response_xmlobj->checkArticleResponse->_value->checkArticleResponseDetails[]=$this->article_details($article);	


    return $response_xmlobj;
  }

  public function getArticle($params)
  {

    $response_xmlobj->getArticleResponse->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia"; 

    $credentials=$this->get_credentials($params);
    
    $error=null;
    //authenticate
		$dbc_intervals = $this->config->get_value("DBC","ip_ranges");
 		if( ($credentials['userId'] <> 'infomedia_fra_netpunkt') && !(ip_func::ip_in_interval($_SERVER['REMOTE_ADDR'], $dbc_intervals)) && !$this->authenticate($credentials,$message) )
      $error=$message;

    // set ccl for zsearch
    if( !$error && $params->articleIdentifier->_value->faust)
      {
	$ccl=$this->get_article_ccl($params->articleIdentifier->_value->faust);
	if( empty($ccl) )
	  $error="error_in_request";
	
	if( $error )
	  return $this->set_error("getArticleResponse",$response_xmlobj,$error);  

	// get the articles from NEP
	$this->watch->start("NEP");
	$articles = NEP::get_articles_one_by_one($ccl, $this->verbose);
	$this->watch->stop("NEP");
      }
    

    elseif( !$error && $file=$params->articleIdentifier->_value->file )
      {
	
	if( is_array($file) )
	  {
	   
	  foreach( $file as $key=>$val )
	    $articles[]=$this->get_article_from_file($val->_value);
	  }
	else
	   $articles[]=$this->get_article_from_file($file->_value);

      }
    elseif( $error )
      return $this->set_error("getArticleResponse",$response_xmlobj,$error); 


    
     // get text for article from infomedia-webservice. 
    foreach( $articles as $article )
      {	
	//	$details=$this->article_details($article);

	if( $article->found && $article->links )
	  foreach( $article->links as $link )
	    {
	      
	      $details=$this->article_details($article);
	    // double check for link_name. article-class only adds links from infomedia, but that might change...
	    if( $link->link_name=="infomedia" )
	      {
		$this->watch->start("INFOMEDIA");
		if( $text=infomedia_webservice::get_article_html($this->config, $link->link_id, $this->verbose) )
		  {	
		    //$this->verbose->log( STAT,"hent::bib_nr:".$credentials['libraryCode']." brugerid:".$credentials['userId']." idnr:".trim($article->idnr));
		    verbose::log( STAT,"hent::bib_nr:".$credentials['libraryCode']." brugerid:".$credentials['userId']." idnr:".trim($article->idnr));
		    $article->text=$text;
		    // $article->text=$text;
		    $details->_value->imArticle->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
		    $details->_value->articleIdentifier->_value=urldecode($link->link_id);
		    $details->_value->imArticle->_cdata="yes";
		    $details->_value->imArticle->_value=$article->text;
		  }
		else
		  {
		    // article was not found on infomedia; log
		    $details->_value->articleVerified->_value="false";
		    verbose::log(WARNING,urldecode($link->link_id)." was not found on infomedia");
		    //$this->verbose->log(WARNING,urldecode($link->link_id)." was not found on infomedia");

		  }
		 $this->watch->stop("INFOMEDIA");
		// text for an article has been found. If the request was for óne specific article thats allright.
		 //	break;
		 $response_xmlobj->getArticleResponse->_value->getArticleResponseDetails[]=$details;	
		 unset($details);
	      }    
	    
	    }
	
      }

   
    return $response_xmlobj;
  }

  public function checkReview($params)
  {
    //print_r($params);
    $response_xmlobj->checkReviewResponse->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    
    $credentials=$this->get_credentials($params);

    $error=null;

    // TODO check if i may disable authentification for checkReview and checkArticle
    // if( !$this->authenticate($credentials,$message) )
    //  $error=$message;

    
    if( !$error )
      {
	$ccl=$this->get_work_ccl($params->workIdentifier->_value);
	if( empty($ccl) )
	  $error="error_in_request ";
      }
  
    if( $error )
      return $this->set_error("checkReviewResponse",$response_xmlobj,$error);

    $this->watch->start("NEP");
    $articles = NEP::get_articles_one_by_one($ccl);
    $this->watch->stop("NEP");
     
    foreach( $articles as $article )
      $response_xmlobj->checkReviewResponse->_value->checkReviewResponseDetails[]=$this->review_details($article);
    
    return $response_xmlobj;
   
  }
  
  public function getReview($params)
  {

    // print_r($params);
    //exit;

    $response_xmlobj->getReviewResponse->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";

    $credentials=$this->get_credentials($params);
    
    $error=null;
    if( !$this->authenticate($credentials,$message) )
      $error=$message;
    
    if( !$error )
      {
	$ccl=$this->get_work_ccl($params->workIdentifier->_value);
	if( empty($ccl) )
	  $error="error_in_request";
      }


    if( $error )
      return $this->set_error("getReviewResponse",$response_xmlobj,$error);
   
    $this->watch->start("NEP");
    $articles = NEP::get_articles_one_by_one($ccl,$this->verbose);
    $this->watch->stop("NEP");
    

    if( is_array($articles) )
      {
	
//	print_r($articles);
//	exit;

	foreach( $articles as $article )
	  {
	    if( $article->found && $article->links )// article might be found in NEP but not on infomedia
	      {
		$this->watch->start("INFOMEDIA");
		foreach( $article->links as $link )
		  {
		    
		    if( $text=infomedia_webservice::get_article_html($this->config, $link->link_id, $this->verbose) )
		      {

			if( $identifier=$params->workIdentifier->_value->isbn->_value)
			  ;
			else
			  $identifier=$params->workIdentifier->_value->faust->_value;			  
			// log for statistics
			//$this->verbose->log( STAT,"hent::bib_nr:".$credentials['libraryCode']." brugerid:".$credentials['userId']." idnr:".trim($article->idnr) );
			verbose::log( STAT,"hent::bib_nr:".$credentials['libraryCode']." brugerid:".$credentials['userId']." idnr:".trim($article->idnr) );

			$detail=$this->review_details($article);
			$detail->_value->workIdentifier->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
			$detail->_value->workIdentifier->_value=urldecode($link->link_id);
			$article->text=$text;
			$detail->_value->imArticle->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
			//	$detail->_value->imArticle->_cdata=true;
			$detail->_value->imArticle->_value=$article->text;
			$response_xmlobj->getReviewResponse->_value->getReviewResponseDetails[]=$detail;
		      }
		    else
		      {
			// article was on nep but was not found on infomedia; log
			verbose::log(WARNING,urldecode($link->link_id)." was not found on infomedia");
			//$this->verbose->log(WARNING,urldecode($link->link_id)." was not found on infomedia");
		      }
		   
		  }
		$this->watch->stop("INFOMEDIA");
		
	      }
	    else
	      $response_xmlobj->getReviewResponse->_value->getReviewResponseDetails[]=$details;
	  }
      }

     return $response_xmlobj;
  }

  private function set_error($action,$obj,$message)
  {   
    
    $obj->$action->_value->error->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    $obj->$action->_value->error->_value=$message;
    
    return $obj;
  }

  private function get_article_from_file($file)
  {

    $article=new article();
    $article->found=true;
    $link=new link();
    $link->link_name="infomedia";
    $link->link_id=$file;
    
    $article->links[]=$link;
   
    return $article;
  }

  private function get_article_ccl($faust)
  {
    if( is_array( $faust ) )
      foreach( $faust as $key=>$val )
	$ccl[]='id='.$val->_value;
    elseif( $faust )
      $ccl[] = 'id='.$faust->_value;

    return $ccl;
  }

  private function article_details($article)
  {
    $details->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->articleVerified->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->articleVerified->_value="false";
    if( $article->found && $article->links)
      {
      foreach( $article->links as $link )
	if( $link->link_name=="infomedia" )
	  {
	    $details->_value->articleVerified->_value="true";
	    $details->_value->articleIdentifier->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
	    $details->_value->articleIdentifier->_value=urldecode($link->link_id);

	    break;
	  }
      }
    else
      {
	$details->_value->articleIdentifier->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
	$details->_value->articleIdentifier->_value=trim($article->idnr);
      }
 
    return $details;
  }
    
  private function get_work_ccl($work)
  { 
    if( $faust=$work->faust )
      {
	if( is_array( $faust ) )
	  foreach( $faust as $key=>$val )
	    $ccl[]='id='.$val->_value;
	elseif( $faust )
	  $ccl[] = 'id='.$faust->_value;
      }
    if( $isbn=$work->isbn )
      {
	if( is_array( $isbn ) )
	  foreach( $isbn as $key=>$val )
	    $ccl[]='is='.$val->_value;
	elseif( $isbn )
	  $ccl[] = 'is='.$isbn->_value;
      }
    return $ccl;
  }

  private function get_credentials($params)
  {
    $credentials['libraryCode']=$params->libraryCode->_value;
    $credentials['userPinCode']=$params->userPinCode->_value;
    $credentials['userId']=$params->userId->_value;
    
    $credentials['ip']=$params->userIp->_value;

    return $credentials;
  } 
 
  private function review_details($article)
  {
    // $details=new stdClass();
    
    $details->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->workIdentifier->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    $details->_value->workIdentifier->_value=$article->idnr;
    $details->_value->reviewsCount->_namespace="http://oss.dbc.dk/ns/useraccessinfomedia";
    if( $article->found && $article->links)
      $details->_value->reviewsCount->_value=count($article->links);
    else
      $details->_value->reviewsCount->_value=0;

    return $details;
  }
  
  private function authenticate($credentials,&$message)
  {
    // pjo 21-09-10 if ip is in range 195.231.241.129 - 195.231.241.159
    // it comes from SkoDa and is allowed
    if( $ip = $credentials['ip'] )
      {
	$intervals = $this->config->get_value("SkoDa","ip_ranges");
	//	print_r($intervals);
	if( ip_func::ip_in_interval($ip, $intervals) )
	  {
	    
	    return true;
	  }
      }
      
    
    $flag=true;
    // borrower-check
    $this->watch->start("borchk");  
    $reply = borchk::check_borrower($this->config,$credentials);

    $this->watch->stop("borchk");
    if( strtolower($reply) != "ok" )
      {
	 $flag=false;
	 $message=$reply;
      }
    // library-check
    if( $flag )
      {
	$this->watch->start("FORS");
	if( ! FORS::authenticate($this->config,$credentials['libraryCode']) )
	  {
	    $flag=false;
	    $message = $this->error_enum[1];
	  }
	$this->watch->stop("FORS");
      }
    return $flag;
  }

 
}

/** 
    Handles requests to borchk webservice 
*/
class borchk
{   
  public static function check_borrower($config,$credentials)
  {
    $BORCHK = $config->get_section("BORCHK");

    if(! $params = self::get_url_parameters($credentials) )
      return "error_in_request";
      

    $url=$BORCHK['url'].$BORCHK['action'].$params;

    // use curl_class for borchk
    $curl=new curl();

    $curl->set_url($url);
    $xml = $curl->get();
    
    //echo $xml;
    //    exit;

    if( $errormessage = helpFunc::check_curl($curl) )
      {
	return $errormessage;
      }
    
    //return $xml;
    $dom = new DOMDocument();
    $dom -> loadXML($xml);


    $nodelist = $dom->getElementsByTagName('requestStatus');
    // return status for the request
    $message = $nodelist->item(0)->nodeValue;
    
    return $message;
  }

  private static function get_url_parameters($credentials)
  {
    if( !$credentials['userPinCode'] && !$credentials['userId'] )
      return false;

    $ret.='&libraryCode=DK-'.$credentials['libraryCode'];
    $ret.='&userId='.$credentials['userId'];
    $ret.='&userPincode='.$credentials['userPinCode'];

    return $ret;
  }  
}

/** *
    Handles requests to FORS database
 */
class FORS
{
  // TODO cache
   public static function authenticate($config,$group,$verbose=null)
  {

//	return true;
       
    $fors = $config->get_section("FORS");
   
    if( !$group )
      {	

      return false;
      }

     if( self::check_in_database($fors,"netpunkt",$group,$verbose) )
	return true;

    return false;  
  }

  private static function check_in_database($fors,$user,$group,$verbose=null)
  {   
 
    $oci = new Oci($fors['FORS_USER'], $fors['FORS_PASS'], $fors['FORS_DB']);
    if ($oci->connect()) 
      {
	$oci->bind("bind_user", $user);
	$oci->bind("bind_group", $group);
	$oci->set_query("SELECT userids.userid, userids.login, userids.state, crypttype, password
               FROM logins_logingroup, userids
               WHERE userids.userid = logins_logingroup.userid
               AND (administratorflag = 0 OR administratorflag IS NULL)
               AND userids.login = :bind_user
               AND groupname = :bind_group");
	$login = $oci->fetch_into_assoc();
	
	if ($userid = $login["USERID"]) {	
	  $login["rights"] = FALSE;
	  $oci->bind("bind_userid", $userid);
	  if (TRUE)
	    $oci->set_query("SELECT t.functiontypeid, objecttypename2
                    FROM table(fors_pkg.fors_get_rights (:bind_userid)) t, map1
                    WHERE t.objectclassid = map1.objecttypeattr1
                    AND t.attr1id = map1.objecttypeattr2");
	  else
	    $oci->set_query("SELECT functiontypeid, objecttypename2
                  FROM access_rights, map1
                  WHERE access_rights.objecttypeattr1 = map1.objecttypeattr1
                  AND access_rights.objecttypeattr2 = map1.objecttypeattr2
                  AND userid = :bind_userid");
	  while ($rights = $oci->fetch_into_assoc()) 
	    if ($rights["OBJECTTYPENAME2"] == $fors['FORS_PRODUKT']) {
	      $login["rights"] = TRUE;
	      break;
	    }
	  
	}
	$oci->disconnect();
	
	if (!$login["rights"] || $login["STATE"] != "OK" )
	    return FALSE;

      } 
    else 
      {
	echo "TUTTUUTUT";
	echo $oci->get_error();
	exit;

	//echo "OCI-error: " . $oci->get_error();
	if( $verbose )
	  verbose(ERROR, "infomediaWS:: check_in_database OCI error: " . $oci->get_error());
	return FALSE;
      }
    return TRUE;
  }
  
}  


/**
   Handles requests to Zsearch for infomedia-articles
 */
class NEP
{
  
  /**
     Get articles one by one.. to 'remember' faustArticleId
   */
  public static function get_articles_one_by_one($ccl,$verbose=null)
  {
     // Prepare the search
    global $TARGET;// from include file : targets.php
    global $search;// array holds parameters and result for Zsearch

    unset($search['rpn']);
    $search = &$TARGET["Danbib"];
    $search["format"] = "a";
    $search["start"]=1;    
    $search["step"]=5;

    $articles=array();

    // do the search(es)
    foreach( $ccl as $key=>$val )
      {
	if( $record=self::zsearch($search,$val,$verbose) )
	  {
	    if( $article= self::parse_for_single_article($record) )
	      $articles[]=$article;
	    else
	      $articles[]=new article();		  
	  }
	else
	  $articles[]=new article();		  		
      }
    return $articles;    
  }

  private static function ccl_to_cachekey($ccl)
  {
    foreach($ccl as $key=>$ccl)
      $key.=$ccl;

    return $key;      
  }

  /** 
      Do a Zsearch for a single record with given ccl
   */
  private static function zsearch(&$search,&$ccl,$verbose)
  {
    unset($search['rpn']);
   
    $search['ccl']=$ccl;

    Zsearch($search);

    //   print_r($search);

    if( $search['error'] )
      {
	verbose::log(FATAL,"NEP::".$search['error']);
	//$verbose->log(FATAL,"NEP::".$search['error']);
	return false;
      }

    if( empty($search['records']) || $search['hits']==0 )
      return false;
      
    
    return $search['records'][1]['record'];
  }

  private static function parse_for_single_article($record)
  {
    //echo $record;

    try{$article=new article(utf8_encode($record)); }
    catch(Exception $e){return false;}
    
    return $article;
  }
}

/** 
    Construct an article from NEP::get_infomedia_article xml
*/
class article
{
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

  public function __construct($xml=null)
  {   
    if( $xml )
      {
	libxml_use_internal_errors(true);	
	

	$dom = new DOMDocument();   
	if( !$dom->loadXML($xml) )  
	  {
	  if( $errors = libxml_get_errors() )
	    {
	      foreach( $errors as $error )
	      	print_r( $error );
	      echo $xml;
	    }
	  return;   
	  }

	$this->found=true;
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
	foreach( $nodelist as $ref )                                                             
	  {                                                                                      
	    $link = new link();                                                                  
	    $link->link_name = $ref->getElementsByTagName('link_name')->item(0)->nodeValue;      
	    $link->link_id=$ref->getElementsByTagName('link_id')->item(0)->nodeValue;          
	    $link->link_replace=$ref->getElementsByTagName('link_replace')->item(0)->nodeValue;  
	    // only choose articles from infomedia.. or what?
	    // TODO check
	    if( $link->link_name=="infomedia" )
	      $this->links[]=$link;
	  }                      
	
	$this->idnr = $dom->getElementsByTagName('idnr')->item(0)->nodeValue;
	$this->ejer = $dom->getElementsByTagName('ejer')->item(0)->nodeValue;  
      } 
    else
      $this->found=false;
  }

  public function num_links()
  {
    return count($this->links);
  }
}  


/** Properties for article-links*/
class link
{
  public $link_name;
  public $link_id;  
  public $link_replace;
}     

/**
   Handles requests to infomedia
 */
class infomedia_webservice
{
  /**
     Get article-text as html from infomedia-webservice.
   */
  public static function get_article_html($config,$fileName,$verbose=null)
  {
    $info = $config->get_section("infomedia");
    $url = $info['webservice'].$info['GetArticleRaw'].$info['filepath'].urldecode($fileName);

        // TODO errorcheck
    $curl = new curl();
    $curl->set_url($url);
   
    // TESTING - proxy MUST be set in working copy
    $curl->set_proxy("phobos.dbc.dk:3128");
    $xml = $curl->get();

    if( $errormessage=helpFunc::check_curl($curl) )
      return false;

    return self::parse_for_html($xml);
  }

  /**
     Parse given infomedia-xml and extract relevant fields. Generate and return HTML
   */
  private static function parse_for_html(&$xml,$verbose=null)
  {
    // use libxm to suppress error reports for dom..
    libxml_use_internal_errors(true);
      
    $dom=new DOMDocument();
    if( !$dom->loadXML(trim($xml)) )
      //     return false;
      if( $errors=libxml_get_errors() )
	{
	  foreach( $errors as $error )
	    $message.=$error->message;

	  //	  if( $verbose )	
	    verbose::log(WARNING,"error in xml ".$message);//$xml);

	  // TODO return a proper error
	  return false;
	}
    
    $HTML;
    $prefix="infomedia_";
    
    // first get metadata headline,author,date etc.
    $xpath = new DOMXPath($dom);   
    $query = "/NewsML/NewsItem/NewsComponent/NewsComponent/NewsLines/*";

    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      {
	if( $node->tagName == 'DateLine' )
	  $HTML.='<div class="'.$prefix.$node->tagName.'">'.helpFunc::danish_date("d M Y",strtotime($node->nodeValue)).'</div>'."\n";
	else
	  $HTML.='<div class="'.$prefix.$node->tagName.'">'.$node->nodeValue.'</div>'."\n";
	//echo $node->tagName.":".$node->nodeValue."\n</br>";
      }

    // add name of provider (newspaper)
    $query = "/NewsML/NewsItem/NewsComponent/AdministrativeMetadata/Source/Party/@FormalName";
    $nodelist=$xpath->query($query);
    if( $nodelist && $paper = $nodelist->item(0)->nodeValue )
      $HTML.='<div class="'.$prefix.'paper">'.$paper.'</div>'."\n";

    // get the 'real' headline and sub-headline; and yes 'hedline' is the way it is
    $query ="/NewsML/NewsItem/NewsComponent/NewsComponent/ContentItem/DataContent/nitf/body/body.head/hedline";
    $nodelist = $xpath->query($query);
    foreach($nodelist as $node)
      {
	$HTML.='<div class="'.$prefix.$node->tagName.'">'.trim($node->nodeValue).'</div>'."\n";
      }
    
    // get all block-elements
    $query =  "/NewsML/NewsItem/NewsComponent/NewsComponent/ContentItem/DataContent/nitf/body/body.content/block";
    $nodelist = $xpath->query($query);
    $HTML.='<div class="'.$prefix.'text">'."\n";
    
    // dump childnodes of block; it is already html-format 
    foreach($nodelist as $block)
      foreach($block->childNodes as $node)
      $HTML.= $dom->saveXML($node);
    
    $HTML.='</div>'."\n";

    // add logo and diclaimer
    $HTML.='<div class="infomedia_logo">'."\n";
    $HTML.='<img src="infomedia_logo.gif" alt="logo"/>'."\n";
    $HTML.='<p>Alt materiale i Infomedia er omfattet af lov om ophavsret og må ikke kopieres uden særlig tilladelse.</p>'."\n";
    $HTML.='</div>';
     // return "<![CDATA[".$HTML."]]>";    


    return $HTML;
  }
}

/**
   Help functions 
 */
class helpFunc
{

  
  public static function check_curl($curl)
  {
    // error-check curl
    $status=$curl->get_status();
    // check http-code
    if( $status['http_code'] > 400 )
      {
	//	var_dump($curl);
	//exit;
	return $status['http_code'];
      }

    // check curl error field
    if( $status['error'] )
      return $status['error'];

    return "";
  }

  public static function danish_date($format,$time)
  {
    $date=date($format,$time);
    $YMH=date_parse($date);

    $months=Array(
    "",
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

    $ret=$YMH['day'].'.'.' '.$months[$YMH['month']].' '.$YMH['year'];
    return $ret;
  }
 
}
?>
