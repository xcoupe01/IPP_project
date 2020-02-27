<?php


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



/** jen test */

$result=[
  'test1/test'=> 'fail# lorem ipsum  lorem ipsum  lorem ipsum v lorem ipsum '.
  ' lorem ipsum  lorem ipsum  lorem ipsum  lorem ipsum  lorem ipsum  lorem ipsum ',
  'test2/testb'=> 'OK',
  'test3/testspec'=>'OK',
  'test4'=>'disfail#  lorem ipsum  lorem ipsum  lorem ipsum v lorem ipsum '.
  ' lorem ipsum  lorem ipsum  lorem ipsum  lorem ipsum  lorem ipsum  lorem ipsum ',
  'test5/0000'=>'OK'
];

/* Generovani HTML 5 vystupu */
$o=ta('h1','Test results'); $n=0; $fails=0; $succes=0;
$o.=ta('br');
$o.=ta('h3', 'test settings:');
$o.=ta('ul');
$o.=ta('li', 'directory: '); 
$o.=ta('li', 'recursive: ');    
$o.=ta('li', 'parse script: ');     
$o.=ta('li', 'interpret script: ');    
$o.=ta('li', 'parse only: ');    
$o.=ta('li', 'interpret only: ');    
$o.=ta('li','JexamXML: ');
$o.=ta('br');
foreach ($result as $k=>$v){
  if (preg_match('/^(\w+)#(.+)$/',$v,$m)){
    $fails ++;
    $m[1] = str_replace('fail', '<b>&#128502;</b>', $m[1]);
    $res = tg('td', 'width=5% align="middle" bgcolor=ff7373', $m[1]);
    $dets=tg('td','',
      tg('span','onclick="document.getElementById(\'ID'.$n.'\').style.display=(document.getElementById(\'ID'.$n.'\').style.display==\'block\')?\'none\':\'block\';" ','<b>Details avalible</b>').
      tg('div', 'id="ID' . $n . '" style="display:none;"', $m[2])); 
  }else{
    $sucess ++;
    $v = str_replace('OK', '<b>&#128504;</b>', $v);
    $res=tg('td', 'width=5% align="middle" bgcolor=9bff8a' ,$v);
    $dets=ta('td', ' ');
  }
  $o.=ta('tr',tg('td','width=5% align="middle"',++$n . $res . ta('td', $k)) . $dets);
}
$o=ta('table',/*ta('caption', 'string').*/ta('th','Number').ta('th', 'Result').ta('th', 'Test File').ta('th','Details (click to view)').$o);

$o=ta('html',
    ta('head',
     tg('meta', 'http-equiv="Content-Type" content="text/html;" charset="utf-8"').
     ta('title','Test results').
ta('style',
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
')).
    ta('body',$o)); 
echo '<!DOCTYPE html>'."\n".$o;

?>