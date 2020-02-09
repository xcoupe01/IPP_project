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
        'ADD'         =>['params'=>['var','symb1','symb2']],
        'SUB'         =>['params'=>['var','symb1','symb2']],
        'MUL'         =>['params'=>['var','symb1','symb2']],
        'IDIV'        =>['params'=>['var','symb1','symb2']],
        'LT'          =>['params'=>['var','symb1','symb2']],
        'GT'          =>['params'=>['var','symb1','symb2']],
        'EQ'          =>['params'=>['var','symb1','symb2']],
        'AND'         =>['params'=>['var','symb1','symb2']],
        'OR'          =>['params'=>['var','symb1','symb2']],
        'NOT'         =>['params'=>['var','symb1','symb2']],
        'INT2CHAR'    =>['params'=>['var','symb']],
        'STRI2INT'    =>['params'=>['var','symb1','symb2']],
        //input output instructions
        'READ'        =>['params'=>['var','type']],
        'WRITE'       =>['params'=>['symb']],
        //string operations
        'CONCAT'      =>['params'=>['var','symb1','symb2']],
        'STRLEN'      =>['params'=>['var','symb']],
        'GETCHAR'     =>['params'=>['var','symb1','symb2']],
        'SETCHAR'     =>['params'=>['var','symb1','symb2']],
        //type operations
        'TYPE'        =>['params'=>['var','symb']],
        //jumps
        'LABEL'       =>['params'=>['label']],
        'JUMP'        =>['params'=>['label']],
        'JUMPIFEQ'    =>['params'=>['label','symb1','symb2']],
        'JUMPIFNEQ'   =>['params'=>['label','symb1','symb2']],
        'EXIT'        =>['params'=>['symb']],
        //debuging
        'DPRINT'      =>['params'=>['symb']],
        'BREAK'       =>['params'=>[]],
        ];

$state = ['GF'           =>[],                         //< global frame of variables
          'TFDef'        =>false,                      //< temporary frame definition lock
          'TF'           =>[],                         //< temporary frame of variables
          'LFDef'        =>false,                      //< local frame definition lock
          'LF'           =>[],                         //< stack of local frames wit variables
          'stack'        =>[],                         //< data stack
          'labes'        =>[],                         //< label stack
          'numLoc'       => 0,                         //< num of lines with opcodes
          'numComments'  => 0,                         //< num of comments
          'numLabels'    => count($state['labels']),   //<
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
    if($prefix == "GF"){
      if(isset($state[$prefix][$name])) {
        decho(" varCheck      \e[33mVAR FOUND\e[0m [$str]\n");
        return true;
      }
    } elseif($prefix == "TF"){
      if($state[$prefix . 'Def'] && isset($state[$prefix][$name])){
        decho(" varCheck      \e[33mVAR FOUND\e[0m [$str]\n");
        return true;
      }
    } elseif($prefix == "LF"){
      $num = count($state[$prefix]) - 1;
      if($state[$prefix . 'Def'] && isset($state[$prefix][$num][$name])) {
        decho(" varCheck      \e[33mVAR FOUND\e[0m [$str]\n");
        return true;
      }
    }
  }
  decho(" varCheck      \e[33mVAR NOT FOUND\e[0m [$str]\n");
  return false;
}

/**
* Create variable
* @param str is variable to be created
* @return true if successful, false otherwise
*/
function varCreate($str){
  global $state;
  if(!varCheck($str)){
    if(strpos($str, "@") !== false){
      $prefix = substr($str, 0, 2);
      $name = substr($str, 3);
      if ($prefix == "GF"){
        $state[$prefix][$name]='';
        decho(" varCreate     \e[32mGOOD\e[0m variable [$name] defined in [$prefix]\n");
        return true;
      } elseif($prefix == "TF"){
        if($state[$prefix . 'Def']){
          $state[$prefix][$name] = 'undef';
          decho(" varCreate     \e[32mGOOD\e[0m variable [$name] defined\n");
          return true;
        }
      } elseif($prefix == 'LF'){
        if($state[$prefix . 'Def']){
          $num = count($state[$prefix]) - 1;
          $state[$prefix][$num][$name];
          decho(" varCreate     \e[32mGOOD\e[0m variable [$name] defined\n");
          return true;
        }
      }
    }
  }
  decho(" varCreate     \e[31mFAIL\e[0m variable [$str] redefinition\n");
  return false;
}

