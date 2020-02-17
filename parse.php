<?php
/* analyzer of IPPcode20
   author : Vojtech Coupek - xcoupe01 */

//----- definig variables -----
$debug = false; // enables debug prints
define("ERR_OK", 0);        //< correct exit code
define("ERR_HEADER", 21);   //< header fail
define("ERR_OPCODE", 22);   //< operation code fail
define("ERR_OTHER", 23);    //< other errors
//table of rules
$rules=[ //base functions
        'MOVE'        =>['params'=>['var','symb']],
        'CREATEFRAME' =>['params'=>[]],
        'PUSHFRAME'   =>['params'=>[]],
        'POPFRAME'    =>['params'=>[]],
        'DEFVAR'      =>['params'=>['var']],
        'CALL'        =>['params'=>['label']],
        'RETURN'      =>['params'=>[]],
        //data stack
        'PUSHS'       =>['params'=>['symb']],
        'POPS'        =>['params'=>['var']],
        //STACK extension
        'CLEARS'      =>['params'=>[]],
        'ADDS'        =>['params'=>[]],
        'SUBS'        =>['params'=>[]],
        'MULS'        =>['params'=>[]],
        'DIVS'        =>['params'=>[]],
        'IDIVS'       =>['params'=>[]],
        'LTS'         =>['params'=>[]],
        'GTS'         =>['params'=>[]],
        'EQS'         =>['params'=>[]],
        'ANDS'        =>['params'=>[]],
        'ORS'         =>['params'=>[]],
        'NOTS'        =>['params'=>[]],
        'INT2FLOATS'  =>['params'=>[]],
        'FLOAT2INTS'  =>['params'=>[]],
        'INT2CHARS'   =>['params'=>[]],
        'STRI2INTS'   =>['params'=>[]],
        'JUMPIFEQS'   =>['params'=>['label']],
        'JUMPIFNEQS'  =>['params'=>['label']],
        //arithmetic operations, bool operation and conversion
        'ADD'         =>['params'=>['var','symb','symb']],
        'SUB'         =>['params'=>['var','symb','symb']],
        'MUL'         =>['params'=>['var','symb','symb']],
        'DIV'         =>['params'=>['var','symb','symb']],
        'IDIV'        =>['params'=>['var','symb','symb']],
        'LT'          =>['params'=>['var','symb','symb']],
        'GT'          =>['params'=>['var','symb','symb']],
        'EQ'          =>['params'=>['var','symb','symb']],
        'AND'         =>['params'=>['var','symb','symb']],
        'OR'          =>['params'=>['var','symb','symb']],
        'NOT'         =>['params'=>['var','symb','symb']],
        'INT2FLOAT'   =>['params'=>['var','symb']],
        'FLOAT2INT'   =>['params'=>['var','symb']],
        'INT2CHAR'    =>['params'=>['var','symb']],
        'STRI2INT'    =>['params'=>['var','symb','symb']],
        //input output instructions
        'READ'        =>['params'=>['var','type']],
        'WRITE'       =>['params'=>['symb']],
        //string operations
        'CONCAT'      =>['params'=>['var','symb','symb']],
        'STRLEN'      =>['params'=>['var','symb']],
        'GETCHAR'     =>['params'=>['var','symb','symb']],
        'SETCHAR'     =>['params'=>['var','symb','symb']],
        //type operations
        'TYPE'        =>['params'=>['var','symb']],
        //jumps
        'LABEL'       =>['params'=>['label']],
        'JUMP'        =>['params'=>['label']],
        'JUMPIFEQ'    =>['params'=>['label','symb','symb']],
        'JUMPIFNEQ'   =>['params'=>['label','symb','symb']],
        'EXIT'        =>['params'=>['symb']],
        //debuging
        'DPRINT'      =>['params'=>['symb']],
        'BREAK'       =>['params'=>[]],
        ];

$state = ['numLoc'       => 0,                         //< num of lines with opcodes  --loc
          'numComments'  => 0,                         //< num of comments            --comments
          'numLabels'    => 0,                         //< number of labels           --labels
          'numJumps'     => 0,                         //< num of jumps               --jumps
          'order'        =>[]];                        //< order in stat file

$output = new DOMDocument("1.0", "UTF-8"); //< output XML file that will be outputed if the whole input is correct
$output->formatOutput = true;
$outProgram = $output->createElement("program");
$outProgram->setAttribute("language","IPPcode20");
//----- used functions -----

/**
* Prints debug info based on variable $debug
* @param toprint is string to be printed
*/
function decho($toprint){
  global $debug;
  if($debug) echo $toprint;
}

/**
* Checks if variable exists
* @param str is name of variable to be checked
* @return true if variable exists, false otherwise
*/
function varCheck($str){
  if(strpos($str, "@") !== false){
    $prefix = substr($str, 0, 2);
    $name = substr($str, 3);
    if( ($prefix == "GF" || $prefix == "LF" || $prefix == "TF")
          && preg_match("/^([\w_\-$&%*!?]+)$/", $name)){
      decho(" varCheck      \e[32mIS VAR\e[0m [$str]\n");
      return true;
    }
  }
  decho(" varCheck      \e[31mNOT VAR\e[0m [$str]\n");
  return false;
}

