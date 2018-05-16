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

require_once("ws_lib/curl_class.php");
// required for object-mapping
require_once("infomediaWS_classes.php");
require_once("ws_lib/xml_func_class.php");

define(URL,"http://vision.dbc.dk/~pjo/OpenLibrary/OpenInfomedia/trunk/");
?>

<style>
p
{
  font-size:0.9em;
}


div.infomedia_HeadLine
{
color:red;
font-size:2em;
}

div.infomedia_SubHeadLine
{
  font-weight:bold;
  margin-bottom:5px;
}
div.infomedia_ByLine
{
  float:left;
  padding-right:50px;
  font-size:0.75em;
}
div.infomedia_DateLine
{
  float:right;
  font-size:0.75em;
}

div.infomedia_text
{  
  clear:both;
}

div.wrap
{
  max-width:800px;
  border:1px dotted #CCCCCC;
  padding:5px;
}
div.infomedia_hedline
{
  clear:both;
  padding-top:5px;
  font-weight:bold;
  font-size:0.9em;
  border-top:1px dotted #CCCCCC;
}
div.select
{
  float:left;
}


</style>
<html>
<head>
<SCRIPT language="JavaScript1.2">
function pop(url)
{
testwindow= window.open(url, "mywindow","location=1,status=1,scrollbars=1,width=600,height=600");
//testwindow.moveTo(0,0);
}
</SCRIPT>
</head>
<body>
<div class='wrap'>
<?php
echo get_form();
?>
</div>
</body>
</html>

<?php
function get_form()
{
  $userId=$_POST['userId'];
  $userPinCode=$_POST['userPinCode'];
  $libraryCode=$_POST['libraryCode'];
  $faust=$_POST['faust'];
  $isbn=$_POST['isbn'];

  $ret= '
     <form method="post" action="example.php">
        faust</br>
        <input type="text" name="faust" value="'.(($faust)?$faust : '27882501' ).'"/>
        </br>
        userId</br>
        <input type="text" name="userId" value="'.(($userId)?$userId : '0019' ).'"> 
        </br> 
         userPinCode</br>
        <input type="text" name="userPinCode" value="'.(($userPinCode)?$userPinCode : '0019' ).'"> 
        </br>
        libraryCode</br>
        <input type="text" name="libraryCode" value="'.(($libraryCode)?$libraryCode : '718300' ).'"> 
<div class="select">
Action:</br>
<select name="action">
<option value="checkArticle"';
$action=$_POST["action"];
if( $action== "checkArticle" ){$ret.='SELECTED';}
$ret.='>checkArticle</option>';
$ret.='<option value="getArticle"';
if( $action== "getArticle" ){$ret.='SELECTED';}
$ret.='>getArticle</option>';
$ret.='<option value="checkReview"';
if( $action== "checkReview" ){$ret.='SELECTED';}
$ret.='>checkReview</option>';
$ret.='<option value="getReview"';
if( $action== "getReview" ){$ret.='SELECTED';}
$ret.='>getReview</option>';
$ret.='</select>';
$ret.='</div>';
$ret.='<div class="select">';
$ret.='Output:</br>';
$ret.='<select name="outputType">
<option value="SOAP"';
$type=$_POST["outputType"];
if( $type== "SOAP" ){$ret.='SELECTED';}
$ret.='>SOAP</option>';
$ret.='<option value="JSON"';
if( $type== "JSON" ){$ret.='SELECTED';}
$ret.='>JSON</option>';

$ret.='<option value="XML"';
if( $type== "XML" ){$ret.='SELECTED';}
$ret.='>XML</option>';

$ret.='</br>';

$ret.='</select>';
$ret.='</div>';
$ret.='<div style="clear:both"/>';
$ret.=' <input type="submit" name="post" value="  GO   "/>  
        </form>';
  if( $action == "getArticle" || $action=="getReview" )
    {
      // TODO set host!!
      //$operation='?operation=getArticle&faust=33750862&libraryCode=718300&userId=0019&userPinCode=0019&outputType=soap';
      $host=URL.'pop.php';
      $operation='?operation='.$action.'&faust='.$faust.'&libraryCode='.$libraryCode.'&userId='.$userId.'&userPinCode='.$userPinCode.'&outputType=xml';
      $url=$host.$operation;
      $ret.="<input type='button' value='View article(s)' id='view_but' style='float:right' onclick='javascript:pop(\"".$url."\")'/>";      
    }

$ret.='<textarea rows="25" cols="50" style="width:100%">';
$ret.=get_soap_result();
$ret.='</textarea></div>';
        
return $ret;

}

?>

<?php
function get_article($faust)
{
  // get xml from webservice
  $url=URL."server.php?operation=getArticle&faust=".$faust."&libraryCode=774000&userId=netpunkt&outputType=XML";


  $curl=new curl();
  $curl->set_url($url);
  $xml=$curl->get();

  $dom=new DOMDocument();
  if(!  $dom->loadXML($xml) )
    {
      return "";
      exit;
    }
 
  $data = $dom->getElementsByTagName('html')->item(0)->nodeValue;

  return $data;
}

