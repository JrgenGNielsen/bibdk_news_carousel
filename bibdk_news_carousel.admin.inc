<?php
/**
 * @file
 * Admin related backend.
 */


// http://php.net/manual/en/function.stream-context-create.php
function http_post($url, $data) {
    $data_url = http_build_query ($data);
    $data_len = strlen ($data_url);
    return array ('content'=>file_get_contents ($url, false, stream_context_create (array ('http'=>array ('method'=>'POST'
            , 'header'=>"Connection: close\r\nContent-Length: $data_len\r\n"
            , 'content'=>$data_url
            ))))
        , 'headers'=>$http_response_header
        );
}


/**
 * Remove HTML tags, including invisible text such as style and
 * script code, and embedded objects.  Add line breaks around
 * block-level tags to prevent word joining after tag removal.
 * http://nadeausoftware.com/articles/2007/09/php_tip_how_strip_html_tags_web_page
 */
function strip_html_tags($text) {
    $text = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
          // Add line breaks before and after blocks
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ),
        $text );
    return strip_tags( $text );
}


function cmp_emner($a, $b) {
  $adjust = 1.5;
  $queries = variable_get('bibdk_carousel_news_queries', array('0'=>'fubar')); 
  $weight_a = ($a['weight'] / $a['count']) * ( ($adjust * $a['count']) / sizeof($queries));
  $weight_b = ($b['weight'] / $b['count']) * ( ($adjust * $b['count']) / sizeof($queries));
  if ( $weight_a == $weight_b ) return 0;
  return ($weight_a > $weight_b) ? -1 : 1;
}


/**
 * Search queries admin form.
 *
 * @param $form
 *   Form.
 * @param $form_state
 *   Form state.
 *
 * @return
 *   Form structure.
 */
