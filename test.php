<?php

// --- variables ---

$debug = true;
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

// --- main ---

// dealing with args
unset($argv[0]);
foreach($argv as $i => $arg){
  if($arg == "--help"){
    $help = true;
  } elseif (preg_match("/^--directory=(.+)$/", $arg, $m)) {
    if(is_file($m[1])){
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
        print("help information ...\n");
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
  $parser = "parser.php";
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
  exec("cat $test.src | php -f $parser", $ParOut, $ParRet);
  if(file_get_contents($test . '.rc') == $ParRet){ 
    if($ParRet > 0){
      $result[$test] = 'RetOk' . $ParRet;
    } else {
      // compare outputs with .out by jexamxml
      // a7soft integration ------------------------------------------------------ TODO
      $result[$test] = 'DifOk';
      $result[$test] = 'DifFail';
    }
  } else {
    $result[$test] = 'RetFail' . $ParRet;
    // html fail
  }
 } elseif($intOnly){
  exec("python3.8 $interpret --source=$test.src --input=$test.in", $IntOut, $IntRet);
  file_put_contents($test . '.tmp', $IntOut);
  if(file_get_contents($test . '.rc') == $IntRet){
    if($IntRet > 0){
      $result[$test] = 'RetOk' . $IntRet;
    } else {
      exec("diff $test.tmp $test.out", $DifOut, $DifRet);
      if($DifRet == 0){
        $result[$test] = 'DifOk';
      } else {
        $result[$test] = 'DifFail'. $DifOut; 
      }
    }
  } else {
    $result[$test] = 'RetFail' . $IntRet;
  }
 } else {
  exec("cat $test.src | php -f $parser | python3.8 $interpret --input=$test.in", $Out, $Ret);
  file_put_contents($test . '.tmp', $Out);
  if (file_get_contents($test . '.rc') == $Ret) {
    if ($Ret > 0) {
      $result[$test] = 'RetOk' . $Ret;
    } else {
      exec("diff $test.tmp $test.out", $DifOut, $DifRet);
      if ($DifRet == 0) {
        $result[$test] = 'DifOk';
      } else {
        $result[$test] = 'DifFail' . $DifOut;
      }
    }
  } else {
    $result[$test] = 'RetFail' . $Ret;
  }
 }
}
print("hello world\n");
exit(ERR_OK);
?>
