#!/usr/bin/php
<?php
require_once(dirname(__FILE__) . '/../lib/common.php');

$result = db_query("select count(*) channels from channel where last_update < NOW() - INTERVAL '15 minutes'");
$count = $result[0]['channels'];
if($count > 0) {
	echo "$count channel(s) not updated in the last 15 minutes\n";
	exit(2);
}

$result = db_query("select count(*) channels from channel where last_update < NOW() - INTERVAL '10 minutes'");
$count = $result[0]['channels'];
if($count > 0) {
	echo "$count channel(s) not updated in the last 10 minutes\n";
	exit(1);
}

echo "All channels updated within the last 10 minutes\n";