function bibdk_news_carousel_admin_form($form, &$form_state) {

  drupal_add_js(drupal_get_path('module', 'bibdk_news_carousel') . '/js/bibdk_news_carousel.admin.js');
  drupal_add_css(drupal_get_path('module', 'bibdk_news_carousel') . '/css/bibdk_news_carousel.css');

  variable_set('bibdk_carousel_news_queries_hidden', array());

  $searches_num = !empty($form_state['values']['num_searches']) ? $form_state['values']['num_searches'] : 0;
  $clicked_btn = '';
  $query = array();
  $hidden = array();

  if ( isset($form_state['clicked_button']) ) {

    // clicked button: redirect to search result, or delete search.
    $id = explode('_', $form_state['clicked_button']['#name']);
    switch ( $id[0] ) {
      case 'test':
        $id = $id[1];
        $queries = variable_get('bibdk_carousel_news_queries', array());
        $query = module_invoke_all('bibdk_news_carousel_query', $queries[$id]);
        // $search_info = search_get_default_module_info();

$texts = array();
$metadata = array();

// hent tekster
foreach ($queries as $query) {
  $url = $query['url'];
  $result = drupal_http_request($url, array());
  if ( property_exists($result, 'data') ) {

    $output = str_replace(array("\r\n", "\r", "\n"), " ", $result->data);
    $raw_text = str_replace('  ', ' ', $output);
    
    /* Get the file's character encoding from a <meta> tag */
    preg_match(
        '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s+charset=([^\s"]+))?@i',
        $raw_text,
        $matches
    );
    $encoding = $matches[3];

    /* Convert to UTF-8 before doing anything else */
    $utf8_text = iconv( $encoding, "utf-8", $raw_text );

    /* Strip HTML tags and invisible text */
    $utf8_text = strip_html_tags( $utf8_text );

    /* Decode HTML entities */
    $text = html_entity_decode( $utf8_text, ENT_QUOTES, "UTF-8" ); 
    if ( $text ) {
      $texts[] = $text;
    } else {
      dpm($result, 'ERROR: Empty test. ' . $query['title']);
    }
    dpm($result, 'RESULT: ' . $query['title']);
  } else {
    dpm($result, 'ERROR: No data. ' . $query['title']);
  }
}

// hent enmeord ud fra tekst
if ( sizeof($texts) > 0 ) {
  foreach ($texts as $text) {
    // curl -X POST -d "text=de graver efter olie i iraq"  http://ml-p02.dbc.dk:8000/createMetaDataFromText
    $url = 'http://ml-p02.dbc.dk:8000/createMetaDataFromText';
    $data = array('text'=>$text);
    $res = http_post($url, $data);
    $metadata[] = json_decode($res['content']);
  }
} else {
  dpm($result, 'ERROR');
}

dpm($metadata, '$metadata');

// Sorter emneord efter vægt 
$emner = array();
if ( sizeof($metadata) > 0 ) {
  foreach ( $metadata as $set ) {
    foreach ( $set->emner as $emne ) {
      $emneord = $emne[0];
      if ( !isset($emner[$emneord])) {
        $emner[$emneord] = array('count'=>1, 'weight'=>$emne[1], 'emneord'=>$emneord);
      } else {
        $emner[$emneord]['count']++;
        $emner[$emneord]['weight'] += $emne[1];
      }
    }
  }
} else {
  dpm($metadata, 'ERROR: no metadata.');
}
usort($emner, "cmp_emner");

// Fjern marc-formattering 
foreach ( $emner as $key =>$emne ) {
  $patterns[0] = '/^[0-9]{3}\*./';
  $patterns[1] = '/\*./';
  $replacements[2] = ' ';
  $replacements[1] = ', ';
  $emner[$key]['emneord.nomarc'] = preg_replace($patterns, $replacements, $emne['emneord']);
}

dpm($emner);


        /*
        if ( !empty($search_info['path']) && in_array($search_info['module'], variable_get('search_active_modules', array())) ) {
          $form_state['redirect'] = FALSE;
          $url = 'search/' . $search_info['path'] . '/' . trim($query['query']);
          drupal_goto($url);
        }
        */
        break;
      case 'remove':
        $hidden[] = $id[1];
        variable_set('bibdk_carousel_news_queries_hidden', $hidden);
        break;
    }

    // Whether add a new query field.
    if ( $form_state['clicked_button']['#name'] == 'add_query') {
      $searches_num++;
    }

  }

  $form['bibdk_news_carousel'] = array(
    '#type' => 'fieldset',
    '#title' => 'Searches',
  );

  // Get current saved queries.
  $searches = variable_get('bibdk_carousel_news_queries', array());

  $i = 0;
  $c = 1;

  // Display saved queries.
  if ( !empty($searches[0]['title']) ) {
    $c = count($searches);
    for (; $i < $c; $i++) {
      $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i] = bibdk_news_carousel_query_form($searches[$i], $i);
    }
  }

  for (; $i < $searches_num + $c; $i++) {
    $unsubmitted = array();
    // Maybe prev field had some data in it...
    if ( isset( $form_state['input']['title'][$i]) ) {
      $unsubmitted = array(
        'title' => $form_state['input']['title'][$i],
        'url' => $form_state['input']['url'][$i],
      );
    }

    $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i] = bibdk_news_carousel_query_form($unsubmitted, $i);
  }

  // Hide removed fields.
  for ($i = 0; $i < $searches_num + $c; $i++) {
    if ( in_array($i, $hidden) ) {
      // Hide title, used to sort needed queries when saving
      $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i]['fields']['title[]']['#value'] = '';
      $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i]['#prefix'] = '<div class="search-query-hidden">';
      $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i]['#suffix'] = '</div>';
    }
  }

  // Keep track of query fields count.
  $form['bibdk_news_carousel']['num_searches'] = array(
    '#type' => 'hidden',
    '#value' => $searches_num,
  );

  // Ajaxified button for new fields.
  $form['bibdk_news_carousel']['new_search_carousel_button'] = array(
    '#type' => 'button',
    '#value' => 'Add another',
    '#name' => 'add_query',
    '#ajax' => array(
      'callback' => 'bibdk_news_carousel_admin_form_ajaxify',
      'wrapper' => 'bibdk-search-carousel-queries',
      'method' => 'replace',
      'effect' => 'fade'
    ),
  );

  // Get current saved queries.
  $searches = variable_get('bibdk_carousel_news_queries', array());

  $period = array(
    '#title' => t('Period', array(), array('context' => 'bibdk_news_carousel')),
    '#type' => 'select',
    '#options' => array(
      0 => t('All', array(), array('context' => 'bibdk_news_carousel')),
      1 => t('1 week', array(), array('context' => 'bibdk_news_carousel')),
      2 => t('@n weeks', array('@n' => '2'), array('context' => 'bibdk_news_carousel')),
      3 => t('@n weeks', array('@n' => '3'), array('context' => 'bibdk_news_carousel')),
      4 => t('@n weeks', array('@n' => '4'), array('context' => 'bibdk_news_carousel')),
      5 => t('@n weeks', array('@n' => '5'), array('context' => 'bibdk_news_carousel')),
      6 => t('@n weeks', array('@n' => '6'), array('context' => 'bibdk_news_carousel')),
      7 => t('@n weeks', array('@n' => '7'), array('context' => 'bibdk_news_carousel')),
      8 => t('@n weeks', array('@n' => '8'), array('context' => 'bibdk_news_carousel')),
      9 => t('@n weeks', array('@n' => '9'), array('context' => 'bibdk_news_carousel')),
      10 => t('@n weeks', array('@n' => '10'), array('context' => 'bibdk_news_carousel')),
      11 => t('@n weeks', array('@n' => '11'), array('context' => 'bibdk_news_carousel')),
      12 => t('@n weeks', array('@n' => '12'), array('context' => 'bibdk_news_carousel')),
    ),
    '#default_value' => isset($item['period']) ? $item['period'] : '',
    '#description' => t('Select period for this carousel search.', array(), array('context' => 'bibdk_news_carousel')),
  );

  $searches_num = sizeof($form['bibdk_news_carousel']['bibdk_searches']);
  for ($i = 0; $i < $searches_num; $i++) {
    $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i]['bibdk_news_carousel'] = array(
      '#type' => 'fieldset',
      '#title' => t('Search settings', array(), array('context' => 'bibdk_news_carousel')),
      '#title_display' => 'invisible',
    );
    $period['#default_value']    = isset($searches[$i]['period'])    ? $searches[$i]['period']    : '';

    $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i]['bibdk_news_carousel']['period[]'] = $period;

    $form['bibdk_news_carousel']['bibdk_searches']['search_' . $i]['remove[]']['#suffix'] = '';
  }

  // Wrap the form, due to draggable items renewal.
  $form['#prefix'] = '<div id="bibdk-search-carousel-queries">';
  $form['#suffix'] = '</div>';

  // Custom themer, mainly used for dragable table creation.
  $form['#theme'] = array('bibdk_news_carousel_admin_form');

  // Custom form submit handler.
  $form['#submit'] = array('bibdk_news_carousel_search_submit');

  return system_settings_form($form);
}



