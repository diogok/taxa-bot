<?php

if (php_sapi_name() !== 'cli') { 
  exit (1); 
}

$data = __DIR__.'/data';

// create data dir if not exists
if(!file_exists($data)) mkdir($data);

$keep=true;
while($keep) {
  include 'strings.php';
  $sources=[];

  if(file_exists("/etc/biodiv/taxa-bot.list")) {
    $fs = fopen("/etc/biodiv/taxa-bot.list",'r');
  } else if(file_exists(__DIR__."/../taxa-bot.list")) {
    $fs = fopen(__DIR__."/../taxa-bot.list",'r');
  }
  while($line = fgets($fs)) {
    $src = trim($line);
    if(strlen($src) >= 10) {
      $sources[] = $src;
    }
  }
  fclose($fs);

  foreach($sources as $src_url) {

    echo "Will get ".$src_url."\n";

    // creates db if not exists
    if(!file_exists($data."/taxa.db")) $create=true;
    else $create=false;

    // download
    echo "Downloading...\n";
    if(file_Exists($data.'/dwca.zip')) unlink($data.'/dwca.zip');
    $command = 'curl '.$src_url.' -o '.$data.'/dwca.zip';
    system($command);
    echo "Downloaded.\n";

    // Unzing
    echo "Unzipping...\n";
    $dst=$data."/dwca";
    if(!file_exists($dst)) mkdir($dst);
    $zip = new ZipArchive;
    if ($zip->open($data."/dwca.zip") === TRUE) {
        $zip->extractTo($dst);
        $zip->close();
    }
    echo "Unzipped.\n";

    $source = "";

    // Try to get title and version
    $eml = file_get_contents($dst."/eml.xml");
    preg_match('@<title[^>]*>([^<]+)</title>@',$eml,$reg);
    if(isset($reg[1])) {
      $source .= " ".$reg[1];
    }
    preg_match('@packageId="[^/]+/v([^"]+)"@',$eml,$reg);
    if(isset($reg[1])) {
      $source .= " v".$reg[1];
    }
    $source = trim($source);

    // start reading the taxa
    $f=fopen($dst."/taxon.txt",'r');

    // read the headers for easier handling
    $headersRow = fgetcsv($f,0,"\t");
    $headers=array();
    for($i=0;$i<count($headersRow);$i++){
        $headers[$headersRow[$i]] = $i;
    }

    // database connection
    $db = new PDO('sqlite:'.__DIR__.'/data/taxa.db');
    $db->exec('PRAGMA synchronous = OFF');
    $db->exec('PRAGMA journal_mode = MEMORY');

    // create table if not exists
    $db->exec(file_get_contents(__DIR__."/schema.sql"));
    $err = $db->errorInfo();
    if($err[0] != "00000") var_dump($db->errorInfo());

    // clean table
    $db->exec("DELETE FROM taxa ;");
    $err = $db->errorInfo();
    if($err[0] != "00000") var_dump($db->errorInfo());

    // insert query
    $insert = $db->prepare("INSERT INTO taxa (`taxonID`,`family`,`genus`,`scientificName`,`scientificNameWithoutAuthorship`,`scientificNameAuthorship`,`taxonomicStatus`,`acceptedNameUsage`,`taxonRank`,`higherClassification`,`source`) VALUES (?,?,?,?,?,?,?,?,?,?,?) ;");
    $err = $db->errorInfo();
    if($err[0] != "00000") var_dump($db->errorInfo());

    $i=0;
    echo "Inserting...\n";
    while($row = fgetcsv($f,0,"\t")) {
        foreach($row as $k=>$v) {
          $row[$k]=trim($v);
        }

        # translate taxonomicStatus
        $row[$headers['taxonomicStatus']] = get_string($row[$headers['taxonomicStatus']]) ;

        # translate taxonRank
        $row[$headers['taxonRank']] = get_string($row[$headers['taxonRank']]) ;

        # only interested in species, subspecies and variety
        $rank = $row[$headers['taxonRank']];
        if($rank != 'species' && $rank != 'subspecies' && $rank != 'variety') {
            continue;
        }

        # an accepted taxa should have its own name as accepted name
        if($row[$headers['taxonomicStatus']] == 'accepted') {
            $row[$headers['acceptedNameUsage']] = $row[$headers['scientificName']];
        }

        #scientificName without author
        if(strlen($row[$headers['scientificNameAuthorship']]) >= 1) {
          $nameWithoutAuthor = trim(str_replace(" ".$row[$headers['scientificNameAuthorship']],'',$row[$headers['scientificName']]));
        } else {
          $nameWithoutAuthor = $row[$headers['scientificName']];
        }

        $maybeBreak = explode(" ",$nameWithoutAuthor);
        if(count($maybeBreak) > 2) {
          $nameWithoutAuthor = $maybeBreak[0]." ".$maybeBreak[1];
          $row[$headers['scientificNameAuthorship']] = str_replace($nameWithoutAuthor." ",'',$row[$headers['scientificName']]);
        }

        $t = [];
        foreach($headers as $k=>$v) {
            $t[$k]=$row[$v];
        }

        # insert
        $taxon = array(
            $row[ $headers['taxonID'] ],
            strtoupper($row[ $headers['family'] ]),
            $row[ $headers['genus'] ],
            $row[ $headers['scientificName'] ],
            $nameWithoutAuthor,
            $row[ $headers['scientificNameAuthorship'] ],
            $row[ $headers['taxonomicStatus'] ],
            $row[ $headers['acceptedNameUsage'] ],
            $row[ $headers['taxonRank'] ],
            $row[ $headers['higherClassification'] ],
            $source
        );
        $insert->execute($taxon);
        #echo "Inserted $i = {$taxon[0]}.\n";
        $err = $insert->errorInfo();
        if($err[0] != "00000") var_dump($insert->errorInfo());
        $i++;
    }
    fclose($f);

    echo "Inserted.\n";

    // start reading the relations
    $f=fopen($dst."/resourcerelationship.txt",'r');

    // read the headers for easier handling
    $headersRow = fgetcsv($f,0,"\t");
    $headers=array();
    for($i=0;$i<count($headersRow);$i++){
        $headers[$headersRow[$i]] = $i;
    }

    $update = $db->prepare("UPDATE taxa SET acceptedNameUsage=(SELECT acceptedNameUsage FROM taxa where taxonID=?) where taxonID=?");
    $err = $db->errorInfo();
    if($err[0] != "00000") var_dump($db->errorInfo());

    $i=0;
    echo "Updating...\n";
    while($row = fgetcsv($f,0,"\t")) {
        $relation = (get_string($row[$headers['relationshipOfResource']]));

        $content=false;
        if($relation == 'synonym_of') {
          $content = [$row[1],$row[0]];
        } else if($relation == 'has_synonym') {
          $content = [$row[0],$row[1]];
        }

        if($content) {
          $update->execute($content);
          $err = $update->errorInfo();
          if($err[0] != "00000") var_dump($update->errorInfo());
        }
        $i++;
    }

    fclose($f);

    echo "Done.\n";
  }

  $es = getenv("ELASTICSEARCH");
  if($es != null) {
    $idx = getenv("INDEX");
    echo "Indexing in ".$es."/".$idx."...\n";
    mapping();
    $q = $db->query("select * from taxa;");
    $acc= [];
    while($taxon = $q->fetchObject()) {
      $acc[]=$taxon;
      if(count($acc) == 512) {
        index($acc);
        $acc=[];
      }
    }
    index($acc);
    echo "Indexed.\n";
  }

  $loop = getenv("LOOP");
  $keep = $loop === "true";
}

