<?php

//define("DEFAULT_HOST", "lakitre.dbc.dk:2105");
define("DEFAULT_HOST", "garove1.dbc.dk:2105");

//define("INFOMEDIA_HOST","lakitre.dbc.dk:21040");
define("INFOMEDIA_HOST","netpunkt-nep.dbc.dk:21040");
//global $TARGET;	// aht wget

$TARGET["infomedia"]= array(
  "host" => INFOMEDIA_HOST,
  "database" => "infomedia",
  "cclfields" => "infomedia.search",
  "formats" => array(
    "f1" => "xml/ems3",
    "f2" => "xml/ems3",
    "f3" => "xml/ems3",
    "f0" => "xml/ems3",
    "f01" => "xml/ems3"
  ),
  "start" =>1,
  "step" =>5,
  "fors_name" => "infomedia",
  "text_prefix" => "infomedia",
  // "info" => "infomedia_info",
  //"menu_name" => "Infomedia",
  "menu_color" => "#AC2313",
  "menu_link" => true,
  "logo" => "infomedia_logo.gif",
  "link" => array(
    "anchor" => '<a href=\'javascript:void(0)\' onClick=\'PopWinxy("_HREF_","popup",700,750,100,1)\'>_TEXT_</a>',
    "href" => 'pop_vis.php?target=infomedia&amp;ccl=id%3D_ID_&amp;format=f2&amp;key=' . MD5_DATE,
    "text" => 'Link til Infomedia'
  ),
  "vis_nyhedsliste" => FALSE,
);

$TARGET["Danbib"]= array(
  "host" => INFOMEDIA_HOST,
  "database" => "danbibv4",
  "subbase" => array(
    "danbibv4",
    "danbibv4_folk",
    "danbibv4_forsk",
    "danbibv4_folkforsk",
    "danbibv4_peri",
    "danbibv4_dbc",
    "danbibv4_dlb"
  ),
  "piggyback" => false,
  "authentication" => "netpunkt/010100/20Koster",
  "cclfields" => "danbib.search",
  "scanfields" => "danbib.scan",
  "sdi_use" => true,
  "format" => "vrk_standard",
  "accept_formats" => array(
    "isodm2" => "danmarc/isodm2",
    "isodm2_ub" => "danmarc/isodm2_ub",
    "stdhentdm2" => "sutrs/stdhentdm2",
    "marckopi" => MARC21 . "/F"
  ),
  "formats" => array(
    "vrk_oversigt" => "xml/np_vrk",
    "vrk_standard" => "xml/np_vrk",
    "vrk_detaljeret" => "xml/np_vrk",
    "bestil" => "xml/f2",
    "marc" => "xml/f0",
    "marc2" => "xml/f01",
    "postklynge" => "xml/f010",
    "rss" => "xml/rss",
    "single" => "xml/single",
    "f_mat" => "xml/f_mat",
    "a" => "xml/A"
  ), // format for reviews
  "fors_name" => "danbib",
  "step" => 10,
  "filter" => "",
  "sort" => "",
  "sort_default" => "aar",
  "fallback_format_max" => 20000,
  "fallback_format" => array(
    "vrk_oversigt" => "single",
    "vrk_standard" => "single",
    "vrk_detaljeret" => "single"
  ),
  "sort_max" => 500000,
  "vis_max" => 500000,
  "basket_max" => 600,
  "timeout" => 60,
  "allow_user_break" => FALSE,
  "menu_name" => "Danbib",
  "text_prefix" => "danbib", // prefix for filer i "Ret tekster" og info
  // "info" => "danbib_info",
  "RSS" => TRUE,
  "vis_nyhedsliste" => TRUE,
  "vis_emneoversigt" => TRUE,
  "menu_select" => TRUE,
  "menu_link" => TRUE
);


?>
