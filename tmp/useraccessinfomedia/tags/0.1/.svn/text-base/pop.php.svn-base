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
?>

<?php $style='
<style>
p
{
  font-size:0.9em;
}

input[type="text"]
{
display:block;
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


</style>';
?>
<html>
<head>
<script language="javascript">
  function show_tab(id)
{
  // hide all tabs
  var menu=document.getElementById('menu');
  var tabs=menu.getElementsByTagName('div');
  var length=tabs.length;
  for(i=0;i<tabs.length;i++)
    {
      test=tabs[i].id;
      div=document.getElementById(test);
      if( div.id == id )
	div.style.display="block";
    }
  //  var div = document.getElementById(id);
  //div.style.display='block';
}
</script>
<?php echo $style?>


</head>
<body>
<div class='wrap'>
<?php
$server = new infomedia_webservice();
$html=$server->html();
//echo $html[0];
//var_dump($server->html());
//echo $server->xml();
//var_dump($_GET);
echo $server->get_page();
?>
</div>
</body>
</html>
<?php

class infomedia_webservice
{	
  private $xpath;
  private $operation;
  public function __construct()
  {
    //  $operation='?operation=getArticle&faust=33750862&libraryCode=718300&userId=0019&userPinCode=0019&outputType=soap';
    $this->operation=$_GET["operation"];
    $WSurl='http://vision.dbc.dk/~pjo/webservices/infomediaWS/trunk/server.php';
    $operation='?operation='.$_GET["operation"].
      '&faust='.$_GET["faust"].
      '&libraryCode='.$_GET["libraryCode"].
      '&userId='.$_GET["userId"].
      '&userPinCode='.$_GET["userPinCode"].
      '&outputType='.$_GET["outputType"];
    // get xml from infomedia-webservice
    $url=$WSurl.$operation;	

    $curl=new curl();
    $curl->set_url($url);
    $xml=$curl->get();

    $dom=new DOMDocument();
    $dom->loadXML($xml);
    $this->xpath=new DOMXPath($dom);    
  }
  
  public function xml()
  {
    return $this->xpath->document->saveXML();
  }

  public function get_page()
  {
    $tabs=array();
    $divs=array();
    foreach( $this->html() as $key=>$val )
      {
	foreach($val as $id=>$html)
	  {
	    $tabs[]=$id;
	    $divs[]=$html;
	  }
      }

   
    /* foreach($tabs as $key=>$val)
       $ret.=$val."\n";*/
   

    $ret.="<div id='menu'>";
    foreach($divs as $key=>$val)
      $ret.=$val."\n";
    $ret.="</div>";
    return $ret;
  }
  
  // return an array of html
  public function html()
  {
    switch( $this->operation )
      {
      case "getArticle":
	$query="/getArticleResponse/getArticleResponseDetails/imArticle";
	break;
      case "getReview":
	$query="/getReviewResponse/getReviewResponseDetails/imArticle";
	break;
      }

    $nodes=$this->xpath->query($query);
    $ret = array();
    $index=0;
    foreach( $nodes as $node )
      {
	$ret[]=$this->get_html_struct($node->nodeValue,$index++);	
      }

    return $ret;
  }

  private function get_html_struct(&$html,$index)
  {
    $id='tab'.'_'.$index;
    preg_match('/(<div class="infomedia_HeadLine">)(.*)(<\/div>)/', $html, $regs);
    $click="<a onclick='javascript:show_tab(\"".$id."\")' style='cursor:pointer'>".$regs[2]."</a></div>";
    $ret[$click]='<div id='.$id.'>'.$html.'</div>';
    return $ret;
  }
}
?>