function mapping(){
  $es = getenv("ELASTICSEARCH");
  if($es==null) $es='http://localhost:9200';
  $idx = getenv("INDEX");
  if($idx==null) $idx="dwc";

  $json = file_get_contents(__DIR__."/mapping.json");
  $url = $es."/".$idx."/_mapping/taxon";

  $c = curl_init();
  curl_setopt($c, CURLOPT_URL, $es."/".$idx);
  curl_setopt($c, CURLOPT_POST, 1);
  curl_setopt($c, CURLOPT_POSTFIELDS, '{}');
  curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($c, CURLOPT_RETURNTRANSFER,1);
  curl_exec($c);
  curl_close($c);

  $c = curl_init();
  curl_setopt($c, CURLOPT_URL, $url);
  curl_setopt($c, CURLOPT_POST, 1);
  curl_setopt($c, CURLOPT_POSTFIELDS, $json);
  curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($c, CURLOPT_RETURNTRANSFER,1);
  $response = curl_exec($c);
  curl_close($c);
}

function index($taxa)  {
  if(count($taxa) ==0) return;
  $es = getenv("ELASTICSEARCH");
  if($es==null) $es='http://localhost:9200';
  $idx = getenv("INDEX");
  if($idx==null) $idx="dwc";
  $arr =[];
  foreach($taxa as $taxon) {
    $arr[] = json_encode(["index"=>["_index"=>$idx,"_type"=>"taxon","_id"=>$taxon->taxonID]]);
    $arr[] = json_encode($taxon);
  }
  $data=implode("\n",$arr);

  $c = curl_init();
  curl_setopt($c, CURLOPT_URL, $es."/_bulk");
  curl_setopt($c, CURLOPT_POST, 1);
  curl_setopt($c, CURLOPT_POSTFIELDS, $data);
  curl_setopt($c, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($c, CURLOPT_RETURNTRANSFER,1);
  $response = curl_exec($c);
  curl_close($c);
}
