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

function add_user_link(&$row) {
	// TODO simplify this
	$link_parts = build_link_from_request('day', 'month', 'year', 'hour');

	$row[0]['name'] = '<a href="details.php?user=' . urlencode($row[0]['name']) . $link_parts . '">' . $row[0]['name'] . '</a>';
}

function messages_per_hour(&$row) {
	$link_parts = build_link_from_request('day', 'month', 'year', 'user');

	$row[0]['hour'] = '<a href="details.php?hour=' . $row[0]['hour'] . $link_parts . '">' . $row[0]['hour'] . '</a>';
}

function messages_per_month(&$row) {
	$link_parts = build_link_from_request('user', 'hour');

	$parts = explode('-', $row[0]['month']);
	$year = $parts[0];
	$month = $parts[1];
	$row[0]['month'] = "<a href=\"details.php?month=$month&amp;year=$year$link_parts\">" . $row[0]['month'] . '</a>';
	spammer_smiley($row);
}

function messages_per_year(&$row) {
	$link_parts = build_link_from_request('user', 'hour');

	$row[0]['year'] = "<a href=\"details.php?year=" . $row[0]['year'] . "$link_parts\">" . $row[0]['year'] . '</a>';
	spammer_smiley($row);
}

/*
function total_words($data) {
	$data = $data[0];

	usort($data, function($a, $b) {
		if($a['total_words'] == $b['total_words']) {
			if($a['shouts'] == $b['shouts']) {
				if($a['name'] < $b['name']) {
					return -1;
				}
				return 1;
			}
			if($a['shouts'] < $b['shouts']) {
				return 1;
			}
			return -1;
		}
		if($a['total_words'] < $b['total_words']) {
			return 1;
		}
		return -1;
	});

	return $data;
}
 */
