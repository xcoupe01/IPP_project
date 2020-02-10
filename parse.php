<?php
/* analyzer of IPPcode20
   author : Vojtech Coupek - xcoupe01 */

//----- definig variables -----
$debug = true; // enables debug prints
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
        //arithmetic operations, bool operation and conversion
        'ADD'         =>['params'=>['var','symb','symb']],
        'SUB'         =>['params'=>['var','symb','symb']],
        'MUL'         =>['params'=>['var','symb','symb']],
        'IDIV'        =>['params'=>['var','symb','symb']],
        'LT'          =>['params'=>['var','symb','symb']],
        'GT'          =>['params'=>['var','symb','symb']],
        'EQ'          =>['params'=>['var','symb','symb']],
        'AND'         =>['params'=>['var','symb','symb']],
        'OR'          =>['params'=>['var','symb','symb']],
        'NOT'         =>['params'=>['var','symb','symb']],
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

$state = ['numLoc'       => 0,                         //< num of lines with opcodes
          'numComments'  => 0,                         //< num of comments
          'numLabels'    => 0,                         //< number of labels
          'numJumps'     => 0];                        //< num of jumps

$output = []; //< output XML file that will be outputed if the whole input is correct
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
  global $state;
  if(strpos($str, "@") !== false){
    $prefix = substr($str, 0, 2);
    $name = substr($str, 3);
    if(($prefix == "GF" || $prefix == "LF" || $prefix == "TF") && strlen($name) > 0){
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
* @return true if symbol matches the requirements of symbol, false otherwise
*/
function symbCheck($str){
  global $state;
  if(($cut = strpos($str, "@")) !== false){
    $prefix = substr($str, 0, $cut);
    $value = substr($str, $cut + 1);
    if($prefix == "GF" || $prefix == "LF" || $prefix == "TF"){
      //i am variable
      decho(" symbCheck     \e[33mVARIABLE RECOGNIZED\e[0m passing [$str] to varCheck\n");
      return varCheck($str);
    } elseif($prefix == "int" || $prefix == "bool" || $prefix == "string" || $prefix == "nil"){
      //i am constant
      switch($prefix){
        case "int" :
          if(preg_match("/^\d+$/", $value) || preg_match("/^-\d+$/", $value)){
            decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
            return true;
          }
          break;
        case "bool" :
          // can I use TRUE aswell ? ---------------------------------------------------- TODO
          if($value == "true" || $value == "false"){
            decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
            return true;
          }
          break;
        case "string":
          // check only escape sequences ?? --------------------------------------------- TODO
          for($i = 0; $i < strlen($value); $i++){
            if($value[$i] == "\\"){
              //checking escape sequence - ascii now i guess
              if(!(($value[$i + 1] <= '9' && $value[$i + 1] >= '0')
                && ($value[$i + 2] <= '9' && $value[$i + 2] >= '0')
                && ($value[$i + 3] <= '9' && $value[$i + 3] >= '0'))){
                  //failed escape sequence
                  decho(" symbCheck     \e[31mFAIL\e[0m [$str] bad escape sequence\n");
                  return false;
                }
                $i += 3;
            }
          }
          decho(" symbCheck     \e[32mIS SYMBOL\e[0m [$str]\n");
          return true;
          break;
        case "nill" :
          if($value == "nill"){
            decho(" symbCheck     \e[33mIS SYMBOL\e[0m [$str]\n");
            return true;
          }
          break;
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
  if(preg_match("/^([^-\s]+)$/", $str)){
    decho(" labelCheck    \e[32mGOOD\e[0m [$str]\n");
    return true;
  }
  decho(" labelCheck    \e[31mFAIL\e[0m [$str]\n");
  return false;
}

//----- main script -----
//add loading prog params --------------------------------------------------------------- TODO
//loading file
$input=[];
$i = 0;
while (FALSE !== ($line = fgets(STDIN))){
  array_push($input,str_replace("\n", '', trim($line)));
}
decho("------\033[0;34m LOADED  INPUT \033[0;37m------\n");
if($debug) print_r($input);
//looking for the header
if($input[0] != ".IPPcode20"){
  //missing header
  exit(ERR_HEADER);
}
//deleting all comments
for($i=1; $i < count($input); $i++){
  if(($cut = strpos($input[$i], "#")) !== false){
    //line has a comment
    $state['numComments'] ++;
    $input[$i] = substr($input[$i], 0, $cut);
  }
}
decho("-----\033[0;34m TRIMMED COMENTS \033[0;37m-----\n");
if($debug) print_r($input);
//main checker
decho("------\033[0;34m MAIN CHECKER \033[0;37m-------\n");
for($i=1; $i < count($input); $i++){
  decho("\e[1mCHECKING LINE [$i] \e[0m-> $input[$i]\n");
  if(preg_match("/^(\w+)/", $input[$i], $m)){
    $opcode = strtoupper($m[0]);
    if (isset($rules[$opcode])){
      //opcode found in rules
      $state['numLoc'] ++;
      $num = count($rules[$opcode]['params']);
      $regexp = "/^(\w+)";
      for($j = 0; $j < $num; $j++){
        $regexp = $regexp . "\s+([^-\s]+)";
      }
      $regexp = $regexp . "$/";
      if(preg_match("$regexp", $input[$i], $m)){
        decho(" paramCheck    \e[32mGOOD\e[0m by [$regexp]\n");
      } else {
        //bad num of args
        decho(" paramCheck    \e[31mFAIL\e[0m by [$regexp]\n");
        exit(ERR_OTHER);
      }
      $j = 2;
      foreach($rules[$opcode]['params'] as $value){
        if($value == "var" && !varCheck($m[$j])){
          decho(" opcode $opcode \e[31mFAIL\e[0m by varCheck at [$m[$j]]");
          exit(ERR_OTHER);
        } elseif($value == "symb" && !symbCheck($m[$j])){
          decho(" opcode $opcode \e[31mFAIL\e[0m by symbCheck at [$m[$j]]");
          exit(ERR_OTHER);
        } elseif($value == "label" && !labelCheck($m[$j])){
          decho(" opcode $opcode \e[31mFAIL\e[0m by labelCheck at [$m[$j]]");
          exit(ERR_OTHER);
        } elseif($value == "type" && ($m[$j] != "int" && $m[$j] != "bool" && $m[$j] != "string")){
          decho(" opcode $opcode \e[31mFAIL\e[0m by typeCheck at [$m[$j]]");
          exit(ERR_OTHER);
        }
        $j++;
      }
      if($opcode == "JUMP" || $opcode == "JUMPIFEQ" || $opcode == "JUMPINEQ"){
        $state['numJumps'] ++;
      }
      if($opcode == "LABEL"){
        $state['numLabels'] ++;
      }
    } else {
      //opcode not found in rules
      exit(ERR_OPCODE);
    }
  } else {
    //no opcode on line
  }
}
decho("------\033[0;34m MAIN END \033[0;37m-------\n");
if($debug) print_r($state);
exit(ERR_OK);
?>
