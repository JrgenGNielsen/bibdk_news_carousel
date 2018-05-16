<?php
/**
 * @file
 * Admin related backend.
 */

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

  // drupal_add_js(drupal_get_path('module', 'bibdk_news_carousel') . '/js/bibdk_news_carousel.admin.js');
  // drupal_add_css(drupal_get_path('module', 'bibdk_news_carousel') . '/css/bibdk_news_carousel.css');

  $form['bibdk_news_carousel'] = array(
    '#type' => 'fieldset',
    '#title' => 'bibliotek.dk news carousel',
  );

  // Custom form submit handler.
  $form['#submit'] = array('bibdk_news_carousel_search_submit');

  return system_settings_form($form);
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
  $infomedia = new infomedia;
  // $infomedia->getArticleSearch();
  $infomedia->getKeywords();
}