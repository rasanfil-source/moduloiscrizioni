<?php
// Solo database sintetico locale; tabella TEMPORARY, nessun dato operativo.
require __DIR__.'/audit-schema-index.php';
$fixture=file_get_contents(__DIR__.'/innodb-fixture.php');
eval(substr($fixture,strpos($fixture,'class DatabaseAdapter')));
$wpdb=new DatabaseAdapter();
$wpdb->query('CREATE DATABASE IF NOT EXISTS mi_ledger_test');
check_index($wpdb->db->select_db('mi_ledger_test'),'Database sintetico non disponibile');
check_index(false!==$wpdb->query('CREATE TEMPORARY TABLE mi_audit_index_test (event_type varchar(40), actor_label varchar(120), registration_id bigint, KEY type_actor (registration_id)) ENGINE=InnoDB'),'Tabella temporanea non creata');
check_index($repair->invoke(null,'mi_audit_index_test'),'Indice storico InnoDB non migrato');
check_index($repair->invoke(null,'mi_audit_index_test'),'Migrazione InnoDB non idempotente');
$rows=$wpdb->get_results("SHOW INDEX FROM mi_audit_index_test WHERE Key_name='type_actor'",ARRAY_A);
check_index(array_column($rows,'Column_name')===['event_type','actor_label'],'Colonne effettive indice errate');
echo "PASS: sostituzione effettiva indice storico su MariaDB/InnoDB e seconda esecuzione idempotente.\n";
