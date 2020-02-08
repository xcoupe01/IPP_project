<?php
/* analyzer of IPPcode20
   author : Vojtech Coupek - xcoupe01 */

$debug = true; // enables debug prints

function decho($toprint){
  global $debug;
  if($debug) echo $toprint . "\n";
}

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

//loading file
$input=[];
$i = 0;
while (FALSE !== ($line = fgets(STDIN))){
  array_push($input,str_replace("\n", '', trim($line)));
}
decho("------\033[0;34m LOADED  INPUT \033[0;37m------");
if($debug) print_r($input);
//looking for the header
if($input[0] != ".IPPcode20"){
  //missing header
  exit(21);
}
//deleting all comments
for($i=1; $i < count($input); $i++){
  if(($cut = strpos($input[$i], "#")) !== false){
    //line has a comment
    $input[$i] = substr($input[$i], 0, $cut);
  }
}
decho("-----\033[0;34m TRIMMED COMENTS \033[0;37m-----");
if($debug) print_r($input);
//main checker
decho("------\033[0;34m MAIN CHECKER \033[0;37m-------");
for($i=1; $i < count($input); $i++){
  decho($input[$i]);
  if(preg_match("/^(\w+)/", $input[$i], $m)){
    $opcode = $m[0];
    decho($opcode);
    if (isset($rules[$opcode])){
      //opcode found in rules
      $num = count($rules[$opcode]['params']);
      $regexp = "/^(\w+)";
      for($j = 0; $j < $num; $j++){
        $regexp = $regexp . "\s+(\w+)";
      }
      $regexp = $regexp . "$/";
      decho($regexp);

    } else {
      //opcode not found in rules
    }
  } else {
    //no opcode on line
    decho("/empty/");
  }






  /*
  if(preg_match("/^(\w+)\s+(\w+)\s+(\w+)\s+(\w+)$/", $input[$i], $m)){
    $opcode = strtoupper($m[0]);
    // searching for opcode in table
    if(isset($rules[$opcode])){
      //checking for params
      if(count($rules[$opcode]['params']) != 3){
        //bad number of params
        exit(23);
      }
      //next checking - specific opcode rules
    } else {
      //bad opcode
      exit(22);
    }
  } elseif(preg_match("/^(\w+)\s+(\w+)\s+(\w+)$/", $input[$i], $m)){
    $opcode = strtoupper($m[0]);
    // searching for opcode in table
    if(isset($rules[$opcode])){
      //checking for params
      if(count($rules[$opcode]['params']) != 2){
        //bad number of params
        exit(23);
      }
      //next checking - specific opcode rules
    } else {
      //bad opcode
      exit(22);
    }
  } elseif(preg_match("/^(\w+)\s+(\w+)$/", $input[$i], $m)){
    $opcode = strtoupper($m[0]);
    // searching for opcode in table
    if(isset($rules[$opcode])){
      //checking for params
      if(count($rules[$opcode]['params']) != 1){
        //bad number of params
        exit(23);
      }
      //next checking - specific opcode rules
    } else {
      //bad opcode
      exit(22);
    }
  } elseif(preg_match("/^(\w+)$/", $input[$i], $m)){
    $opcode = strtoupper($m[0]);
    // searching for opcode in table
    if(isset($rules[$opcode])){
      //checking for params
      if(count($rules[$opcode]['params']) != 0){
        //bad number of params
        exit(23);
      }
    } else {
      //bad opcode
      exit(22);
    }
  } elseif($input[$i] == ''){
    //empty line - correct
  } else {
    //too many words on line
    preg_match("/^(\w+)/", $input[$i], $m);
    $opcode = $m[0];
    if(isset($rules[$opcode])){
      //there is the opcode
      exit(23);
    } else {
      //bad opcode
      exit(22);
    }
  }
*/

}

exit(0);

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


/*
do {
  $c = fgetc(STDIN);
  echo "$c";
} while ($c != EOF)
*/
?>