/**
* Checks if symbol is ok
* @param str is symbol to be checked
* @return type (int, bool, string, nil, var) if symbol matches the requirements of symbol, false otherwise
*/
function symbCheck($str){
  if(($cut = strpos($str, "@")) !== false){
    $prefix = substr($str, 0, $cut);
    $name = substr($str, $cut + 1);
    if($prefix == "GF" || $prefix == "LF" || $prefix == "TF"){
      //i am variable
      decho(" symbCheck     \e[33mVARIABLE RECOGNIZED\e[0m passing [$str] to varCheck\n");
      if(!varCheck($str)){
        return false;
      }
      return "var";
    } elseif($prefix == "int" || $prefix == "bool" || $prefix == "string" || $prefix == "nil"){
      //i am constant
      switch($prefix){
        case "int" :
          if(preg_match("/^[-]?\d+$/", $name)){
            decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
            return "int";
          }
          break;
        case "bool" :
          if($name == "true" || $name == "false"){
            decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
            return "bool";
          }
          break;
        case "string":
          for($i = 0; $i < strlen($name); $i++){
            if($name[$i] == "\\"){
              //checking escape sequence - ascii now i guess
              if(!((isset($name[$i + 1]) && $name[$i + 1] <= '9' && $name[$i + 1] >= '0')
                && (isset($name[$i + 2]) && $name[$i + 2] <= '9' && $name[$i + 2] >= '0')
                && (isset($name[$i + 3]) && $name[$i + 3] <= '9' && $name[$i + 3] >= '0'))){
                  //failed escape sequence
                  decho(" symbCheck     \e[31mFAIL\e[0m [$str] bad escape sequence\n");
                  return false;
                }
                $i += 3;
            }
          }
          decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
          return "string";
          break;
        case "nil":
          if($name == "nil"){
            decho(" symbCheck     \e[33mIS SYMBOL\e[0m [$str]\n");
            return "nil";
          break;
          }
          break;
        case "float":
          if(preg_match("/^[+-]?0x[0-9abcde]+\.[0-9abcde]+p[+-]?[0-9]+$/", $name)){
            decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
            return "int";
          }
      }
    }
  }
  decho(" symbCheck     \e[31mFAIL\e[0m [$str]\n");
  return false;
}

/**
* Checks if given string is label
* @param str is string to be checked
* @return true if str is label, false otherwise
*/
function labelCheck($str){
  if(preg_match("/^([\w_\-$&%*!?]+)$/", $str)){
    decho(" labelCheck    \e[32mGOOD\e[0m [$str]\n");
    $name = $str; $type = "label";
    return true;
  }
  decho(" labelCheck    \e[31mFAIL\e[0m [$str]\n");
  return false;
}

//----- main script -----
// dealing with parameters - help part
if(count($argv) > 2){
  foreach($argv as $value){
    if($value == "--help" || $value == "-h"){
      decho("\e[31mWRONG PARAMETERS\e[0m use just \"--help\" \n");
      exit(10);
    }
  }
} elseif(count($argv) == 2) {
  if($argv[1] == "--help" || $argv[1] == "-h"){
    echo "\n   Expects intput of type IPPcode20 on standard input,checks if its syntax is correct and produce
   XML representation of inputed program. Also it can make stat file.
   Options :
   \"--help\" or \"-h\"   to print help info
   \"--source=[file]\"  to set on the output stat file and give location
   \"--loc\"            to mention number of lines in stat file
   \"--comments\"       to mention number of comments in stat file
   \"--labels\"         to mention number of labels in stat file
   \"--jumps\"          to mention number of jumps in stat file
   if you use soume of the last four, you must set source file!\n\n";
    exit(ERR_OK);
  }
}