/**
* Sets variable to given value
* @param var is variable to be updated
* @param set is the value the variable is beeing seted to
* @return true if successful, false otherwise
*/
function varSet($var, $set){
  global $state;
  if(varCheck($var)){
    $cut = strpos($var, "@");
    $prefix = substr($var, 0, $cut);
    $value = substr($var, $cut + 1);
    if($prefix == 'LF'){
      $num = count($state[$prefix]) - 1;
      $state[$prefix][$num][$value] = $set;
    } else {
      $state[$prefix][$value] = $set;
    }
    decho(" varSet        \e[32mGOOD\e[0m variable [$value] set to type [$set]\n");
    return true;
  }
  decho(" varSet        \e[31mFAIL\e[0m variable [$var] not defined\n");
  return false;
}

/**
* Get the value of variable also before getting it checks its correct
* @param str is variable to be given
* @return value if the variable is correct, false otherwise
*/
function varGet($str){
  global $state;
  if(varCheck($str)){
    $cut = strpos($str, "@");
    $prefix = substr($str, 0, $cut);
    $value = substr($str, $cut + 1);
    if($prefix == "LF"){
      $num = count($state[$prefix]) - 1;
      decho(" varGet        \e[32mGOOD\e[0m variable [$value] of value [" . $state[$prefix][$value] . "]\n");
      return $state[$prefix][$num][$value];
    } else {
      decho(" varGet        \e[32mGOOD\e[0m variable [$value] of value [" . $state[$prefix][$value] . "]\n");
      return $state[$prefix][$value];
    }
  }
  decho(" varGet        \e[31mFAIL\e[0m [$str] \n");
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
            decho(" symbCheck     \e[32mGOOD\e[0m [$str]\n");
            return true;
          }
          break;
        case "bool" :
          // can I use TRUE aswell ? ---------------------------------------------------- TODO
          if($value == "true" || $value == "false"){
            decho(" symbCheck     \e[32mGOOD\e[0m [$str]\n");
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
          decho(" symbCheck     \e[32mGOOD\e[0m [$str]\n");
          return true;
          break;
        case "nill" :
          if($value == "nill"){
            decho(" symbCheck     \e[33mGOOD\e[0m [$str]\n");
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
* Get the value (type) of symbol (variable or constant), also before getting it checks
* if its correct
* @param str is symbol to be given
* @return value if the symbol is correct, false otherwise
*/
function symbGet($str){
  global $state;
  if(symbCheck($str)){
    $cut = strpos($str, "@");
    $prefix = substr($str, 0, $cut);
    $value = substr($str, $cut + 1);
    if($prefix == "GF" || $prefix == "LF" || $prefix == "TF"){
      //symb is variable
      decho(" symbGet       \e[32mGOOD\e[0m variable [$value] of type " . $state[$prefix][$value] . "\n");
      return $state[$prefix][$value];
    } elseif($prefix == "int" || $prefix == "bool" || $prefix == "string" || $prefix == "nil"){
      //symb is constant
      decho(" symbGet       \e[32mGOOD\e[0m symbol [$str] of type [$prefix]\n");
      return $prefix;
    }
  }
  decho(" symbGet       \e[31mFAIL\e[0m [$str]\n");
  return false;
}

/**
* Checks if label exists
* @param str is name of label to be checked
* @return true if label exists, false otherwise
*/
function labelCheck($str){
  global $state;
  if(isset($state['labels'][$str])){
    decho(" labelCheck    \e[33mEXISTS\e[0m [$str]\n");
    return true;
  }
  decho(" labelCheck    \e[33mDONT EXIST\e[0m [$str]\n");
  return false;
}

/**
* Creates new lable
* @param str is name of new lable
* @return true if successful, false otherwise
*/
function labelCreate($str){
  global $state;
  if(!labelCheck($str)){
    $state['lables'][$str] = '';
    decho(" labelCreate   \e[32mFAIL\e[0m label [$str] created\n");
    return true;
  }
  decho(" labelCreate   \e[31mFAIL\e[0m label [$str] redefinition\n");
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
      switch($opcode){
        case "MOVE":
          // MOVE <var> <symb>
          // copies value of symbol to variable
          $var = $m[2];  //need to check if var exists
          $symb = $m[3]; //need to check if its constatnt or var
          if(($val = symbGet($symb)) === false){
            //bad symb
            decho(" opcode MOVE   \e[31mFAIL\e[0m bad <symb>\n");
            exit(ERR_OTHER);
          }
          if($val == "undef"){
            decho(" opcode MOVE   \e[33mWARN\e[0m undefined variable\n");
          }
          if(!varSet($var, $val)){
            //variable doesnt exist
            decho(" opcode MOVE   \e[31mFAILED\e[0m undefined <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode MOVE   \e[32mGOOD\e[0m\n");
          break;
        case "CREATEFRAME":
          // CREATEFRAME
          // creates new temporary frame
          $state['TFDef'] = true;
          $state['TF'] = [];
          decho(" opcode CRFR   \e[32mGOOD\e[0m\n");
          break;
        case "PUSHFRAME":
          // PUSHFRAME
          // pushes temporary frame to local frame stack
          if(!$state['TFDef']){
            //temporary frame not defined
            decho(" opcode PUFR   \e[31mFAILED\e[0m undefined temporary frame\n");
            exit(ERR_OTHER);
          }
          $state['TFDef'] = false;
          array_push($state['LF'], $state['TF']);
          $state['TF'] = [];
          decho(" opcode PUFR   \e[32mGOOD\e[0m\n");
          break;
        case "POPFRAME":
          // POPFRAME
          // pops local frame to temporary frame
          if(!$state['LFDef']){
            decho(" opcode POFR   \e[31mFAILED\e[0m undefined local frame\n");
            exit(ERR_OTHER);
          }
          $state['TFDef'] = true;
          $state['TF'] = array_pop($state['LF']);
          if(count($state['LF']) == 0){
            $state['TFDef'] = false;
          }
          decho(" opcode POFR   \e[32mGOOD\e[0m\n");
          break;
        case "DEFVAR":
          // DEFVAR <var>
          $var = $m[2];
          if(!varCreate($var)){
            decho(" opcode DEFVAR \e[31mFAIL\e[0m redefinition of <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode DEFVAR \e[32mGOOD\e[0m\n");
          break;
        case "CALL":
          // CALL <label> --------------------------------------------------------------- TODO -- save PC ?
          // jumps to label and saves incremented PC
          $label = m[2];
          if(!labelCheck($label)){
            //label does not exist
            decho(" opcode CALL   \e[31mFAIL\e[0m undefined <label>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode CALL   \e[32mGOOD\e[0m\n");
          break;
        case "RETURN":
          // RETURN --------------------------------------------------------------------- TODO -- something else ?
          // returns to saved PC by call
          break;
        case "PUSHS":
          // PUSH <symb>
          // pushes symbol to stack
          $symb = $m[2];
          if(($val = symbGet($symb)) === false){
            //bad symbol
            decho(" opcode PUSHS  \e[31mFAIL\e[0m bad <symb>\n");
            exit(ERR_OTHER);
          }
          if($val == "undef"){
            decho(" opcode PUSHS  \e[33mWARN\e[0m undefined variable\n");
          }
          array_push($state['stack'], $val);
          decho(" opcode PUSHS  \e[32mGOOD\e[0m\n");
          break;
        case "POPS":
          // POPS <var>
          // pops top of the stack to var
          $var = $m[2];
          if(!varSet($var, array_pop($state['stack']))){
            decho(" opcode POPS   \e[31mFAIL\e[0m udnefined <var>\n");
            exit(ERR_OTHER);
          }
          if(varGet($var) === "undef"){
            decho(" opcode POPS   \e[33mWARN\e[0m undefined variable\n");
          }
          decho(" opcode POPS   \e[32mGOOD\e[0m\n");
          break;
        case "ADD":
          // ADD <var> <symb1> <symb2>
          // adds two int symbols and store them to variable
        case "SUB":
          // SUB <var> <symb1> <symb2>
          // subtracts two int symbols and store the to variable
        case "MUL":
          // MUL <var> <symb1> <symb2>
          // multyply two int symbols and store them to variable
        case "IDIV":
          // IDIV <var> <symb1> <symb2>
          // divide two int symbols and store the to variable
          $symb1 = $m[3];
          $symb2 = $m[4];
          $var = $m[2];
          if(!($val1 = symbGet($symb1))){
            //bad symb1
            decho(" opcode $m[1]    \e[31mFAIL\e[0m bad <symb1> \n");
            exit(ERR_OTHER);
          }
          if(!($val2 = symbGet($symb2))){
            //bad symb2
            decho(" opcode $m[1]    \e[31mFAIL\e[0m bad <symb2>\n");
            exit(ERR_OTHER);
          }
          if($val1 == "undef"){
            decho(" opcode $m[1]    \e[33mWARN\e[0m redefinig undefined variable from <symb1> to int\n");
            varSet($symb1, "int");
            $val1 = "int";
          }
          if($val2 == "undef"){
            decho(" opcode $m[1]    \e[33mWARN\e[0m redefinig undefined variable from <symb1> to int\n");
            varSet($symb2, "int");
            $val2 = "int";
          }
          if($val1 != "int" && $val2 != "int" ){
            decho(" opcode $m[1]   \e[31mFAIL\e[0m <symb1> or/and <symb2> not integer\n");
          }
          if(!varSet($var, $val1)){
            //bad var
            decho(" opcode ADD    \e[31mFAIL\e[0m undefined <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode $m[1]    \e[32mGOOD\e[0m\n");
          break;
        case "LT":
          // LT <var> <symb1> <symb2>
          // if symb1 is less then symb2 var is true, false otherwise
        case "GT":
          // GT <var> <symb1> <symb2>
          // if symb1 is greater then symb2 var is true, false otherwise
        case "EQ":
          // EQ <var> <symb1> <symb2>
          // if symb1 is equal to symb2 var is true, false otherwise
          $var = $m[2];
          $symb1 = $m[3];
          $symb2 = $m[4];
          if(!($val1 = symbGet($symb1))){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m bad <symb1>\n");
            exit(ERR_OTHER);
          }
          if(!($val2 = symbGet($symb2))){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m bad <symb2>\n");
            exit(ERR_OTHER);
          }
          if($val1 == "nil" || $val2 == "nil"){
            if($m[1] == "EQ"){
              if(!varSet($var, "bool")){
                decho(" opcode $m[1]       \e[31mFAIL\e[0m undefined <var>\n");
                exit(ERR_OTHER);
              }
              decho(" opcode $m[1]       \e[32mGOOD\e[0m\n");
              break;
            } else {
              decho(" opcode $m[1]       \e[31mFAIL\e[0m <symb1> or/and <symb2> bad types\n");
              exit(ERR_OTHER);
            }
          }
          if($val1 != $val2){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m <symb1> or/and <symb2> bad types\n");
            exit(ERR_OTHER);
          }
          if(!varSet($var, "bool")){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m undefined <var>\n");
            echo(ERR_OTHER);
          }
          decho(" opcode $m[1]       \e[32mGOOD\e[0m\n");
          break;
        case "AND":
          // AND <var> <symb1> <symb2>
          // logical and between symbol1 and symbol2 stored to variable
        case "OR":
          // OR <var> <symb1> <symb2>
          // logical or between symbol1 and symbol2 stored to variable
          $var = $m[2];
          $symb1 = $m[3];
          $symb2 = $m[4];
          if(!($val1 = symbGet($symb1))){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m bad <symb1>\n");
            exit(ERR_OTHER);
          }
          if(($val2 = symbGet($symb2))){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m bad <symb2>\n");
            exit(ERR_OTHER);
          }
          if($val1 == "undef"){
            decho(" opcode $m[1]    \e[33mWARN\e[0m redefinig undefined variable from <symb1> to bool\n");
            varSet($symb1, "bool");
            $val1 = "bool";
          }
          if($val2 == "undef"){
            decho(" opcode $m[1]    \e[33mWARN\e[0m redefinig undefined variable from <symb2> to bool\n");
            varSet($symb2, "bool");
            $val2 = "bool";
          }
          if($val1 != "bool" || $val2 != "bool"){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m bad <symb1> and/or <symb2> types\n");
            exit(ERR_OTHER);
          }
          if(!varSet($var, "bool")){
            decho(" opcode $m[1]       \e[31mFAIL\e[0m undefined <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode $m[1]       \e[32mGOOD\e[0m\n");
          break;
        case "NOT":
          // NOT <var> <symb>
          // logical and between symbol1 and symbol2 stored to variable
          $var = $m[2];
          $symb = $m[3];
          if(!($val = symbGet($symb))){
            decho(" opcode NOT    \e[31mFAIL\e[0m bad <symb>\n");
            exit(ERR_OTHER);
          }
          if($val == "undef"){
            decho(" opcode NOT    \e[33mWARN\e[0m redefinig undefined variable from <symb> to bool\n");
            varSet($symb, "bool");
            $val = "bool";
          }
          if($val != "bool"){
            decho(" opcode NOT    \e[31mFAIL\e[0m bad <symb> type\n");
            exit(ERR_OTHER);
          }
          if(!varSet($var, "bool")){
            decho(" opcode NOT    \e[31mFAIL\e[0m undefined <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode NOT    \e[32mGOOD\e[0m\n");
        case "INT2CHAR":
          // INT2CHAR <var> <symb>
          // numerical value in symb is converted to char and saved to variable
          $var = $m[2];
          $smyb = $m[3];
          if(!($val = symbGet($symb))){
            decho(" opcode I2CHAR \e[31mFAIL\e[0m bad <symb>\n");
            exit(ERR_OTHER);
          }
          if($val == "undef"){
            decho(" opcode I2CHAR \e[33mWARN\e[0m redefinig undefined variable from <symb> to int\n");
            varSet($symb, "int");
            $val = "int";
          }
          if($val != "int"){
            decho(" opcode I2CHAR \e[31mFAIL\e[0m bad <symb> type\n");
            exit(ERR_OTHER);
          }
          if(!varSet($var, "bool")){
            decho(" opcode I2CHAR \e[31mFAIL\e[0m undefined <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode I2CHAR \e[32mGOOD\e[0m\n");
          break;
        case "STRI2INT":
          // STRI2INT <var> <symb1> <symb2>
          // saves int of char in string of symbol1 at position symbol2 to variable
          $var = $m[2];
          $smyb1 = $m[3];
          $symb2 = $m[4];
          if(!($val1 = symbGet($symb1))){
            decho(" opcode S2INT  \e[31mFAIL\e[0m bad <symb1>\n");
            exit(ERR_OTHER);
          }
          if(!($val2 = symbGet($symb2))){
            decho(" opcode S2INT  \e[31mFAIL\e[0m bad <symb2>\n");
            exit(ERR_OTHER);
          }
          if($val1 == "undef"){
            decho(" opcode S2INT  \e[33mWARN\e[0m redefinig undefined variable from <symb1> to string\n");
            varSet($symb1, "string");
            $val1 = "string";
          }
          if($val2 == "undef"){
            decho(" opcode S2INT  \e[33mWARN\e[0m redefinig undefined variable from <symb2> to int\n");
            varSet($symb2, "int");
            $val2 = "int";
          }
          if($val1 != "string" || $val2 != "int"){
            decho(" opcode S2INT  \e[31mFAIL\e[0m bad <symb1> and/or <symb2> types\n");
            exit(ERR_OTHER);
          }
          if(!varSet($var, "int")){
            decho(" opcode S2INT  \e[31mFAIL\e[0m undefined <var>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode S2INT  \e[32mGOOD\e[0m\n");
          break;
        case "READ":
          // READ <var> <type> ---------------------------------------------------------- TODO
          // saves red data from input to variable
          break;
        case "WRITE":
          // WRITE <symb>
          // writes symbol to output
          $symb =$m[2];
          if(!($val = symbGet($symb))){
            decho(" opcode WRITE  \e[31mFAIL\e[0m bad <symb1>\n");
            exit(ERR_OTHER);
          }
          decho(" opcode WRITE  \e[32mGOOD\e[0m\n");
          break;
        case "CONCAT":
        case "STRLEN":
        case "GETCHAR":
        case "SETCHAR":
        case "TYPE":
        case "LABEL":
        case "JUMP":
        case "JUMPIFEQ":
        case "JUMPIFNQE":
        case "EXIT":
        case "DPRINT":
        case "BREAK":
          break;
        default :
          decho("\e[31mHARD INTERNAL FAIL\e[0m\n");
          exit(99);
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

/*
$shortopts = "";
$shortopts .= "h";

$longopts = array(
  "help"
);

$arguments = getopt($shortopts, $longopts);
printf("%d \n", count($arguments)) ;
var_dump($arguments);
*/

?>
