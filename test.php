<?php

// --- variables ---

$debug = false;
$testlist = [];
$help = false;
$directory = Null;
$recursive = false;
$parser = Null;
$interpret = Null;
$parseOnly = false;
$intOnly = false;
$jexam = Null;
$results = [];

define("ERR_OK", 0);
define("ERR_PARAMS", 10);
define("ERR_OPEN", 11);
define("ERR_WRITE", 12);
define("ERR_INTERNAL", 99);

// --- functions ---

/**
* Prints debug info based on variable $debug
* @param toprint is string to be printed
*/
function decho($toprint){
  global $debug;
  if($debug) echo $toprint;
}

function ta($tagname, $content = '')
{
  if ($content == '') {
    return '<' . $tagname . '/>';
  }
  if ($content == 'noslash') {
    return '<' . $tagname . '>';
  }
  return '<' . $tagname . '>' . $content . '</' . $tagname . '>' . "\n";
}

function tg($tagname, $params = '', $content = '', $nopack = false)
{
  if ($params . $content == '') {
    return '<' . $tagname . '/>';
  }
  if ($params != '') $params = ' ' . $params;
  if ($content == 'noslash' && !$nopack) {
    return '<' . $tagname . $params . '>';
  }
  if ($content == '' && !$nopack) {
    return '<' . $tagname . $params . '/>' . "\n";
  } else {
    return '<' . $tagname . $params . '>' . $content . '</' . $tagname . '>' . "\n";
  }
}

// --- main ---