//loading file
$input=[];
while (FALSE !== ($line = fgets(STDIN))){
  array_push($input,str_replace("\n", '', trim($line)));
}
decho("------\033[0;34m LOADED  INPUT \033[0;37m------\n");
if($debug) print_r($input);
//deleting all comments
for($i=0; $i < count($input); $i++){
  if(($cut = strpos($input[$i], "#")) !== false){
    //line has a comment
    $state['numComments'] ++;
    $input[$i] = substr($input[$i], 0, $cut);
    trim($input[$i]);
  }
}
//looking for the header
$i = 0; $go = true;
do{
  if($input[$i] == ""){
    $i ++;
  } elseif($input[$i] == ".IPPcode20"){
    $i ++;
    $go = false;
  } else {
    //missing header
    decho("\e[31m MISSING HEADER\e[0m\n");
    exit(ERR_HEADER);
  }
} while($go);
decho("-----\033[0;34m TRIMMED COMENTS \033[0;37m-----\n");
if($debug) print_r($input);
//main checker
decho("------\033[0;34m MAIN CHECKER \033[0;37m-------\n");
for($i; $i < count($input); $i++){
  decho("\e[1mCHECKING LINE [$i] \e[0m-> $input[$i]\n");
  if(preg_match("/^(\w+)/", $input[$i], $m)){
    $opcode = strtoupper($m[0]);
    if (isset($rules[$opcode])){
      //opcode found in rules
      $state['numLoc'] ++;
      $num = count($rules[$opcode]['params']);
      $regexp = "/^(\w+)";
      for($j = 0; $j < $num; $j++){
        $regexp = $regexp . "\s+([^\s]+)";
      }
      $regexp = $regexp . "\s*$/";
      if(preg_match("$regexp", $input[$i], $m)){
        decho(" paramCheck    \e[32mGOOD\e[0m by [$regexp]\n");
      } else {
        //bad num of args
        decho(" paramCheck    \e[31mFAIL\e[0m by [$regexp]\n");
        exit(ERR_OTHER);
      }
      $outInstruction = $output->createElement("instruction");
      $outInstruction->setAttribute("order", $state['numLoc']);
      $outInstruction->setAttribute("opcode", $opcode);
      $j = 2;
      foreach($rules[$opcode]['params'] as $value){
        $name = "";
        $type = "";
        if($value == "var"){
          if(!varCheck($m[$j])){
            decho(" opcode $opcode \e[31mFAIL\e[0m by varCheck at [$m[$j]]\n");
            exit(ERR_OTHER);
          }
          $type = "var";
          $name = $m[$j];
        } elseif($value == "symb"){
          if(($type = symbCheck($m[$j])) === false){
            decho(" opcode $opcode \e[31mFAIL\e[0m by symbCheck at [$m[$j]]");
            exit(ERR_OTHER);
          }
          if($type == "var"){
            $name = $m[$j];
          } else {
            $cut = strpos($m[$j], "@");
            if($type == "string"){
              $name = htmlspecialchars(substr($m[$j], $cut + 1));
            } else {
              $name = substr($m[$j], $cut + 1);
            }
          }
        } elseif($value == "label"){
          if( !labelCheck($m[$j])){
            decho(" opcode $opcode \e[31mFAIL\e[0m by labelCheck at [$m[$j]]");
            exit(ERR_OTHER);
          }
          $type = "label";
          $name = $m[$j];
        } elseif($value == "type"){
          $name = $m[$j]; $type = "type";
          if($m[$j] != "int" && $m[$j] != "bool" && $m[$j] != "string"){
            decho(" opcode $opcode \e[31mFAIL\e[0m by typeCheck at [$m[$j]]");
            exit(ERR_OTHER);
          }
        }
        $outArgument = $output->createElement("arg" . ($j -1) );
        $outArgument->setAttribute("type", $type);
        $outArgument->nodeValue = $name;
        $outInstruction->appendChild($outArgument);
        $j++;
      }
      $outProgram->appendChild($outInstruction);
      if($opcode == "JUMP" || $opcode == "JUMPIFEQ" || $opcode == "JUMPIFNEQ" || $opcode == "CALL"){
        $state['numJumps'] ++;
      }
      if($opcode == "LABEL"){
        $state['numLabels'] ++;
      }
    } else {
      //opcode not found in rules
     decho("\e[31m BAD OPCODE\e[0m\n");
      exit(ERR_OPCODE);
    }
  } else {
    //no opcode on line
  }
}
decho("------\033[0;34m MAIN END \033[0;37m-------\n");
//dealing with parameters - stat part
$isfileset = false;
if(count($argv) > 1){
  foreach($argv as $value){
    if($value == "parse.php"){

    } elseif($value == "--loc" || $value == "--comments" || $value == "--labels" || $value == "--jumps"){
      array_push($state['order'], $value);
    } elseif(preg_match("/^--stats=(\w*.*)$/", $value, $m) !== false ||
              preg_match("/^--stats=\"(\w*.*)\"$/", $value, $m) !== false) {
          if($isfileset){
            decho("\e[31m MULTIPLE STATFILE PLACES\e[0m\n");
            exit(10);
          } else {
            $statfile = $m[1];
            $isfileset = true;
          }
    } else {
      decho("\e[31m UNKNOWN OPTION\e[0m\n");
      // skip or let go ??
    }
  }
  if(!$isfileset){
    decho("\e[31m NO FILE SET\e[0m\n");
    exit(10);
  }
  if(($handle = fopen($statfile, "w")) === false){
    exit(99);
  }

  foreach($state['order'] as $value){
    switch($value){
      case "--loc":
        fprintf($handle, "%d\n", $state['numLoc']);
        break;
      case "--comments":
        fprintf($handle, "%d\n", $state['numComments']);
        break;
      case "--labels":
        fprintf($handle, "%d\n", $state['numLabels']);
        break;
      case "--jumps":
        fprintf($handle, "%d\n", $state['numJumps']);
        break;
      default:
        exit(99);
    }
  }
  fclose($handle);
}
if($debug) print_r($state);
$output->appendChild($outProgram);
print $output->saveXML();
exit(ERR_OK);
?>
