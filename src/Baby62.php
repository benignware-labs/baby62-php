<?php

namespace Baby62;

/**
 * @author       Rafael Nowrotek <mail@benignware.com>
 * @copyright    2015 Benignware <mail@benignware.com> 
 * @license      http://www.opensource.org/licenses/MIT    The MIT License
 * @link         https://github.com/benignware/baby62-php
 * @since        Release v0.0.1
 */
class Baby62
{

    const CODESET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    const ASCII_PRINTABLE = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
    
    private $codeset;
    
    public function __construct($codeset = self::CODESET) {
       $this->codeset = $codeset;
    }
    
    public function encode($string) {
      $codeset = $this->codeset;
      $digits = 3;
      $offset_base = 24;
      $codeset_digits = substr($codeset, strlen($codeset) - $digits);
      $codeset_int = substr($codeset, 0, strlen($codeset) - $digits);
      $codeset_offset = substr($codeset, 0, $offset_base);
      $codeset_chars = substr($codeset, strlen($codeset_offset), strlen($codeset) - strlen($codeset_offset) - $digits);

      $stripped = $string;
      // Match chars
      $found = preg_match_all("~[^" . preg_quote($codeset, "~") . "]+~", $string, $matches, PREG_OFFSET_CAPTURE);
      $header = "";
      
      // Collect matches
      $all = array();
      $result_sets = array();
      $index = 0;
      foreach ($matches[0] as $i => $match) {
        
        // Sequence
        $sequence = $match[0];
        
        // Offset
        $offset = $match[1] - $index;
        
        // Add to result set
        $result_set;
        if (isset($result_sets[$sequence])) {
          $result_set = $result_sets[$sequence];
          $set_last_index = $result_set->occurrences[count($result_set->occurrences) - 1];
          $set_offset = $match[1] - $set_last_index;
          if ($set_offset >= strlen($codeset_offset) || $offset >= strlen($codeset_offset)) {
            $result_sets[$sequence] = null;
          }
        } 
        if (!isset($result_sets[$sequence])) {
          $result_set = $result_sets[$sequence] = new \stdClass;
          $result_set->sequence = $sequence;
          $result_set->occurrences = array();
          $all[] = $result_set;
        }
        $result_set->occurrences[] = $match[1];
        
        //$header.= $sequence_encoded . $offsets_encoded;
        $stripped = substr_replace($stripped, "", $match[1] - (strlen($string) - strlen($stripped)), strlen($match[0]));
        $index = $match[1];
      }
      
      // Generate Header
      $index = 0;
      $last_index = 0;
      foreach ($all as $result_set) {
        $sequence = $result_set->sequence;
        // Encode sequence
        $sequence_chars = str_split($sequence);
        $sequence_char_values = array();
        foreach ($sequence_chars as $char) {
          $char_value = self::charValue($char, $codeset_chars);
          $sequence_char_values[] = $char_value;
        }
        $sequence_encoded = self::formatNumbers($sequence_char_values, $codeset_chars, $codeset_digits);
        // Add encoded sequence
        $header.= $sequence_encoded;
        
        $offset_values = array();
        foreach ($result_set->occurrences as $i => $occurrence) {
          if ($i === 0) {
            // First occurrence
            $set_index = $occurrence;
            $last_index = $index;
            $index = $occurrence;
            $item_offset = $index - $last_index;
            $offset_values[] = $item_offset;
          } else {
            // Occurrence within base range
            $item_offset = $occurrence - $set_last_index;
            $offset_values[] = $item_offset;
          }
          $set_last_index = $occurrence;
        }
        
        $offsets_encoded = self::formatNumbers($offset_values, $codeset_offset, $codeset_digits);
        $header.= $offsets_encoded;
        
      }

      $string = $stripped;
      $header_length = strlen($header);
      $header_length_encoded = self::formatNumber($header_length, $codeset_int, $codeset_digits);
      // Setup result string
      $result = "";
      // Add Header Length
      $result.= $header_length_encoded;
      // Header
      $result.= $header;
      // Rest
      $result.= $string;
      
      // Obfuscate
      $result = self::rotate($result, floor(strlen($codeset) * 0.65), $codeset);
      $result = self::paritySplitEncode($result);
      return $result;
    }

