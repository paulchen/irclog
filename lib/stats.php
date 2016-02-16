<?php
// TODO check if included

require_once(dirname(__FILE__) . '/common.php');

function ex_aequo2(&$data) {
	ex_aequo($data, 2);
}

function ex_aequo3(&$data) {
	ex_aequo($data, 3);
}

function ex_aequo4(&$data) {
	ex_aequo($data, 4);
}

function ex_aequo9(&$data) {
	ex_aequo($data, 9);
}

function ex_aequo(&$data, $col) {
	$last_value = -1;
	foreach($data[0] as &$row) {
		$keys = array_keys($row);
		$first_row = $keys[0];
		$compare_row = $keys[$col];
		if($row[$compare_row] == $last_value) {
			$row[$first_row] = '';
		}
		$last_value = $row[$compare_row];
	}
	unset($row);
}

/*
function spammer_smiley(&$row) {
	if($row[0]['top_spammer'] != '$$$$') {
		$parts = explode('$$', $row[0]['top_spammer']);
		$row[0]['top_spammer'] = "<a href=\"details.php?user={$parts[1]}\">{$parts[1]}</a> ({$parts[2]}x)";
	}
	else {
		$row[0]['top_spammer'] = '-';
	}

	if($row[0]['popular_smiley'] != '$$$$') {
		$parts = explode('$$', $row[0]['popular_smiley']);
		$row[0]['popular_smiley'] = "<a href=\"details.php?smiley={$parts[0]}\"><img src=\"images/smilies/{$parts[1]}\" alt=\"\" /></a> ({$parts[2]}x)";
	}
	else {
		$row[0]['popular_smiley'] = '-';
	}

	if($row[0]['popular_word'] != '$$$$') {
		$parts = explode('$$', $row[0]['popular_word']);
		$row[0]['popular_word'] = "<a href=\"details.php?word=" . urlencode($parts[1]) . "\">{$parts[1]}</a> ({$parts[2]}x)";
	}
	else {
		$row[0]['popular_word'] = '-';
	}
}
 */

function duplicates0(&$data) {
	duplicates($data, array(0));
}

function duplicates1(&$data) {
	duplicates($data, array(1));
}

function duplicates(&$data, $columns) {
	if(count($data[0]) == 0) {
		return;
	}

	$column_names = array_keys($data[0][0]);
	$names = array();
	foreach($columns as $column) {
		$names[] = $column_names[$column];
	}

	foreach($data[0] as $index => $row) {
		if(isset($last_row)) {
			$equal = true;
			foreach($names as $name) {
				if($last_row[$name] != $row[$name]) {
					$equal = false;
					break;
				}
			}
			if($equal) {
				unset($data[0][$index]);
			}
		}
		$last_row = $row;
	}
}

function insert_position(&$data) {
	$index = 0;
	foreach($data[0] as &$row) {
		$index++;
		array_unshift($row, "$index.");
	}
}

/*
function smiley_column(&$row) {
	if($row[0]['smiley_info'] == '$$$$') {
		$row[0]['smiley_info'] = '-';
		return;
	}

	$smiley_info = explode('$$', $row[0]['smiley_info']);
	$id = $smiley_info[0];
	$filename = $smiley_info[1];
	$count = $smiley_info[2];

	$row[0]['smiley_info'] = "<a href=\"details.php?smiley=$id\"><img src=\"images/smilies/$filename\" alt=\"\" /></a>&nbsp;(${count}x)";
}

function word_column(&$row) {
	if($row[0]['word_info'] == '$$$$') {
		$row[0]['word_info'] = '-';
		return;
	}

	$word_info = explode('$$', $row[0]['word_info']);
	$id = $word_info[0];
	$word = $word_info[1];
	$count = $word_info[2];
	$link = 'details.php?word=' . urlencode($word);
	$row[0]['word_info'] = "<a href=\"$link\">$word</a>&nbsp;(${count}x)";
}
 */

