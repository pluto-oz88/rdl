<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function fail(string $message,int $status=400):never{http_response_code($status);echo json_encode(['error'=>$message]);exit;}
$session=strtolower(trim((string)($_GET['session']??'')));
if(!preg_match('/^[a-z0-9_-]{3,32}$/',$session))fail('Invalid session.');
$path=dirname(__DIR__).'/data/gps-'.$session.'.ndjson';
if(!is_file($path))fail('No recorded journey exists for this session.',404);
$lines=file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
if($lines===false)fail('Could not read recorded journey.',500);
$points=[];
foreach($lines as $line){$point=json_decode($line,true);if(is_array($point))$points[]=$point;}
if(!$points)fail('Recorded journey is empty.',404);
echo json_encode(['session'=>$session,'count'=>count($points),'points'=>$points],JSON_UNESCAPED_SLASHES);