    public function decode($string) {
      $codeset = $this->codeset;
      // De-Obfuscate
      $string = self::rotate($string, -floor(strlen($codeset) * 0.65), $codeset);
      $string = self::paritySplitDecode($string);
      // Decode
      $digits = 3;
      $offset_base = 24;
      $codeset_digits = substr($codeset, strlen($codeset) - $digits);
      $codeset_int = substr($codeset, 0, strlen($codeset) - $digits);
      $codeset_offset = substr($codeset, 0, $offset_base);
      $codeset_chars = substr($codeset, strlen($codeset_offset), strlen($codeset) - strlen($codeset_offset) - $digits);
      
      $found = "";
      $length = 0;
      $results = array();
      
      $header_length = 0;
      if (self::parseNumber($string, $codeset_int, $match, $codeset_digits)) {
        $header_length = $match[1];
        $string = substr($string, strlen($match[0]));
      }
      
      $header = substr($string, 0, $header_length);
      $string = substr($string, $header_length);
      
      $index = 0;
      $all = array();
      while (strlen($header) > 0) {
        
        $sequence = "";
        $char_matches = array();
        if (self::parseNumbers($header, $codeset_chars, $char_matches, $codeset_digits)) {
          // Sequence
          foreach ($char_matches as $char_match) {
            $char = self::charFromValue($char_match[1], $codeset_chars); 
            $sequence.= $char;
            $header = substr($header, strlen($char_match[0]));
          }
          // Offset
          $offset = 0;
          $offset_matches = array();
          
          $occurrences = array();
          if (self::parseNumbers($header, $codeset_offset, $offset_matches, $codeset_digits)) {
            $i = 0;
            foreach ($offset_matches as $i => $offset_match) {
              $offset = $offset_match[1];
              if ($i == 0) {
                $index+= $offset;
                $occurrences[] = $index;
              } else {
                $occurrences[] = $occurrences[$i - 1] + $offset;
              }
              
              $header = substr($header, strlen($offset_match[0]));
            }
          }
          $result_set = new \stdClass;
          $result_set->sequence = $sequence;
          $result_set->occurrences = $occurrences;
          $results[] = array($sequence, $offset);
          $all[] = $result_set;
        }
        
      }
      
      // Sort single sequence/offset pairs
      $all_matches = array();
      foreach ($all as $result_set) {
        $sequence = $result_set->sequence;
        foreach ($result_set->occurrences as $occurrence) {
          $all_matches[] = array($sequence, $occurrence);
        }
      }
      usort($all_matches, array( self, "cmpOccurrences"));
      // Apply substitutions
      foreach ($all_matches as $match) {
        $sequence = $match[0];
        $index = $match[1];
        $string = substr_replace($string, $sequence, $index, 0);
      }
      
      return $string;
    }

    private static function encodeInt($int, $codeset) {
      $base = strlen($codeset);
      if ($int === 0) {
        return substr($codeset, 0, 1);
      }
      $encoded = '';
      while ($int > 0) {
        $encoded = substr($codeset, bcmod($int, $base), 1) . $encoded;
        $int = bcmul(bcdiv($int, $base), '1', 0);
      }
      return $encoded ;
    }
    
    private static function decodeInt($encoded, $codeset) {
      $base = strlen($codeset);
      $c = '0';
      for ($i = strlen($encoded); $i; $i--) {
        $c = bcadd($c, bcmul(strpos($codeset, substr($encoded, (-1 * ($i - strlen($encoded))), 1)), bcpow($base, $i-1)));
      }
      $result = bcmul($c, 1, 0);
      if (result === "") {
        return 0;
      }
      return $result;
    }
    
    public static function charValue($char, $codeset) {
      $codeset_128 = preg_replace("~[" . preg_quote($codeset, "~") . "]~", "", self::ASCII_PRINTABLE);
      $codeset_128_pos = strpos($codeset_128, $char);
      if ($codeset_128_pos !== false) {
        return $codeset_128_pos;
      }
      if (ord($char) < 32) {
        return strlen($codeset_128) + ord($char);
      }
      return ord($char) - 128 + strlen($codeset_128);
    }
    