// dealing with args
unset($argv[0]);
foreach($argv as $i => $arg){
  if($arg == "--help"){
    $help = true;
  } elseif (preg_match("/^--directory=(.+)$/", $arg, $m)) {
    if(is_dir($m[1])){
      $directory = $m[1];
    } else {
      decho(" PARAM \t\e[31mFAIL\e[0m is not a directory\n");
      exit(ERR_OPEN);
    }
  } elseif ($arg == "--recursive") {
    $recursive = true;
  } elseif (preg_match("/^--parse-script=([\w\/\.]+)/", $arg, $m)) {
    if(is_file($m[1])){
      $parser = $m[1];
    } else {
      decho(" PARAM \t\e[31mFAIL\e[0m is not a parser\n");
      exit(ERR_OPEN);
    }
  } elseif (preg_match("/^--int-script=([\w\/\.]+)/", $arg, $m)) {
    if(is_file($m[1])){
      $interpret = $m[1];
    } else {
      decho(" PARAM \t\e[31mFAIL\e[0m is not a interpret\n");
      exit(ERR_OPEN);
    }
  } elseif ($arg == "--parse-only") {
    $parseOnly = true;
  } elseif ($arg == "--int-only"){
    $intOnly = true;
  } elseif (preg_match("/^--jexamxml=([\w\/\.]+)/", $arg, $m)) {
    if(is_file($m[1])){
      $jexam = $m[1];
    } else {
      decho(" PARAM \t\e[31mFAIL\e[0m is not a jexamxml\n");
      exit(ERR_OPEN);
    }
  } else {
    decho(" PARAM \t\e[31mFAIL\e[0m Unknown argument [$arg] \n");
    exit(ERR_PARAMS);
  }
}
if($help){
  if($directory != Null || $recursive || $parser != Null || $interpret != Null ||
      $parseOnly || $intOnly || $jexam != Null){
        decho(" PARAM \t\e[31mFAIL\e[0m Other argument with help \n");
        exit(ERR_PARAMS);
      } else {
        print("Test script for checking both parse.php and/or interpret.py
generates HTML file and outputs it in standard output.\n
Options:
- --help\t\tto print this informations
- --directory=[dir]\tto set the testfiles directory
- --recursive\t\tto tell the script to open all subfiles
- --parse-script=[file]\tto set the source of parse script (default: parse.php)
- --int-script=[file]\tto set the source of interpret script (default: interpret.py)
- --parse-only\t\tto test parser only (jexamxml used)
- --int-only\t\tto test only interpret
- --jexamxml=[file]\tto set the location of jexamxml (default: /pub/courses/ipp/jexamxml/jexamxml.jar)\n"); 
        exit(ERR_OK);
      }
}
if($directory == Null){
  decho(" PARAM \e[33mWARN\e[0m Scanning current folder\n");
  $directory = '.';
}
if ($intOnly) {
  if ($parser != Null || $parseOnly) {
    decho(" PARAM \t\e[31mFAIL\e[0m Bad arguments\n");
    exit(ERR_PARAMS);
  }
}
if ($parseOnly) {
  if ($interpret != Null || $intOnly) {
    decho(" PARAM \t\e[31mFAIL\e[0m Bad arguments\n");
    exit(ERR_PARAMS);
  }
}
if($parser == Null && !$intOnly){
  decho(" PARAM \e[33mWARN\e[0m Setting parser to default\n");
  $parser = "parse.php";
}
if($interpret == Null && !$parseOnly){
  decho(" PARAM \e[33mWARN\e[0m Setting interpret to default\n");
  $interpret = "interpret.py";
}
if($jexam == Null){
  decho(" PARAM \e[33mWARN\e[0m Setting jexamxml to default\n");
  $jexam = "/pub/courses/ipp/jexamxml/jexamxml.jar";
}
// loading files
if ($recursive) {
  $it = new RecursiveDirectoryIterator($directory);
  foreach (new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() == 'src') {
      array_push($testlist, str_replace('.src', '', $file->getPathname()));
    }
  }
} else {
  $tmpfiles = scandir($directory, SCANDIR_SORT_ASCENDING);
  for ($i = 2; $i < count($tmpfiles); $i++)
    if (preg_match('/^(.+).src/', $tmpfiles[$i], $m)) {
      array_push($testlist, $directory . '/' . $m[1]);
    }
}
foreach($testlist as $file) {
  if(!is_file($file . '.in')){
    decho(" FILES \e[33mWARN\e[0m generating infile for $file \n");
    file_put_contents($file . '.in', '');
  }
  if (!is_file($file . '.out')) {
    decho(" FILES \e[33mWARN\e[0m generating outfile for $file\n");
    file_put_contents($file . '.out', '');
  }
  if (!is_file($file . '.rc')) {
    decho(" FILES \e[33mWARN\e[0m generating rcfile for $file\n");
    file_put_contents($file . '.rc', '0');
  }
}
foreach($testlist as $test){
 if($parseOnly){
   $ParOut = [];
  exec("cat $test.src | php -f $parser", $ParOut, $ParRet);
  if(file_get_contents($test . '.rc') == $ParRet){ 
    if($ParRet > 0){
      $results[$test] = 'OK';
    } else {
      $DifOut = [];
      file_put_contents($test . '.tmp', $ParOut);
      exec("java -jar $jexam $test.out $test.tmp", $DifOut, $DifRet);
      if ($DifRet == 0){
        $results[$test] = 'OK';
      } else {
        $results[$test] = 'fail#' . 'Different script outputs: jexamxml [script_output] [expected_output] <br>' . str_replace("\n", '<br>', htmlspecialchars(implode("\n", $DifOut)));
      }
      unlink($test . '.tmp');
    }
  } else {
    $results[$test] = 'fail#' . 'Different return code expected - returned:<b>' . $ParRet . '</b> expected:<b>' . file_get_contents($test . '.rc') . '</b>';
    // html fail
  }
 } elseif($intOnly){
  exec("python3.8 $interpret --source=$test.src --input=$test.in", $IntOut, $IntRet);
  file_put_contents($test . '.tmp', $IntOut);
  if(file_get_contents($test . '.rc') == $IntRet){
    if($IntRet > 0){
      $results[$test] = 'OK';
    } else {
      exec("diff $test.tmp $test.out", $DifOut, $DifRet);
      if($DifRet == 0){
        $results[$test] = 'OK';
      } else {
        $results[$test] = 'fail#'. 'Different script outputs: diff [script_output] [expected_output] <br>'. str_replace("\n", '<br>', htmlspecialchars(implode("\n", $DifOut))); 
      }
    }
  } else {
    $results[$test] = 'fail#' . 'Different return code expected - returned:<b>' . $IntRet . '</b> expected:<b>' . file_get_contents($test . '.rc') . '</b>';
  }
  unlink($test . '.tmp');
 } else {
  decho("cat $test.src | php -f $parser | python3.8 $interpret --input=$test.in\n");
  $Out = [];
  exec("cat $test.src | php -f $parser | python3.8 $interpret --input=$test.in > $test.tmp", $Out, $Ret); // not working --------------- TODO
  if (file_get_contents($test . '.rc') == $Ret) {
    exec("diff $test.tmp $test.out", $DifOut, $DifRet);
    if ($DifRet == 0) {
      $results[$test] = 'OK';
    } else {
      $results[$test] = 'fail#' . 'Different script outputs: diff [script_output] [expected_output] <br>' . str_replace("\n", '<br>', htmlspecialchars(implode("\n", $DifOut)));
    }
  } else {
    $results[$test] = 'fail#' . 'Different return code expected - returned:<b>' . $Ret . '</b> expected:<b>' . str_replace("\n", '', file_get_contents($test . '.rc')) . '</b>';
  }
  unlink($test . '.tmp');
 }
}
/* Generovani HTML 5 vystupu */
$o = ta('h1', 'Test results - ' . gmdate("d.m. H:i:s", time() + 3600 ));
$n = 0;
$fails = 0;
$success = 0;
$o .= ta('br');
$o .= ta('h3', 'test settings:');
$o .= ta('ul');
$o .= ta('li', "directory: ". realpath($directory));
if($recursive){
  $o .= ta('li', "recursive: &#128504;");
} else {
  $o .= ta('li', "recursive: &#128502;");
}
$o .= ta('li', "parse script: $parser");
$o .= ta('li', "interpret script: $interpret");
if($parseOnly){
  $o .= ta('li', "parse only: &#128504;");
} else {
  $o .= ta('li', "parse only: &#128502;");
}
if($intOnly){
  $o .= ta('li', "interpret only: &#128504;");
} else {
  $o .= ta('li', "interpret only: &#128502;");
}
$o .= ta('li', "JexamXML: $jexam");
$o .= ta('br');
foreach ($results as $k => $v) {
  if (preg_match('/^(\w+)#(.+)$/', $v, $m)) {
    $fails++;
    $m[1] = str_replace('fail', '<b>&#128502;</b>', $m[1]);
    $res = tg('td', 'width=5% align="middle" bgcolor=ff7373', $m[1]);
    $dets = tg(
      'td',
      '',
      tg('span', 'onclick="document.getElementById(\'ID' . $n . '\').style.display=(document.getElementById(\'ID' . $n . '\').style.display==\'block\')?\'none\':\'block\';" ', '<b>Details available</b>') .
        tg('div', 'id="ID' . $n . '" style="display:none;"', substr($v, strpos($v, '#') + 1))
    );
  } else {
    $success++;
    $v = str_replace('OK', '<b>&#128504;</b>', $v);
    $res = tg('td', 'width=5% align="middle" bgcolor=9bff8a', $v);
    $dets = ta('td', ' ');
  }
  $o .= ta('tr', tg('td', 'width=5% align="middle"', ++$n . $res . ta('td', $k)) . $dets);
}
$percentage = ($success / ($success + $fails)) * 100;
$o = ta('table',ta('caption', "total tests done : $n<br>percentage : $percentage %<br>successful : $success<br>failed : $fails") . ta('th', 'Number') . ta('th', 'Result') . ta('th', 'Test File') . ta('th', 'Details (click to view)') . $o);

