<?php

$settings = parse_ini_file(dirname(__FILE__) . '/../config.ini', TRUE);

$connect_string = sprintf("pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
	$settings['general']['db_host'],
	$settings['general']['db_port'],
	$settings['general']['db_name'],
	$settings['general']['db_user'],
	$settings['general']['db_pass']);
$db = new PDO($connect_string);


