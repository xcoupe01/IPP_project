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

$state = ['GF'=>[],             //< global frame of variables
          'TFDef'=>false,       //< temporary frame definition lock
          'TF'=>[],             //< temporary frame of variables
          'LFDef'=>false,       //< local frame definition lock
          'LF'=>[],             //< stack of local frames wit variables
          'stack'=>[],          //< data stack
          'labes'=>[]];         //< label stack
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
  if(strpos($str, "@") === true){
    $prefix = substr($str, 0, 2);
    $name = substr($str, 3);
    if($prefix == "GF"){
      if(isset($state['globalFrame'[$name]])){
        decho(" varCheck      \e[32mVAR FOUND\e[0m [$str]\n");
        return true;
      }
    } elseif($prefix == "TF"){
      if($state[$prefix . 'Def'] && isset($state[$prefix[$name]])){
        decho(" varCheck      \e[32mVAR FOUND\e[0m [$str]\n");
        return true;
      }
    } elseif($prefix == "LF"){
      $num ; //-------------------------------------------------------------------------- TODO -- num of local arrays
      if($state[$prefix . 'Def'] && isset($state[$prefix[$num/*num of frame*/[$name]]])) {
        decho(" varCheck      \e[32mVAR FOUND\e[0m [$str]\n");
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
        // weird ------------------------------------------------------------------------ TODO -- important not working at all
        array_push($state[$prefix], $name);
        decho(" varCreate     \e[32mGOOD\e[0m variable [$name] defined in [$prefix]\n");
        return true;
      } elseif($prefix == "TF"){
        if($state[$prefix . 'Def']){
          array_push($state[$prefix], $name);
          decho(" varCreate     \e[32mGOOD\e[0m variable [$name] defined\n");
          return true;
        }
      } elseif($prefix == 'LF'){
        if($state[$prefix . 'Def']){
          $num = //---------------------------------------------------------------------- TODO -- calculate the number of arrays
          array_push($state[$prefix[/*number of frame*/$num]], $name);
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
      $num ; //-------------------------------------------------------------------------- TODO -- num of local frame array
      $state[$prefix[$num[$value]]] = $set;
    } else {
      $state[$prefix[$value]] = $set;
    }
    decho(" varSet        \e[32mGOOD\e[0m variable [$value] set to value [$set]\n");
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
      $num ; //-------------------------------------------------------------------------- TODO -- num of local frame array
      decho(" varGet        \e[32mGOOD\e[0m variable [$value] of value [" . $state[$prefix[$value]] . "]\n");
      return $state[$prefix[$num[$value]]];
    } else {
      decho(" varGet        \e[32mGOOD\e[0m variable [$value] of value [" . $state[$prefix[$value]] . "]\n");
      return $state[$prefix[$value]];
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
      decho(" symbCheck     \e[33mVARIABLE RECOGNIZED\e[0m passing [$str] to varcheck\n");
      return varCheck($str);
    } elseif($prefix == "int" || $prefix == "bool" || $prefix == "string" || $prefix == "nil"){
      //i am constant
      switch($prefix){
        case "int" :
          if(preg_match("/^\d+$/", $value) || preg_match("/^-\d+$/")){
            decho(" symbCheck     \e[33mGOOD\e[0m [$str]\n");
            return true;
          }
          break;
        case "bool" :
          // can I use TRUE aswell ? ---------------------------------------------------- TODO
          if($value == "true" || $value == "false"){
            decho(" symbCheck     \e[33mGOOD\e[0m [$str]\n");
            return true;
          }
          break;
        case "string":
          // check only escape sequences ?? --------------------------------------------- TODO
          for($i = 0; $i < strlen($value); $i++){
            if($value[$i] == "\\"){
              //checking escape sequence
              if(!(($value[$i + 1] <= 57 && $value[$i + 1] >= 48)
                && ($value[$i + 2] <= 57 && $value[$i + 2] >= 48)
                && ($value[$i + 3] <= 57 && $value[$i + 3] >= 48))){
                  //failed escape sequence
                  decho(" symbCheck     \e[31mFAIL\e[0m [$str]\n");
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
* Get the value of symbol (variable or constant), also before getting it checks
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
      decho(" symbGet       \e[32mGOOD\e[0m variable [$value] of value " . $state[$prefix[$value]] . "\n");
      return $state[$prefix[$value]];
    } elseif($prefix == "int" || $prefix == "bool" || $prefix == "string" || $prefix == "nil"){
      //symb is constant
      decho(" symbGet       \e[32mGOOD\e[0m symbol [$str] of value [$value]\n");
      return $value;
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
  if(isset($state['labels'[$str]])){
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
    array_push($state['lables'], $str);
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
            //bad symbol
          }
          if(!varSet($var, $val)){
            //variable doesnt exist
          }
          break;
        case "CREATEFRAME":
          // CREATEFRAME
          // creates new temporary frame
          $state['TFDef'] = true;
          $state['TF'] = [];
          break;
        case "PUSHFRAME":
          // PUSHFRAME
          // pushes temporary frame to local frame stack
          $state['TFDef'] = false;
          array_push($state['LF'], $state['TF']);
          $state['TF'] = [];
          break;
        case "POPFRAME":
          // POPFRAME
          // pops local frame to temporary frame
          $state['TFDef'] = true;
          $state['TF'] = array_pop($state['LF']);
          break;
        case "DEFVAR":
          // DEFVAR <var>
          $var = $m[2];
          if(!varCreate($var)){
            //redefinition
          }
          break;
        case "CALL":
          break;
        case "RETURN":
          break;
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
