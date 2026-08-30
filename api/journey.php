<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function fail(string $message,int $status=400):never{http_response_code($status);echo json_encode(['error'=>$message]);exit;}
$storageDir=dirname(__DIR__).'/data';
$session=strtolower(trim((string)($_GET['session']??'')));
if($session===''){
    $journeys=[];
    foreach(glob($storageDir.'/gps-*.ndjson')?:[] as $path){
        $base=basename($path);
        if(!preg_match('/^gps-(.+)\.ndjson$/',$base,$m))continue;
        $name=$m[1];$lines=file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if($lines===false||!$lines)continue;
        $first=json_decode($lines[0],true);$last=json_decode($lines[count($lines)-1],true);
        $firstTs=(int)($first['timestamp']??0);$lastTs=(int)($last['timestamp']??0);
        $journeys[]=['session'=>$name,'count'=>count($lines),'startedAt'=>$first['receivedAt']??null,'endedAt'=>$last['receivedAt']??null,'firstTimestamp'=>$firstTs,'lastTimestamp'=>$lastTs,'durationSeconds'=>$firstTs&&$lastTs?max(0,(int)round(($lastTs-$firstTs)/1000)):0];
    }
    usort($journeys,fn($a,$b)=>($b['lastTimestamp']??0)<=>($a['lastTimestamp']??0));
    echo json_encode(['journeys'=>$journeys],JSON_UNESCAPED_SLASHES);exit;
}
if(!preg_match('/^[a-z0-9_-]{3,32}$/',$session))fail('Invalid journey code.');
$path=$storageDir.'/gps-'.$session.'.ndjson';
if(!is_file($path))fail('No recorded journey exists for this code.',404);
$lines=file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if($lines===false)fail('Could not read recorded journey.',500);
$points=[];foreach($lines as $line){$point=json_decode($line,true);if(is_array($point))$points[]=$point;}
if(!$points)fail('Recorded journey is empty.',404);
echo json_encode(['session'=>$session,'count'=>count($points),'points'=>$points],JSON_UNESCAPED_SLASHES);