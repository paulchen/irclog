<?php
// TODO check if included

require_once(dirname(__FILE__) . '/common.php');

function ex_aequo2($data) {
	return ex_aequo($data, 2);
}

function ex_aequo3($data) {
	return ex_aequo($data, 3);
}

function ex_aequo4($data) {
	return ex_aequo($data, 4);
}

function ex_aequo9($data) {
	return ex_aequo($data, 9);
}

function ex_aequo($data, $col) {
	$last_value = -1;
	foreach($data as &$row) {
		$keys = array_keys($row);
		$first_row = $keys[0];
		$compare_row = $keys[$col];
		if($row[$compare_row] == $last_value) {
			$row[$first_row] = '';
		}
		$last_value = $row[$compare_row];
	}
	unset($row);

	return $data;
}

function duplicates0($data) {
	return duplicates($data, array(0));
}

function duplicates1($data) {
	return duplicates($data, array(1));
}

function duplicates($data, $columns) {
	$column_names = array_keys($data[0]);
	$names = array();
	foreach($columns as $column) {
		$names[] = $column_names[$column];
	}

	foreach($data as $index => $row) {
		if(isset($last_row)) {
			$equal = true;
			foreach($names as $name) {
				if($last_row[$name] != $row[$name]) {
					$equal = false;
					break;
				}
			}
			if($equal) {
				unset($data[$index]);
			}
		}
		$last_row = $row;
	}

	return $data;
}

function insert_position($data) {
	$index = 0;
	foreach($data as &$row) {
		$index++;
		array_unshift($row, "$index.");
	}
	unset($row);
	return $data;
}

function top_spammers($data) {
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

			$derived_query['data'] = call_user_func($derived_query['transformation_function'], $data);
			$queries[$index] = $derived_query;
		}
	}
}

foreach($queries as $index => $query) {
	$data = $query['data'];

	if(isset($query['processing_function_all'])) {
		if(is_array($query['processing_function_all'])) {
			foreach($query['processing_function_all'] as $func) {
				$data = call_user_func($func, $data);
			}
		}
		else {
			call_user_func($query['processing_function_all'], $data);
		}
	}

	if(isset($query['processing_function'])) {
		if(is_array($query['processing_function'])) {
			foreach($query['processing_function'] as $func) {
				foreach($data as $key => &$value) {
					$value = call_user_func($func, $value);
				}
				unset($value);
			}
		}
		else {
			foreach($data as $key => &$value) {
				$value = call_user_func($query['processing_function'], $value);
			}
			unset($value);
		}
	}

	if(isset($query['total'])) {
		$queries[$index]['total'] = call_user_func($query['total'], $data);
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

