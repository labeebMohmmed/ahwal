<?php
$sql = 'ALTER TABLE ' . $conf['table'] . ' ADD ' . implode(', ', $defs) . ';';
db()->exec($sql);
respond(true, 'column added');



function drop_col(string $which): void {
require_admin();
$conf = table_conf($which);
$body = json_input();
$name = trim((string)($body['name'] ?? ''));


if ($name === '' || !allow_column($name)) respond(false, 'Disallowed column name', 400);
if (!has_column($conf['table'], $name)) respond(false, 'Column not found', 404);


// Safety: never drop ID, UpdatedAt, UpdatedBy
$protected = ['ID','UpdatedAt','UpdatedBy'];
if (in_array($name, $protected, true)) respond(false, 'Protected column', 400);


$sql = 'ALTER TABLE ' . $conf['table'] . ' DROP COLUMN ' . quote_ident($name);
db()->exec($sql);
respond(true, 'column dropped');
}


function link_req(): void {
require_admin();
$conf = table_conf('models');
$body = json_input();
$id = (int)($body['id'] ?? 0);
$reqId = (int)($body['reqId'] ?? 0);
if ($id <= 0) respond(false, 'Missing id', 400);


// Allow null unlink
$params = [$reqId ?: null, $id];
$sql = 'UPDATE ' . $conf['table'] . ' SET [ReqID] = ? WHERE [ID] = ?';
$st = db()->prepare($sql);
$st->execute($params);
respond(true, 'linked');
}


function linked_models_for_req(): void {
// Bonus helper: list models pointing to a given ProcReq ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) respond(false, 'Missing id', 400);
$sql = 'SELECT * FROM dbo.TableAddModel WHERE [ReqID] = ? ORDER BY [ID]';
$st = db()->prepare($sql);
$st->execute([$id]);
respond(true, $st->fetchAll());
}


// --- Router ---
$path = $_SERVER['REQUEST_URI'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';


// Normalize (no query string)
if (($qpos = strpos($path, '?')) !== false) $path = substr($path, 0, $qpos);


switch (true) {
// LIST
case $method==='GET' && preg_match('#^/admin/models/list$#', $path): list_rows('models'); break;
case $method==='GET' && preg_match('#^/admin/reqs/list$#', $path): list_rows('reqs'); break;
// GET single
case $method==='GET' && preg_match('#^/admin/models/get$#', $path): get_row('models'); break;
case $method==='GET' && preg_match('#^/admin/reqs/get$#', $path): get_row('reqs'); break;
// UPDATE
case $method==='POST' && preg_match('#^/admin/models/update$#', $path): update_row('models'); break;
case $method==='POST' && preg_match('#^/admin/reqs/update$#', $path): update_row('reqs'); break;
// DELETE
case $method==='POST' && preg_match('#^/admin/models/delete$#', $path): delete_row('models'); break;
case $method==='POST' && preg_match('#^/admin/reqs/delete$#', $path): delete_row('reqs'); break;
// ADD ROW
case $method==='POST' && preg_match('#^/admin/models/addrow$#', $path): add_row('models'); break;
case $method==='POST' && preg_match('#^/admin/reqs/addrow$#', $path): add_row('reqs'); break;
// DDL add/drop col
case $method==='POST' && preg_match('#^/admin/models/addcol$#', $path): add_col('models'); break;
case $method==='POST' && preg_match('#^/admin/models/dropcol$#', $path): drop_col('models'); break;
case $method==='POST' && preg_match('#^/admin/reqs/addcol$#', $path): add_col('reqs'); break;
case $method==='POST' && preg_match('#^/admin/reqs/dropcol$#', $path): drop_col('reqs'); break;
// FK link
case $method==='POST' && preg_match('#^/admin/models/link-req$#', $path): link_req(); break;
// Bonus
case $method==='GET' && preg_match('#^/admin/reqs/linked-models$#', $path): linked_models_for_req(); break;


default:
respond(false, 'Not found: ' . $method . ' ' . $path, 404);
}