function top_spammers($data) {
	$data = $data[0];
	foreach($data as $index => $row) {
		if(isset($last_row) && $last_row['name'] == $row['name']) {
			unset($data[$index]);
		}
		$last_row = $row;
	}

	usort($data, function($a, $b) {
		if($a['average_shouts_per_day'] == $b['average_shouts_per_day']) {
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
		if($a['average_shouts_per_day'] < $b['average_shouts_per_day']) {
			return 1;
		}
		return -1;
	});

	foreach($data as $index => &$row) {
		array_unshift($data[$index], ($index+1) . '.');
	}

	return $data;
}

function busiest_hours($data) {
	$data = $data[0];
	usort($data, function($a, $b) {
		if($a['shouts'] == $b['shouts']) {
			if($a['hour'] == $b['hour']) {
				return 0;
			}
			if($a['hour'] < $b['hour']) {
				return 1;
			}
			return -1;
		}
		if($a['shouts'] < $b['shouts']) {
			return 1;
		}
		return -1;

	});

	return array_filter($data, function($a) { return $a['shouts'] != '0'; });
}

function busiest_time($data) {
	$data = $data[0];
	// TODO duplicate code
	usort($data, function($a, $b) {
		if($a['shouts'] == $b['shouts']) {
			return 0;
		}
		if($a['shouts'] < $b['shouts']) {
			return 1;
		}
		return -1;

	});
	foreach($data as $index => &$row) {
		array_unshift($row, ($index+1) . '.');
	}	
	return $data;
}

$last_update = -1;
for($index=0; $index<count($queries); $index++) {
	$query = $queries[$index];

	if(!isset($query['params'])) {
		$query['params'] = array();
	}
	$hash = sha1($query['query'] . serialize($query['params']));
	$memcached_key = "${memcached_prefix}_stats_$hash";
	$memcached_data = $memcached->get($memcached_key);
	if($memcached_data && !isset($_REQUEST['update']) && !(isset($query['cached']) && !$query['cached'])) {
		$last_update = max($memcached_data['update'], $last_update);
		$data = $memcached_data['data'];
	}
	else {
		$data = db_query($query['query'], $query['params']);

		$memcached_data = array(
				'update' => time(),
				'data' => $data
			);
		// TODO magic number
		$memcached->set($memcached_key, $memcached_data, 600+rand(0,100));

		$last_update = time();
	}

	$queries[$index]['data'] = $data;

	if(isset($query['derived_queries'])) {
		foreach($query['derived_queries'] as $derived_query) {
			for($a=count($queries)-1; $a>$index; $a--) {
				$queries[$a+1] = $queries[$a];
				unset($queries[$a]);
			}
			$index++;

			$derived_query['data'] = call_user_func($derived_query['transformation_function'], array($data));
			$queries[$index] = $derived_query;
		}
	}
}

foreach($queries as $index => $query) {
	$data = $query['data'];

	if(isset($query['processing_function_all'])) {
		if(is_array($query['processing_function_all'])) {
			foreach($query['processing_function_all'] as $func) {
				call_user_func($func, array(&$data));
			}
		}
		else {
			call_user_func($query['processing_function_all'], array(&$data));
		}
	}

	if(isset($query['processing_function'])) {
		if(is_array($query['processing_function'])) {
			foreach($query['processing_function'] as $func) {
				foreach($data as $key => &$value) {
					call_user_func($func, array(&$value));
				}
				unset($value);
			}
		}
		else {
			foreach($data as $key => &$value) {
				call_user_func($query['processing_function'], array(&$value));
			}
			unset($value);
		}
	}

	if(isset($query['total'])) {
		$queries[$index]['total'] = call_user_func($query['total'], array($data));
	}

	foreach($data as $row) {
		if(count($row) != count($queries[$index]['column_styles'])) {
			die('Invalid value of array column_styles in query with title: ' . $queries[$index]['title']);
		}
	}
	$queries[$index]['data'] = $data;
}

ksort($queries);

$query = 'SELECT COUNT(*) shouts FROM message WHERE deleted = false';
$data = db_query($query);
$total_shouts = $data[0]['shouts'];

if($query == $query_total['query'] && count($query_total['params']) == 0) {
	$filtered_shouts = $total_shouts;
}
else {
	$data = db_query($query_total['query'], $query_total['params']);
	$filtered_shouts = $data[0]['shouts'];
}

ob_start();
require_once(dirname(__FILE__) . '/../templates/pages/stats.php');
$data = ob_get_contents();
ob_clean();

xml_validate($data);
header('Content-Type: application/xhtml+xml; charset=utf-8');
ob_start("ob_gzhandler");
echo $data;

