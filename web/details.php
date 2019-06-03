<?php
require_once(dirname(__FILE__) . '/../lib/common.php');

function build_link_from_request() {
	$keys = func_get_args();
	$link_parts = '';
	foreach($keys as $key) {
		if(isset($_REQUEST[$key])) {
			$link_parts .= "&amp;$key=" . urlencode($_REQUEST[$key]);
		}
	}
	return $link_parts;
}

function overview_redirect() {
	header('Location: ' . basename($_SERVER['SCRIPT_FILENAME']));
	die();
}

function add_user_link($row) {
	global $safe_channel;

	// TODO simplify this
	$link_parts = build_link_from_request('day', 'month', 'year', 'hour');

	$row['name'] = '<a href="details.php?user=' . urlencode($row['name']) . $link_parts . '&amp;channel=' . $safe_channel . '">' . $row['name'] . '</a>';

	return $row;
}

function messages_per_hour($row) {
	global $safe_channel;

	$link_parts = build_link_from_request('day', 'month', 'year', 'user');

	$row['hour'] = '<a href="details.php?hour=' . $row['hour'] . $link_parts . '&amp;channel=' . $safe_channel . '">' . $row['hour'] . '</a>';

	return $row;
}

function messages_per_month($row) {
	global $safe_channel;

	$link_parts = build_link_from_request('user', 'hour');

	$parts = explode('-', $row['month']);
	$year = $parts[0];
	$month = $parts[1];
	$row['month'] = "<a href=\"details.php?month=$month&amp;year=$year$link_parts&amp;channel=$safe_channel\">" . $row['month'] . '</a>';

	return $row;
}

function messages_per_year($row) {
	global $safe_channel;

	$link_parts = build_link_from_request('user', 'hour');

	$row['year'] = "<a href=\"details.php?year=" . $row['year'] . "$link_parts&amp;channel=$safe_channel\">" . $row['year'] . '</a>';

	return $row;
}

function top_spammers_total($data) {
	global $total_days;

	$total_shouts = 0;
	foreach($data as $row) {
		$total_shouts += $row['shouts'];
	}

	return array('',
		'Total',
		$total_shouts,
	);
}

$main_page = false;
if(!isset($_REQUEST['user']) && !isset($_REQUEST['year']) && !isset($_REQUEST['hour'])) {
	$main_page = true;
}
if(isset($_REQUEST['day']) && !isset($_REQUEST['month'])) {
	overview_redirect();
}
if(isset($_REQUEST['month']) && !isset($_REQUEST['year'])) {
	overview_redirect();
}

foreach(array('day', 'month', 'year', 'hour', 'smiley') as $item) {
	if(isset($_REQUEST[$item]) && !preg_match('/^[0-9]+$/', $_REQUEST[$item])) {
		overview_redirect();
	}
}
if(isset($_REQUEST['user'])) {
	$user = $_REQUEST['user'];

	$user_data = db_query('SELECT user_pk FROM "user" WHERE username = ?', array($user));
	if(count($user_data) != 1) {
		overview_redirect();
	}
	$user_id = $user_data[0]['user_pk'];
}
if(isset($_REQUEST['hour'])) {
	$hour = $_REQUEST['hour'];
}

$channel = $settings['web']['default_channel'];
if(isset($_REQUEST['channel'])) {
	$channel = $_REQUEST['channel'];
}
$safe_channel = urlencode($channel);

$channel_id = get_channel_id($channel);
if(!$channel_id) {
	die();
}

$filter_parts = array('deleted = false', 'channel_fk = ?');
$params = array($channel_id);
$what_parts = array();

if(isset($_REQUEST['hour'])) {
	$filter_parts[] = "extract(hour from timestamp) = ?";
	$params[] = $hour;
	$what_parts[] = "hour $hour";
}
if(isset($_REQUEST['year'])) {
	$filter_parts[] = 'extract(year from timestamp) = ?';
	$params[] = $_REQUEST['year'];
	$what_parts[] = $_REQUEST['year'];
}
if(isset($_REQUEST['month'])) {
	$filter_parts[] = 'extract(month from timestamp) = ?';
	$params[] = $_REQUEST['month'];
	array_pop($what_parts);
	$what_parts[] = $_REQUEST['year'] . '-' . $_REQUEST['month'];
}
if(isset($_REQUEST['day'])) {
	$filter_parts[] = 'extract(day from timestamp) = ?';
	$params[] = $_REQUEST['day'];
	array_pop($what_parts);
	$what_parts[] = $_REQUEST['year'] . '-' . $_REQUEST['month'] . '-' . $_REQUEST['day'];
}
if(isset($_REQUEST['user'])) {
	$filter_parts[] = 'm.user_fk = ?';
	$params[] = $user_id;
	$what_parts[] = $user;
}