/**
 * Admin settings form for frontend display of carousel.
 */
function bibdk_news_carousel_settings_admin_form($form, &$form_state) {
  $form = array();

  $form['bibdk_news_carousel_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Frontend settings'),
  );

  $form['bibdk_news_carousel_settings']['bibdk_news_carousel_tabs_position'] = array(
    '#title' => t('Position of navigational tabs'),
    '#type' => 'select',
    '#options' => array(
      'bottom' => t("Bottom"),
    ),
    '#default_value' => variable_get('bibdk_news_carousel_tabs_position', 'bottom'),
    '#required' => TRUE,
    '#description' => t('Select where you want the tabs for displaying the various searches to be placed in frontend.'),
  );

  $form['bibdk_news_carousel_settings']['bibdk_news_carousel_description_toggle'] = array(
    '#title' => t('Show descriptions for navigational tabs'),
    '#type' => 'checkbox',
    '#default_value' => variable_get('bibdk_news_carousel_description_toggle', 0),
    '#required' => FALSE,
    '#description' => t('Toggle whether descriptions of the various searches are displayed or not.'),
  );

  return system_settings_form($form);
}



/**
 * Admin settings form for frontend display of carousel.
 */
function bibdk_news_carousel_search_admin_form($form, &$form_state) {
  $form = array();

  $form['bibdk_news_carousel_search'] = array(
    '#type' => 'fieldset',
    '#title' => t('Search settings'),
  );

  $form['bibdk_news_carousel_search']['bibdk_news_carousel_webservice'] = array(
    '#title' => t('Search webservice'),
    '#type' => 'select',
    '#options' => array(
      'bibdk_search' => t("Ting Search"),
      'bibdk_openformat' => t("Ting OpenFormat"),
    ),
    '#default_value' => variable_get('bibdk_news_carousel_webservice', 'bibdk_search'),
    '#required' => TRUE,
    '#description' => t('Select the search webservice.'),
  );

  $form['bibdk_news_carousel_search']['bibdk_news_carousel_covers_max'] = array(
    '#type' => 'textfield',
    '#title' => t('Covers maximum'),
    '#description' => t('The maximum number of covers fetched from the ADDI webservice.'),
    '#default_value' => (int)variable_get('bibdk_news_carousel_covers_max', 20),
    '#required' => TRUE,
  );

  $form['bibdk_news_carousel_search']['bibdk_news_carousel_queries_max'] = array(
    '#type' => 'textfield',
    '#title' => t('Search maximum'),
    '#description' => t('The maximum number of records fetched from the search webservice.'),
    '#default_value' => (int)variable_get('bibdk_news_carousel_queries_max', 400),
    '#required' => TRUE,
  );

  $form['bibdk_news_carousel_search']['bibdk_news_carousel_dateformat'] = array(
    '#type' => 'textfield',
    '#title' => t('Date format.', array(), array('context' => 'bibdk_news_carousel')),
    '#description' => t('PHP date format. Jfr. http://php.net/manual/en/function.date.php', array(), array('context' => 'bibdk_news_carousel')),
    '#default_value' => variable_get('bibdk_news_carousel_dateformat', 'bk\mYW'),
  );

  $form['bibdk_news_carousel_search']['bibdk_news_carousel_search_test']['boolean_information'] = array(
    '#markup' =>  t(
      'Date format example: "@dateformat" = "@date"',
      array(
        '@dateformat' => variable_get('bibdk_news_carousel_dateformat', 'bk\mYW'),
        '@date' => date( variable_get('bibdk_news_carousel_dateformat', 'bk\mYW'))
      ),
      array(
        'context' => 'bibdk_news_carousel'
      )
    )
  );

  $form['bibdk_news_carousel_search']['bibdk_news_carousel_week_search_code'] = array(
    '#type' => 'textfield',
    '#title' => t('Search code', array(), array('context' => 'bibdk_news_carousel')),
    '#description' => t('Search code for week number.', array(), array('context' => 'bibdk_news_carousel')),
    '#default_value' => variable_get('bibdk_news_carousel_week_search_code', 'dkcclterm.kk'),
  );

  $form['bibdk_news_carousel_search']['boolean_information'] = array(
    '#markup' =>  t(
      'Boolean operators used: "and", "or" and "not"',
      array(),
      array(
        'context' => 'bibdk_news_carousel'
      )
    )
  );

  return system_settings_form($form);
}