function get_soap_result()
{
  /* $userId=$_POST['userId'];
  $userPinCode=$_POST['userPinCode'];
  $libraryCode=$_POST['libraryCode'];
  $fustArticleId=$_POST['faustArticleId'];*/

  $soap = soap_server::get_request();
  
  // return $soap;

  $curl=new curl();
  $curl->set_url(URL."server.php");

  if(! $action=$_POST['action'] )
    $action="getArticle";

 
  $curl->set_soap_action($action);
  $curl->set_post_xml($soap);

  //return htmlspecialchars( $curl->get() );
 
  /*  echo $soap;
  echo "\n";
  echo $curl->get();
  exit;*/
 

  return $curl->get();
}



class soap_server
{
  public static function get_soap_request($faust)
  {
    if (!$userId=$_POST['userId'] )
      $userId="netpunkt";

    if( !$userPinCode=$_POST['userPinCode'] )
      $userPinCode = "20Koster";

    if ( !$libraryCode=$_POST['libraryCode'] )
      $libraryCode = "010100";

    if( !$faust=$_POST['faust'] )
      $faust = "2788250";

    if( ! $outputType=$_POST['outputType'] )
      $outputType= "SOAP";
    
    $ret.=self::header();

    if(! $action=$_POST['action'] )
       $action="getArticle";

    switch( $action )
      {
      case "getArticle":
	$ret.='<uaim:getArticleRequest>'."\n";
	break;
      case "checkArticle":
	$ret.='<uaim:checkArticleRequest>'."\n";
	break;
      case "checkReview":
	$ret.='<uaim:checkReviewRequest>'."\n";
	break;	
      case "getReview":
	$ret.='<uaim:getReviewRequest>'."\n";
	break;	
      }

    $ret.='  <uaim:faust>'.$faust.'</uaim:faust>'."\n";
    // $ret.='  <uaim:faust>2788250</uaim:faust>'."\n";
    $ret.='  <uaim:libraryCode>'.$libraryCode.'</uaim:libraryCode>'."\n";
    $ret.='  <uaim:userId>'.$userId.'</uaim:userId>'."\n";
    $ret.='  <uaim:userPinCode>'.$userPinCode.'</uaim:userPinCode>'."\n";
    $ret.='  <uaim:outputType>'.$outputType.'</uaim:outputType>'."\n"; 
    //   $ret.='</uaim:getArticleRequest>'."\n";
    
 switch( $action )
      {
      case "getArticle":
	$ret.='</uaim:getArticleRequest>'."\n";
	break;
      case "checkArticle":
	$ret.='</uaim:checkArticleRequest>'."\n";
	break;
      case "checkReview":
	$ret.='</uaim:checkReviewRequest>'."\n";
	break;	
      case "getReview":
	$ret.='</uaim:getReviewRequest>'."\n";
	break;	
      }

    $ret.=self::footer();

    return $ret;
  }
  
  public static function get_request()
  {
    $ret.=self::header();

    $obj = self::set_request_object();
    $ret.= xml_func::object_to_xml($obj,"uaim");
    
    $ret.=self::footer();

    return $ret;
  }

  public static function header()
  {
    $ret.='<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $ret.='<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:uaim="http://oss.dbc.dk/ns/useraccessinfomedia" >'."\n";
    $ret.='<SOAP-ENV:Body>'."\n";
    
    return $ret;
  }

  public static function footer()
  {
    $ret.='</SOAP-ENV:Body>'."\n";
    $ret.='</SOAP-ENV:Envelope>'."\n";

    return $ret;    
  }
  
  private static function set_request_object()
  {
    $action = $_POST['action'];
    switch($action)
      {
      case "getArticle":
      case "checkArticle":      
	$request = new articleRequestType();
	$identifier = new articleIdentifierType();
	$identifier->faust = $_POST['faust'];
	$request->articleIdentifier = $identifier;
	if( $action == "getArticle" )
	  $obj = new getArticleRequest();
	else
	  $obj = new checkArticleRequest();
	break;
      case "getReview":
      case "checkReview":
	$request = new workRequestType();
	
	if( $_POST['faust'] )
	  {
	    $identifier = new workIdentifierType();
	    $identifier->faust = $_POST['faust'];
	    $request->workIdentifier[]=$identifier;
	  }
	if( $_POST['isbn'] )
	  {
	    $identifier = new workIdentifierType();
	    $identifier->isbn = $_POST['isbn'];
	    $request->workIdentifier[]=$identifier;
	  }

	if( $action == "getReview" )
	  $obj = new getReviewRequest();
	else
	  $obj = new checkReviewRequest();
	break;
      }
    
    // set fields for request
    $request->userId=$_POST['userId'];
    $request->userPinCode=$_POST['userPinCode'];
    $request->libraryCode=$_POST['libraryCode'];
    $request->outputType=$_POST['outputType'];
    $request->action= $action;
    
    $obj->request=$request;
    
    return $obj;
  }
}
?>