$filter = implode(' AND ', $filter_parts);
$what = implode(', ', $what_parts);

$queries = array();

$queries[] = array(
		'title' => 'Top spammers',
		'query' => "select u.username AS name, count(*) shouts
				from \"user\" u join message m on (u.user_pk = m.user_fk)
				where $filter
				group by u.user_pk, u.username
				order by count(*) desc",
		'params' => $params,
		'processing_function' => array('add_user_link'),
		'processing_function_all' => array('duplicates0', 'insert_position', 'ex_aequo2'),
		'columns' => array('Position', 'Username', 'Messages'),
		'column_styles' => array('right', 'left', 'right'),
		'total' => 'top_spammers_total',
		'derived_queries' => array(),
	);

$queries[] = array(
		'title' => 'Messages per hour',
		'query' => "select extract(hour from timestamp) as hour, count(*) as shouts from message m where $filter group by hour order by hour asc",
		'params' => $params,
		'processing_function' => 'messages_per_hour',
		'processing_function_all' => 'duplicates0',
		'columns' => array('Hour', 'Messages'),
		'column_styles' => array('left', 'right'),
		'derived_queries' => array(
			array(
				'title' => 'Busiest hours',
				'transformation_function' => 'busiest_hours',
				'processing_function' => 'messages_per_hour',
				'processing_function_all' => array('duplicates0', 'insert_position', 'ex_aequo2'),
				'columns' => array('Position', 'Hour', 'Messages'),
				'column_styles' => array('right', 'right', 'right'),
			),
		),
	);
$queries[] = array(
		'title' => 'Busiest days',
		'query' => "select to_char(timestamp, 'YYYY-MM-DD') as day, count(*) as shouts from message m where $filter group by day order by count(*) desc limit 10",
		'params' => $params,
		'processing_function' => function($row) {
				global $safe_channel;

				$parts = explode('-', $row['day']);
				$year = $parts[0];
				$month = $parts[1];
				$day = $parts[2];
				$row['day'] = "<a href=\"details.php?day=$day&amp;month=$month&amp;year=$year&amp;channel=$safe_channel\">" . $row['day'] . '</a>';

				return $row;
			},
		'processing_function_all' => array('duplicates0', 'insert_position'),
		'columns' => array('Position', 'Day', 'Messages'),
		'column_styles' => array('right', 'left', 'right'),
	);
if(!isset($_REQUEST['day'])) {
	$queries[] = array(
			'title' => 'Messages per month',
			'query' => "select to_char(timestamp, 'YYYY-MM') as month, count(*) as shouts from message m where $filter group by month order by month asc",
			'params' => $params,
			'processing_function' => 'messages_per_month',
			'processing_function_all' => 'duplicates0',
			'columns' => array('Month', 'Messages'),
			'column_styles' => array('left', 'right'),
			'derived_queries' => array(
				array(
					'title' => 'Messages per month, ordered by number of messages',
					'transformation_function' => 'busiest_time',
					'processing_function' => 'messages_per_month',
					'processing_function_all' => array('duplicates1', 'ex_aequo2'),
					'columns' => array('Position', 'Month', 'Messages'),
					'column_styles' => array('right', 'left', 'right'),
				),
			),
		);
}
if(!isset($_REQUEST['month'])) {
	$queries[] = array(
			'title' => 'Messages per year',
			'query' => "select extract(year from timestamp) as year, count(*) as shouts from message m where $filter group by year order by year asc",
			'params' => $params,
			'processing_function' => 'messages_per_year',
			'processing_function_all' => 'duplicates0',
			'columns' => array('Year', 'Messages'),
			'column_styles' => array('left', 'right'),
			'derived_queries' => array(
				array(
					'title' => 'Messages per year, ordered by number of messages',
					'transformation_function' => 'busiest_time',
					'processing_function' => 'messages_per_year',
					'processing_function_all' => array('duplicates0', 'ex_aequo2'),
					'columns' => array('Position', 'Year', 'Messages'),
					'column_styles' => array('right', 'left', 'right'),
				),
			),
		);
}
$query_total = array(
		'query' => "SELECT COUNT(*) shouts FROM message m WHERE $filter",
		'params' => $params,
	);

if($main_page) {
	$page_title = 'Spam overview for #' . htmlentities($channel, ENT_QUOTES, 'UTF-8');
	$backlink = array(
		'url' => "index.php?channel=$safe_channel",
		'text' => '#' . htmlentities($channel, ENT_QUOTES, 'UTF-8') . ' archive',
	);
}
else {
	$page_title = "Spam overview for #" . htmlentities($channel, ENT_QUOTES, 'UTF-8') . ": $what";
	$backlink = array(
			'url' => "details.php?channel=$safe_channel",
			'text' => 'Spam overview',
		);
}

require_once(dirname(__FILE__) . '/../lib/stats.php');

log_data();

