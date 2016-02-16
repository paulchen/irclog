<?php
require_once(dirname(__FILE__) . '/../lib/common.php');

$default_page = 1;
$default_limit = 100;

if(isset($_GET['id'])) {
	$id = $_GET['id'];
	if(!preg_match('/^[0-9]+$/', $id)) {
		die();
	}
	if(!isset($_GET['limit'])) {
		$limit = $default_limit;
	}
	else {
		$limit = $_GET['limit'];
		if(!preg_match('/^[0-9]+$/', $limit)) {
			$limit = $default_limit;
		}
	}

	$query = 'SELECT COUNT(*) messages FROM message WHERE message_pk > ?';
	$data = db_query($query, array($id));

	$page = floor($data[0]['messages']/$limit)+1;

	header("Location: ?limit=$limit&page=$page#message${id}");
	die();
}

$page = isset($_GET['page']) ? $_GET['page'] : $default_page;
$limit = isset($_GET['limit']) ? $_GET['limit'] : $default_limit;
if(!preg_match('/^[0-9]+$/', $page) || $page < 1) {
	$page = $default_page;
}
if(!preg_match('/^[0-9]+$/', $limit)) {
	$limit = $default_limit;
}
$offset = ($page-1)*$limit;

$limit = intval($limit);
$offset = intval($offset);

$ajax = (isset($_GET['ajax']) && $_GET['ajax'] == 'on');
$refresh = (!isset($_GET['refresh']) || $_GET['refresh'] == 'on');

$text = isset($_GET['text']) ? trim($_GET['text']) : '';
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

$last_shown_id = -1;
if(isset($_GET['last_shown_id']) && preg_match('/^[0-9]+$/', $_GET['last_shown_id'])) {
	$last_shown_id = $_GET['last_shown_id'];
}

$message_data = get_messages($text, $user, $date, $offset, $limit, $last_shown_id);
$messages = $message_data['messages'];
$filtered_shouts = $message_data['filtered_shouts'];
$total_shouts = $message_data['total_shouts'];
$page_count = $message_data['page_count'];
$last_loaded_id = $message_data['last_loaded_id'];
$new_messages = $message_data['new_messages'];

$link_parts = "?limit=$limit";
if($text != '') {
	$link_parts .= '&amp;text=' . urlencode($text);
}
if($user != '') {
	$link_parts .= '&amp;user=' . urlencode($user);
}
if($date != '') {
	$link_parts .= '&amp;date=' . urlencode($date);
}
$previous_page = $page-1;
if($previous_page <= 0) {
	$previous_page = 1;
}
if($previous_page > $page_count) {
	$previous_page = $page_count;
}
$next_page = $page+1;
if($next_page <= 0) {
	$next_page = 1;
}
if($next_page > $page_count) {
	$next_page = $page_count;
}
$previous_link = "$link_parts&amp;page=$previous_page";
$next_link = "$link_parts&amp;page=$next_page";
$first_link = "$link_parts&amp;page=1";
$last_link = "$link_parts&amp;page=$page_count";
$generic_link = str_replace('&amp;', '&', "$link_parts&amp;page=");

if(!$ajax) {
	$memcached_key = "${memcached_prefix}_userlist";
	$memcached_data = $memcached->get($memcached_key);
	if($memcached_data == null) {
		$query = 'SELECT u.username FROM message m JOIN "user" u ON (m.user_fk = u.user_pk) GROUP BY u.username ORDER BY COUNT(*) DESC';
		$users = json_encode(array_map(function($a) { return $a['username']; }, db_query($query)));
		$memcached->set($memcached_key, $users, 300);
	}
	else {
		$users = $memcached_data;
	}
}

// header('Content-Type: application/xhtml+xml; charset=utf-8');
header('Content-Type: text/html; charset=utf-8');

if($limit > 1000) {
	require_once(dirname(__FILE__) . '/../templates/pages/archive.php');
	die();
}

ob_start();
require_once(dirname(__FILE__) . '/../templates/pages/archive.php');
$data = ob_get_contents();
ob_clean();

if(!$ajax) {
	xml_validate($data);
}
ob_start("ob_gzhandler");
echo $data;

log_data();