    public static function charFromValue($int, $codeset) {
      $codeset_128 = preg_replace("~[" . preg_quote($codeset, "~") . "]~", "", self::ASCII_PRINTABLE);
      if ($int >= 0 && $int < strlen($codeset_128)) {
        return substr($codeset_128, $int, 1);
      } else if ($int >= strlen($codeset_128) && $int < strlen($codeset_128) + 32) {
        return chr($int - strlen($codeset_128));
      }
      return chr($int + 128 - strlen($codeset_128));
    }
    
    public static function formatNumber($int, $codeset, $digits = 4) {
      $codeset_int = is_string($digits) ? $codeset : substr($codeset, 0, strlen($codeset) - $digits + 1);
      $codeset_digits = is_string($digits) ? $digits : substr($codeset, strlen($codeset_int));
      $int_encoded = self::encodeInt($int, $codeset_int);
      $digits = strlen($int_encoded);
      $result = $digits > 1 ? self::encodeInt($digits - 1, $codeset_digits) . $int_encoded : $int_encoded;
      return $result;
    }
    
    public static function parseNumber($string, $codeset, &$match = array(), $digits = 4) {
      $codeset_int = is_string($digits) ? $codeset : substr($codeset, 0, strlen($codeset) - $digits + 1);
      $codeset_digits = is_string($digits) ? $digits : substr($codeset, strlen($codeset_int));
      $int = null;
      $found = preg_match("~^([" . preg_quote($codeset_digits, "~") . "]+)?([" . preg_quote($codeset_int, "~") . "])~", $string, $initial_match);
      if ($found) {
        $digits_encoded = $initial_match[1] ? $initial_match[1] : "";
        $digits = $digits_encoded ? self::decodeInt($digits_encoded, $codeset_digits) + 1 : 1;
        $int_encoded = substr($string, strlen($digits_encoded), $digits);
        $int = self::decodeInt($int_encoded, $codeset_int);
        $match[0] = $digits_encoded . $int_encoded;
        $match[1] = $int;
      }
      return $found;
    }
    
    public static function formatNumbers($array, $codeset, $digits = 4) {
      $string = "";
      foreach ($array as $int) {
        $string.= self::formatNumber($int, $codeset, $digits);
      }
      return $string;
    }
    
    public static function parseNumbers($string, $codeset, &$matches = array(), $digits = 4) {
      $c = 0;
      while(self::parseNumber($string, $codeset, $match, $digits)) {
        $matches[] = $match;
        $string = substr($string, strlen($match[0]));
        $c++;
      }
      return count($matches) > 0;
    }
    
    private static function cmpOccurrences($a, $b) {
      return $a[1] - $b[1];
    }
    
    public static function rotate($s, $n = 13, $codeset = self::CODESET) {
      //Rotate a string by a number.
      $letterLen=round(strlen($codeset)/2);
      if($n==-1) $n=(int)($letterLen/2); //Find the "halfway rotate point"
      $n = (int)$n % ($letterLen);
      if (!$n) return $s;
      if ($n < 0) $n += ($letterLen);
      $rep = substr($codeset, $n * 2) . substr($codeset, 0, $n * 2);
      return strtr($s, $codeset, $rep);
    }
    
    public static function paritySplitEncode($string) {
      $chars = str_split($string);
      $even = "";
      $odd = "";
      foreach ($chars as $i => $char) {
        if ($i % 2 == 0) {
          $even.= $char;
        } else {
          $odd.= $char;
        }
      }
      $string = "";
      $string.= strrev($odd);
      $string.= strrev($even);
      return $string;
    }
    
    public static function paritySplitDecode($string) {
      $chars = str_split($string);
      $halfway = floor(strlen($string) / 2);
      $odd = substr($string, 0, $halfway);
      $even = substr($string, $halfway);
      foreach ($chars as $i => $char) {
        if ($i >= $halfway) {
          $even.= $char;
        } else {
          $odd.= $char;
        }
      }
      $odd = strrev($odd);
      $even = strrev($even);
      $string = "";
      foreach ($chars as $i => $char) {
        if ($i % 2 == 0) {
          $string.= substr($even, 0, 1);
          $even = substr($even, 1);
        } else {
          $string.= substr($odd, 0, 1);
          $odd = substr($odd, 1);
        }
      }
      return $string;
    }
    
}     