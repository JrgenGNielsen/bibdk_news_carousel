<?php

require_once 'service_classes.php';


class TestCulrHandler extends PHPUnit_Framework_TestCase {
  public function test_generateQueryk() {
    $obj = new ReflectionClass('OS');
    // test generateQuery method
    $method = $obj->getMethod('generateQuery');
    $method->setAccessible(TRUE);
    // construct a faust number
    $OLS = new stdClass();
    $OLS->_value='1234';
    $args = array($OLS);
    $query = $method->invokeArgs(null, array($args,'faust'));

    $this->assertTrue($query === 'rec.id=1234');
    $query = $method->invokeArgs(null, array($args,'isbn'));
    $this->assertTrue($query === 'term.isbn=1234');
  }

  public function test_parseForArticleIds(){
    $obj = new ReflectionClass('OS');
    // test generateQuery method
    $method = $obj->getMethod('parseForArticleIds');
    $method->setAccessible(TRUE);

    $file = file_get_contents('testfiles/getArticle.serialized');
    $response = unserialize($file);
    $result = $method->invokeArgs(null, array($response));
    $file =  file_get_contents('testfiles/getArticlesParsed.serialized');
    $expected = unserialize($file);
    $this->assertTrue($result == $expected);
  }
}