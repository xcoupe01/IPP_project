<?php

$debug = true;

/**
* Prints debug info based on variable $debug
* @param toprint is string to be printed
*/
function decho($toprint){
  global $debug;
  if($debug) echo $toprint;
}


$help = false;
$directory = Null;
$recursive = false;
$parser = Null;
$interpret = Null;
$parseOnly = false;
$intOnly = false;
$jexam = Null;

foreach($argv as $arg){
  if($arg == '--help'){
    $help = true;
  } elseif (preg_match('^--directory=([\w\/\.]+)$', $arg, $m)) {
    $directory = $m[1];
  } elseif ($arg == '--recursive') {
    $recursive = true;
  } elseif (preg_match('^--parse-script=([\w\/\.]+)', $arg, $m)) {
    $parser = $m[1];
  } elseif (preg_match('^--int-script=([\w\/\.]+)', $arg, $n)) {
    $interpret = $m[1];
  } elseif ($arg == '--parse-only') {
    $parseOnly = true;
  } elseif ($arg == '--int-only'){
    $intOnly = true;
  } elseif (preg_match('^--jexamxml=([\w\/\.]+)', $arg, $m)) {
    $jexam = $m[1];
  } else {
    decho('Unknown argument');
    exit(10);
  }
}
if($help){
  if($directory != Null || $recursive || $parser != Null || $interpret != Null ||
      $parseOnly || $intOnly || $jexam != Null){
        decho('Other argument with help');
        exit(10);
      } else {
        print('help informations ...');
        exit(0);
      }
}



?>
