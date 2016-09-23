<?php

function get_string($s) {
  $strings = array(
      null=>'',
      ''=>'',
      ' '=>' ',
      'NOME_ACEITO'=>'accepted',
      'SINONIMO'=>"synonym",
      'CLASSE'=>"class",
      'DIVISAO'=>"division",
      'ESPECIE'=>"species",
      'FAMILIA'=>"family",
      'FORMA'=>"form",
      'GENERO'=>"genus",
      'ORDEM'=>"order",
      'SUB_ESPECIE'=>"subspecies",
      'SUB_FAMILIA'=>"subfamily",
      'TRIBO'=>"tribe",
      'VARIEDADE'=>"variety",
      'É sinônimo HETEROTIPICO'=>'synonym_of',
      'É sinônimo HOMOTIPICO'=>'synonym_of',
      'É sinônimo BASIONIMO'=>'synonym_of',
      'Tem como sinônimo HETEROTIPICO'=>'has_synonym',
      'Tem como sinônimo HOMOTIPICO'=>'has_synonym',
      'Tem como sinônimo BASIONIMO'=>'has_synonym'
  );
  foreach($strings as $k=>$v) {
    if(strtolower($k) == strtolower( $s )) {
      return $v;
    }
    if(strtolower($k) == strtolower( utf8_encode( $s ) )) {
      return $v;
    }
    if(strtolower($k) == strtolower( utf8_decode( $s ) )) {
      return $v;
    }
  }
  return $s;
}