/**
 * AJAX responder for field addition/removal fields.
 *
 * @param $form
 *   Form.
 *
 * @return
 *   Changed field to be updated.
 */
function bibdk_news_carousel_admin_form_ajaxify($form, &$form_state) {
  return $form;
}



/**
 * Theme the queries form, mainly adding a table drag-n-drop feature.
 *
 * @param $variables
 *   Theme related variables.
 *
 * @return
 *   Themed form output.
 */
function theme_bibdk_news_carousel_admin_form($variables) {

  $form = $variables['form'];

  drupal_add_tabledrag('bibdk-search-carousel-admin-sort', 'order', 'sibling', 'sort');

  $header = array('Searches', '');
  $rows = array();

  foreach (element_children($form['bibdk_news_carousel']['bibdk_searches']) as $key) {

    // Add class to group weight fields for drag and drop.
    $form['bibdk_news_carousel']['bibdk_searches'][$key]['sort[]']['#attributes']['class'][] = 'sort';

    // Get sort element.
    $sort = $form['bibdk_news_carousel']['bibdk_searches'][$key]['sort[]'];
    unset($form['bibdk_news_carousel']['bibdk_searches'][$key]['sort[]']);

    // Build rows.
    $classes = array('draggable');
    if (isset($form['bibdk_news_carousel']['bibdk_searches'][$key]['#prefix'])) {
      $classes[] = 'search-query-hidden';
    }

    $rows[] = array(
      'data' => array(
        drupal_render($form['bibdk_news_carousel']['bibdk_searches'][$key]),
        drupal_render($sort)
      ),
      'class' => $classes
    );
  }

  $table_vars = array(
    'header' => $header,
    'rows' => $rows,
    'attributes' => array('id' => 'bibdk-search-carousel-admin-sort'),
    'caption' => '',
    'colgroups' => array(),
    'sticky' => FALSE,
    'empty' => 'EMPTY'
  );
  $output = theme_table($table_vars);

  // Render remaining elements.
  $output .= drupal_render_children($form);

  return $output;
}



