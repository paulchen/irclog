#!/usr/bin/php
<?php
require_once(dirname(__FILE__) . '/../lib/common.php');

if(!isset($argc) || !isset($argv) || $argc != 2) {
	die();
}

$page = $argv[1];
if(!preg_match('/^[0-9]+$/', $page)) {
	die();
}

$limit = 10000;
$offset = ($page-1)*$limit;

get_messages('', '', '', $offset, $limit);