$o = ta(
  'html',
  ta(
    'head',
    tg('meta', 'http-equiv="Content-Type" content="text/html;" charset="utf-8"') .
      ta('title', 'Test results') .
      ta(
        'style',
        '
body  {font-family: \'Arial CE\',arial; font-size: 10pt; background-color: #a3a3a3; margin: 20px; }
table {border: solid #000000 1px;	background: white; border-collapse: collapse; width:100%;}
table caption {background-color: #D4E5E3;	color: #6B6B77;
font-weight: bold;	margin: 0px 0px 10px 0px;  border: solid #C5C5C4 1px;  padding: 5px;  text-align: center; }

table thead {font-style: italic; background-color: #DADADA;	color: #6B6B6B;  border: solid #C5C5C4 1px;  padding: 5px;}
table th {text-align: left;  border: solid #C5C5C4 1px; padding: 4px;  background-color: #DADADA;} 
table tfoot {border: solid #C5C5C4 1px; background: #e0e0e0; }
table tr {background-color: #FFF9F1;}
table tr:hover, table tr.sudy:hover {background-color: #bababa;}
table td {border: solid #000000 1px;  padding: 1px 4px 1px 4px;}
table tbody td {color: black;  padding: 1px 4px 1px 4px;}
table tr.zahlavi th {text-align: left;  border: solid #C5C5C4 1px; padding: 4px;  background-color: #DADADA;}
'
      )
  ) .
    ta('body', $o)
);
if(!$debug) echo '<!DOCTYPE html>' . "\n" . $o;
exit(ERR_OK);
?>
