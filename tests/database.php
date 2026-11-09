<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';

function verify_database(bool $pass, string $check): void {
    if ($pass) return;
    fwrite(STDERR,"FAIL: $check\n");
    exit(1);
}

$settings=one('SELECT @@SESSION.foreign_key_checks AS fk, @@SESSION.sql_mode AS mode, @@SESSION.time_zone AS tz');
verify_database((int)$settings['fk']===1 && str_contains($settings['mode'],'STRICT_TRANS_TABLES') && $settings['tz']==='+08:00',
    'Foreign keys, strict SQL mode, and Philippine time must be enabled on the application connection.');

$tables=['setup_state','admins','slots','requests','pool_details','request_features','request_facilities','meetings','site_visits',
    'projects','content','content_images','message_templates','messages','activity','rate_limits','reset_tokens'];
$engines=array_column(rows('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'),'ENGINE','TABLE_NAME');
foreach ($tables as $table) verify_database(($engines[$table]??null)==='InnoDB',"$table must use InnoDB.");

$foreignKeys=[
    'requests.slot_id'=>'slots.id',
    'pool_details.request_id'=>'requests.id',
    'request_features.request_id'=>'requests.id',
    'request_facilities.request_id'=>'requests.id',
    'meetings.request_id'=>'requests.id',
    'site_visits.request_id'=>'meetings.request_id',
    'projects.request_id'=>'meetings.request_id',
    'content_images.content_id'=>'content.id',
    'messages.request_id'=>'requests.id',
    'messages.event'=>'message_templates.event',
    'activity.admin_id'=>'admins.id',
    'reset_tokens.admin_id'=>'admins.id',
];
$actual=array_column(rows("SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME) AS child,
    CONCAT(REFERENCED_TABLE_NAME,'.',REFERENCED_COLUMN_NAME) AS parent FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"),'parent','child');
foreach ($foreignKeys as $child=>$reference) {
    verify_database(($actual[$child]??null)===$reference,"Missing foreign key $child → $reference.");
    [$table,$column]=explode('.',$child);
    [$parent,$parentColumn]=explode('.',$reference);
    verify_database(!one("SELECT 1 FROM `$table` child LEFT JOIN `$parent` parent ON parent.`$parentColumn`=child.`$column`
        WHERE child.`$column` IS NOT NULL AND parent.`$parentColumn` IS NULL LIMIT 1"),"$child has an orphan.");
}

$unique=one("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='requests' AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MIN(COLUMN_NAME)='active_slot'");
verify_database($unique!==null,'The active appointment claim must have a single-column unique index.');
$generated=one("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='requests' AND COLUMN_NAME='active_slot'");
verify_database(str_contains(strtoupper($generated['EXTRA']??''),'GENERATED'),'The active appointment claim must be generated.');
verify_database(!one("SELECT 1 FROM requests WHERE NOT (active_slot <=> CASE WHEN status<>'declined' THEN slot_id END) LIMIT 1"),
    'Active claims must reflect the request status.');
verify_database(!one('SELECT active_slot FROM requests WHERE active_slot IS NOT NULL GROUP BY active_slot HAVING COUNT(*)>1 LIMIT 1'),
    'No slot may have more than one active claim.');

foreach (require ROOT.'/database/integrity.php' as $table=>$checks) {
    foreach ($checks as $name=>$condition) {
        verify_database((bool)one("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
            AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='CHECK'",[$table,$name]),"Missing $name. Rerun setup to upgrade.");
        verify_database(!one("SELECT 1 FROM `$table` WHERE NOT ($condition) LIMIT 1"),"Existing rows violate $name.");
    }
}
verify_database(!one("SELECT 1 FROM meetings m JOIN requests r ON r.id=m.request_id WHERE r.status<>'approved' LIMIT 1"),
    'Meeting records require approved requests.');
verify_database(!one("SELECT 1 FROM projects p JOIN meetings m ON m.request_id=p.request_id WHERE m.outcome<>'proceed' LIMIT 1"),
    'Projects require a proceeding meeting.');
verify_database(!one("SELECT 1 FROM site_visits v JOIN meetings m ON m.request_id=v.request_id JOIN requests r ON r.id=v.request_id
    JOIN slots s ON s.id=r.slot_id WHERE m.outcome<>'proceed' OR v.starts_at<=s.starts_at LIMIT 1"),
    'Site visits require a proceeding meeting and a date after the appointment.');

echo "Passed: 17 InnoDB tables, ".count($foreignKeys)." foreign keys, four field checks, slot uniqueness, and workflow integrity.\n";
