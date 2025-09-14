<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$in   = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int)($in['id'] ?? 0);
$patch= (array)($in['patch'] ?? []);
if ($id <= 0 || !$patch) { http_response_code(400); echo json_encode(['error'=>'Missing id/patch']); exit; }

$set=[]; $params=[':id'=>$id];
foreach ($patch as $k=>$v) {
     $p=':p_'.md5($k); 
     $set[]='['.str_replace(']',']]',$k)."]=$p"; 
     $params[$p]=$v; 
     }

try {
  $pdo->prepare("UPDATE [dbo].[TableProcReq] SET ".implode(',',$set)." WHERE [ID]=:id")->execute($params);
  $row = $pdo->query("SELECT * FROM [dbo].[TableProcReq] WHERE [ID]=$id")->fetch();
  echo json_encode(['ok'=>true,'row'=>$row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