function top_spammers_total($data) {
	global $total_days;

	$total_shouts = 0;
	foreach($data[0] as $row) {
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

$filter_parts = array('deleted = false');
$params = array();
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
		'query' => "select to_char(timestamp, 'YYYY-MM-DD') as day, count(*) as shouts from message where $filter group by day order by count(*) desc limit 10",
		'params' => $params,
		'processing_function' => function(&$row) {
				$parts = explode('-', $row[0]['day']);
				$year = $parts[0];
				$month = $parts[1];
				$day = $parts[2];
				$row[0]['day'] = "<a href=\"details.php?day=$day&amp;month=$month&amp;year=$year\">" . $row[0]['day'] . '</a>';
			},
		'processing_function_all' => array('duplicates0', 'insert_position'),
		'columns' => array('Position', 'Day', 'Messages'),
		'column_styles' => array('right', 'left', 'right'),
	);
/*
if(!isset($_REQUEST['day'])) {
	$queries[] = array(
			'title' => 'Messages per month',
			'query' => "with smileycount as (
				select s.month, s.year, sm.smiley, sum(sm.count) count from shouts s join shout_smilies sm on (s.primary_id=sm.shout) where $filter group by s.month, s.year, sm.smiley
			), wordcount as (
				select s.month, s.year, sw.word, sum(sw.count) count from shouts s join shout_words sw on (s.primary_id=sw.shout) where $filter group by s.month, s.year, sw.word
			), hours as (
				select user_id \"user\", month, year, count(*) count from shouts s where $filter group by user_id, month, year
			)
					select concat(cast(j.year as text), '-', lpad(cast(j.month as text), 2, '0')) \"month\", j.count shouts, concat(c.user, '$$', u.name, '$$', c.count) top_spammer,
						concat(f.smiley, '$$', sm.filename, '$$', f.count) popular_smiley, concat(i.word, '$$', w.word, '$$', i.count) popular_word
					from (select month, year, count(s.id) count from shouts s where $filter group by month, year) j
						left join
						(
							(select month, year, max(count) max from hours a group by month, year) b
							left join hours c
							on (b.month=c.month and b.year=c.year and b.max=c.count)
						) on (j.month=b.month and j.year=b.year)
						left join users u on (c.user=u.id)
						left join
						(
							(select e.month, e.year, max(e.count) max
								from smileycount e
								group by e.month, e.year) d
							left join smileycount f
							on (d.month = f.month and d.year = f.year and d.max = f.count)
						) on (j.month=d.month and j.year=d.year)
						left join smilies sm on (f.smiley = sm.id)
						left join
						(
							(select h.month, h.year, max(h.count) max
								from wordcount h
								group by h.month, h.year) g
							left join wordcount i
							on (g.month = i.month and g.year = i.year and g.max = i.count)
						) on (j.month=g.month and j.year=g.year)
						left join words w on (i.word = w.id)
						order by j.year asc, j.month asc",
			'params' => array_merge($params, $params, $params, $params),
			'processing_function' => 'messages_per_month',
			'processing_function_all' => 'duplicates0',
			'columns' => array('Month', 'Messages', 'Top spammer', 'Most popular smiley', 'Most popular word'),
			'column_styles' => array('left', 'right', 'left', 'left', 'left'),
			'derived_queries' => array(
				array(
					'title' => 'Messages per month, ordered by number of messages',
					'transformation_function' => 'busiest_time',
					'processing_function' => 'messages_per_month',
					'processing_function_all' => array('duplicates1', 'ex_aequo2'),
					'columns' => array('Position', 'Month', 'Messages', 'Top spammer', 'Most popular smiley', 'Most popular word'),
					'column_styles' => array('right', 'left', 'right', 'left', 'left', 'left'),
				),
			),
		);
}
if(!isset($_REQUEST['month'])) {
	$queries[] = array(
			'title' => 'Messages per year',
			'query' => "with smileycount as (
				select s.year, sm.smiley, sum(sm.count) count from shouts s join shout_smilies sm on (s.primary_id=sm.shout) where $filter group by s.year, sm.smiley
			), wordcount as (
				select s.year, sw.word, sum(sw.count) count from shouts s join shout_words sw on (s.primary_id=sw.shout) where $filter group by s.year, sw.word
			), hours as (
				select user_id \"user\", year, count(*) count from shouts s where $filter group by user_id, year
			)
					select j.year, j.count shouts, concat(c.user, '$$', u.name, '$$', c.count) top_spammer,
						concat(f.smiley, '$$', sm.filename, '$$', f.count) popular_smiley, concat(i.word, '$$', w.word, '$$', i.count) popular_word
					from (select year, count(s.id) count from shouts s where $filter group by year) j
						left join
						(
							(select year, max(count) max from hours a group by year) b
							left join hours c
							on (b.year=c.year and b.max=c.count)
						) on (j.year=b.year)
						left join users u on (c.user=u.id)
						left join
						(
							(select e.year, max(e.count) max
								from smileycount e
								group by e.year) d
							left join smileycount f
							on (d.year = f.year and d.max = f.count)
						) on (j.year=d.year)
						left join smilies sm on (f.smiley = sm.id)
						left join
						(
							(select h.year, max(h.count) max
								from wordcount h
								group by h.year) g
							left join wordcount i
							on (g.year = i.year and g.max = i.count)
						) on (j.year=g.year)
						left join words w on (i.word = w.id)
						order by j.year asc",
			'params' => array_merge($params, $params, $params, $params),
			'processing_function' => 'messages_per_year',
			'processing_function_all' => 'duplicates0',
			'columns' => array('Year', 'Messages', 'Top spammer', 'Most popular smiley', 'Most popular word'),
			'column_styles' => array('left', 'right', 'left', 'left', 'left'),
			'derived_queries' => array(
				array(
					'title' => 'Messages per year, ordered by number of messages',
					'transformation_function' => 'busiest_time',
					'processing_function' => 'messages_per_year',
					'processing_function_all' => array('duplicates0', 'ex_aequo2'),
					'columns' => array('Position', 'Year', 'Messages', 'Top spammer', 'Most popular smiley', 'Most popular word'),
					'column_styles' => array('right', 'left', 'right', 'left', 'left', 'left'),
				),
			),
		);
}
$queries[] = array(
		'title' => 'Smiley usage',
		'query' => "with smileycount as (
				select s.user_id \"user\", ss.smiley, sum(count) count
					from shouts s join shout_smilies ss on (s.primary_id=ss.shout)
					where $filter 
					group by s.user_id, ss.smiley
			)
				select sm.filename, d.count, concat(u.id, '$$', u.name, '$$', c.count) top
				from
					(select smiley, coalesce(sum(count), 0) count
						from smileycount
						group by smiley) d
				left join
					(
						(select a.smiley, max(count) max
							from smileycount a
							group by a.smiley) b
					left join smileycount c
					on (b.smiley=c.smiley and b.max=c.count))
				on (d.smiley=b.smiley)
				left join users u on (c.user=u.id)
				left join smilies sm on (d.smiley=sm.id)
				order by d.count desc, sm.filename asc",
		'params' => $params,
		'processing_function' => function(&$row) {
				global $smilies;

				if(!isset($smilies)) {
					$query = 'SELECT id, filename FROM smilies';
					$smilies = db_query($query, array());
				}

				foreach($smilies as $smiley) {
					if($smiley['filename'] == $row[0]['filename']) {
						$smiley_id = $smiley['id'];
						break;
					}
				}

				$row[0]['filename'] = '<a href="details.php?smiley=' . $smiley_id . '"><img src="images/smilies/' . $row[0]['filename'] . '" alt="" /></a>';

				$top = explode('$$', $row[0]['top']);
				$user_id = $top[0];
				$username = $top[1];
				$frequency = $top[2];
				$link = 'details.php?user=' . urlencode($username);
				$row[0]['top'] = "<a href=\"$link\">$username</a> (${frequency}x)";
			},
		'processing_function_all' => array('duplicates0', 'insert_position'),
		'columns' => array('Position', 'Smiley', 'Occurrences', 'Top user'),
		'column_styles' => array('right', 'right', 'right', 'left'),
	);
$queries[] = array(
		'title' => 'Word usage (top 100)',
		'query' => "with wordcount as (
			select s.user_id \"user\", sw.word, sum(count) count
				from shouts s join shout_words sw on (s.primary_id=sw.shout)
				where $filter
				group by s.user_id, sw.word
		)
				select w.word, d.count, concat(u.id, '$$', u.name, '$$', c.count) top
				from 
					(select word, coalesce(sum(count), 0) count
						from wordcount
						group by word
						order by count desc
						limit 100) d
				left join
					(
						(select a.word, max(count) max
							from wordcount a
							group by a.word) b
					left join wordcount c
					on (b.word=c.word and b.max=c.count))
				on (d.word=b.word)
				left join users u on (c.user=u.id)
				join words w on (d.word=w.id)
				order by d.count desc, w.word asc",
		'params' => $params,
		'processing_function' => array(function(&$row) {
				$row[0]['word'] = '<a href="details.php?word=' . urlencode($row[0]['word']) . '">' . $row[0]['word'] . '</a>';

				$top = explode('$$', $row[0]['top']);
				$user_id = $top[0];
				$username = $top[1];
				$frequency = $top[2];
				$link = 'details.php?user=' . urlencode($username);
				$row[0]['top'] = "<a href=\"$link\">$username</a> (${frequency}x)";
			}),
		'processing_function_all' => array('duplicates0', 'insert_position'),
		'columns' => array('Position', 'Word', 'Occurrences', 'Top user'),
		'column_styles' => array('right', 'left', 'right', 'left'),
	);
if(!isset($_REQUEST['smiley']) && !isset($_REQUEST['word']) && !isset($_REQUEST['user'])) {
	$queries[] = array(
			'title' => "Ego points",
			'query' => "SELECT u.id AS id, s.message AS message
				FROM shouts s
					JOIN users u ON (s.user_id = u.id)
				WHERE s.deleted = 0
					AND (s.message LIKE '%ego%' OR s.message LIKE '%/hail.gif%' OR s.message LIKE '%/multihail.gif%' OR s.message LIKE '%/antihail.png%')
					AND $filter
				ORDER BY s.id ASC",
			'params' => $params,
			'processing_function_all' => array(function(&$data) {
					$result = calculate_ego($data[0]);
					$user_egos = $result['user_egos'];

					$datax = db_query('SELECT u.id AS id, u.name AS name, c.color AS color
							FROM users u
								JOIN user_categories c ON (u.category = c.id)');
					$users = array();
					foreach($datax as $row) {
						if($row['color'] == '-') {
							$row['color'] = 'user';
						}
						$users[$row['id']] = $row;
					}

					while(count($data[0]) > 0) {
						array_shift($data[0]);
					}
					$pos = 0;
					foreach($user_egos as $id => $ego) {
						$data[0][] = array(
							++$pos,
							'<a href="./?text=ego&amp;user=' . urlencode($users[$id]['name']) . '&amp;limit=100&amp;page=1&amp;date=&amp;refresh=on" class="' . $users[$id]['color'] . '">' . $users[$id]['name'] . '</a>',
							$ego
						);
					}
				}),
			'columns' => array('Position', 'User', 'Ego'),
			'column_styles' => array('right', 'left', 'right'),
			'cached' => false,
			'note' => 'For details about how ego points are calculated, please refer to the <a href="ego.php">global list of ego points</a>.',
		);
}
$queries[] = array(
		'title' => 'First and last posts',
		'query' => "SELECT u.name, TO_CHAR(MIN(s.date)+interval '1 hours', 'YYYY-MM-DD HH24:MI') min_date, TO_CHAR(MAX(s.date)+interval '1 hours', 'YYYY-MM-DD HH24:MI') max_date, EXTRACT(EPOCH FROM (MAX(s.date)-MIN(s.date))) duration
				FROM users u
					JOIN shouts s ON (u.id=s.user_id)
				WHERE s.deleted = 0
					AND $filter
				GROUP BY u.id, u.name
				ORDER BY duration DESC",
		'params' => $params,
		'processing_function' => array('add_user_link', function(&$row) {
			if($row[0]['duration'] >= 86400*2) {
				$row[0]['duration'] = floor($row[0]['duration']/86400) . ' Tage';
			}
			else if($row[0]['duration'] >= 86400) {
				$row[0]['duration'] = 'ein Tag';
			}
			else if($row[0]['duration'] >= 7200) {
				$row[0]['duration'] = floor($row[0]['duration']/3600) . ' Stunden';
			}
			else if($row[0]['duration'] >= 3600) {
				$row[0]['duration'] = 'eine Stunde';
			}
			else if($row[0]['duration'] >= 120) {
				$row[0]['duration'] = floor($row[0]['duration']/60) . ' Minuten';
			}
			else if($row[0]['duration'] >= 60) {
				$row[0]['duration'] = 'eine Minute';
			}
			else {
				$row[0]['duration'] = '-';
			}
		}),
		'processing_function_all' => array('insert_position', 'ex_aequo4'),
		'columns' => array('Position', 'Username', 'First message', 'Last message', 'Time difference'),
		'column_styles' => array('right', 'left', 'left', 'left', 'right'),
	);
 */
/*
$queries[] = array(
		'title' => '',
		'query' => "",
		'columns' => array(),
		'column_styles' => array(),
	);
 */
$query_total = array(
		'query' => "SELECT COUNT(*) shouts FROM message m WHERE $filter",
		'params' => $params,
	);

if($main_page) {
	$page_title = 'Spam overview';
	$backlink = array(
		'url' => 'index.php',
		'text' => 'Chatbox archive',
	);
}
else {
	$page_title = "Spam overview: $what";
	$backlink = array(
			'url' => 'details.php',
			'text' => 'Spam overview',
		);
}

require_once(dirname(__FILE__) . '/../lib/stats.php');

log_data();