/**
 * Form part, describing query fields.
 *
 * @param $item
 *   Values for existing query.
 *
 * @return
 *   Fields structure.
 */
function bibdk_news_carousel_query_form($item = array(), $index = 0) {
  $form = array();

  $form['fields'] = array(
    '#type' => 'fieldset',
    '#title' => t('Search fields'),
    '#title_display' => 'invisible',
  );

  $form['sort[]'] = array(
    '#type' => 'weight',
    '#delta' => 5,
    '#default_value' => 0,
    '#value' => 0,
    '#attributes' => array(
      'class' => array(
        'sort-hidden'
      )
    ),
  );

  $form['fields']['title[]'] = array(
    '#type' => 'textfield',
    '#title' => t('Title'),
    '#description' => t('The title of the search is used for the tab in the carousel. Keep it short.'),
    '#default_value' => isset($item['title']) ? $item['title'] : '',
    '#prefix' => '<div class="search-carousel-query">',
  );

  $form['fields']['url[]'] = array(
    '#type' => 'textfield',
    '#title' => t('URL'),
    '#maxlength' => 2048,
    '#description' => t('The URL to grab text from.'),
    '#default_value' => isset($item['url']) ? $item['url'] : '',
  );

  if ( isset($item['url']) ) {
    $form['fields']['test[]'] = array(
      '#type' => 'button',
      '#value' => t('Test'),
      '#name' => 'test_' . $index,
      '#attributes' => array(
        'class' => array(
          'test'
        )
      ),
    );
  }

  $form['fields']['remove[]'] = array(
    '#type' => 'button',
    '#value' => t('Remove'),
    '#name' => 'remove_' . $index,
    '#attributes' => array(
      'class' => array(
        'remove'
      )
    ),
    '#suffix' => '</div><div class="clearfix"></div>',
    '#ajax' => array(
      'callback' => 'bibdk_news_carousel_admin_form_ajaxify',
      'wrapper' => 'bibdk-search-carousel-queries',
      'method' => 'replace',
      'effect' => 'fade'
    ),
  );

  return $form;
}



/**
 * Submit handler for admin backend queries form.
 *
 * @param $form
 *   Form.
 * @param $form_state
 *   Form state.
 */
function bibdk_news_carousel_search_submit($form, &$form_state) {
  $c = count($form_state['input']['title']);
  $searches = array();
  $search_items = array();

  $hidden = variable_get('bibdk_carousel_news_queries_hidden', array());

  for ($i = 0; $i < $c; $i++) {
    if (!in_array($i, $hidden)) {
      // Make an array for saving, ignoring queries w/o title.
      if (!empty($form_state['input']['title'][$i])) {
        $searches[] = array(
          'title' => $form_state['input']['title'][$i],
          'url' => $form_state['input']['url'][$i],
          'period' => $form_state['input']['period'][$i]
        );
      }
    }
  }

  /**
   * @TODO: Add clear cache button and detect changes in the query input fields
   * so only partial cache can be rebuild. This will slow down the submit but
   * may speed up the presentation for the users by kick starting the cache.
   */

  // Save the queries as a persistent variable.
  variable_set('bibdk_carousel_news_queries', $searches);
dpm($searches);
  // Clear carousel search cache
  cache_clear_all('bibdk_news_carousel_result', 'cache');
}