<?php

require_once(__DIR__ . '/../src/Baby62.php');
require_once(__DIR__ . '/../vendor/autoload.php');

class Baby62Test extends PHPUnit_Framework_TestCase {
  
  public $baby62;
  
  public function __construct() {
    $this->baby62 = new Baby62\Baby62();
  }
  
  public function testString() {
    $string = "Hello World";
    
    echo "\nInput:\t\t$string\n";
    
    // Arrange
    $encoded = $this->baby62->encode($string);
    
    echo "Encoded:\t$encoded\n";

    // Act
    $decoded = $this->baby62->decode($encoded);
    
    echo "Decoded:\t$decoded\n";

    // Assert
    $this->assertEquals($string, $decoded);
  }
  
  public function testURL() {
    
    $string = "http://www.example.com/2015-11-03/abcde-fghijkl-mnopq-rstuvw-xyz?abc=123&def=456&ghi=2015";
    echo "\nInput:\t\t$string\n";
    
    // Arrange
    $encoded = $this->baby62->encode($string);
    
    echo "Encoded:\t$encoded\n";

    // Act
    $decoded = $this->baby62->decode($encoded);
    
    echo "Decoded:\t$decoded\n";

    // Assert
    $this->assertEquals($string, $decoded);
  }
  
  public function testText() {
    $string = "Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.";
    
    echo "\nInput:\t\t$string\n";
    
    // Arrange
    $encoded = $this->baby62->encode($string);
    
    echo "Encoded:\t$encoded\n";

    // Act
    $decoded = $this->baby62->decode($encoded);
    
    echo "Decoded:\t$decoded\n";

    // Assert
    $this->assertEquals($string, $decoded);
  }
}
?>