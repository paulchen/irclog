<?php
$start_time = microtime(true);

require_once('Mail/mime.php');
require_once('Mail.php');
require_once('linkify.php');

$settings = parse_ini_file(dirname(__FILE__) . '/../config.ini', TRUE);

$connect_string = sprintf("pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
	$settings['general']['db_host'],
	$settings['general']['db_port'],
	$settings['general']['db_name'],
	$settings['general']['db_user'],
	$settings['general']['db_pass']);
$db = new PDO($connect_string);

$report_email = $settings['email']['report_email'];
$email_from = $settings['email']['email_from'];

$refresh_time = $settings['web']['refresh_time'];

/* HTTP basic authentication */
if(!defined('STDIN') && !isset($argc)) {
	if(!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
		noauth();
	}

	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];

	$query = 'SELECT hash FROM accounts WHERE username = ?';
	$data = db_query($query, array($username));
	if(count($data) != 1) {
		noauth();
	}

	$hash = crypt($password, $data[0]['hash']);
	if($hash != $data[0]['hash']) {
		noauth();
	}
}

$memcached = new Memcached();
$memcached->addServer('127.0.0.1', '11211');
$memcached_prefix = 'chatbox_dev';
/* TODO
foreach($memcached_servers as $server) {
	$memcached->addServer($server['ip'], $server['port']);
}
 */

$ignored_db_errors = array();

function db_query($query, $parameters = array(), $errors_to_ignore = array()) {
	$stmt = db_query_resultset($query, $parameters, $errors_to_ignore);
	if(!$stmt) {
		return array();
	}
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	db_stmt_close($stmt);
	return $data;
}

function db_stmt_close($stmt) {
	if(!$stmt->closeCursor()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
}

function db_query_resultset($query, $parameters = array(), $errors_to_ignore = array()) {
	global $db, $db_queries, $ignored_db_errors;

	$query_start = microtime(true);
	if(!($stmt = $db->prepare($query))) {
		$error = $db->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	foreach($parameters as $key => $value) {
		$stmt->bindValue($key+1, $value);
	}
	if(!$stmt->execute()) {
		$error = $stmt->errorInfo();
		if(in_array($error[0], $errors_to_ignore)) {
			$ignored_db_errors[] = $error[0];
			return null;
		}
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}

	$query_end = microtime(true);

	if(!isset($db_queries)) {
		$db_queries = array();
	}
	$db_queries[] = array('timestamp' => time(), 'query' => $query, 'parameters' => serialize($parameters), 'execution_time' => $query_end-$query_start);

	return $stmt;
}

function db_error($error, $stacktrace, $query, $parameters) {
	global $report_email, $email_from;

	header('HTTP/1.1 500 Internal Server Error');
	echo "A database error has just occurred. Please freak out, the administrator has already been notified.\n";

	$params = array(
			'ERROR' => $error,
			'STACKTRACE' => dump_r($stacktrace),
			'QUERY' => $query,
			'PARAMETERS' => dump_r($parameters),
			'REQUEST_URI' => (isset($_SERVER) && isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : 'none',
		);
	send_mail('db_error.php', 'Database error', $params, true);
}

function dump_r($variable) {
	ob_start();
	print_r($variable);
	$data = ob_get_contents();
	ob_end_clean();

	return $data;
}

function db_last_insert_id() {
	global $db;

	$data = db_query('SELECT lastval() id');
	return $data[0]['id'];
}

function log_data($output = true) {
	global $db_queries, $start_time;

	$data = array();
	$data['db_queries'] = $db_queries;

	$data['start_time'] = $start_time;
	$data['end_time'] = microtime(true);

	$data['request_uri'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	$data['remote_addr'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	$data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$data['auth_user'] = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
	$data['request_time'] = time();

	$serialized_data = serialize($data);
//	TODO
//	$filename = tempnam(get_setting('request_log_dir'), 'req');
//	file_put_contents($filename, $serialized_data);
	
	
	if($output) {
		echo "<!-- \n";
		print_r($data);
		echo "-->\n";
	}
	return;
}

function noauth() {
	header('WWW-Authenticate: Basic realm="Access restricted"');
	header('HTTP/1.0 401 Unauthorized');
	die();
}

function cp1252_character($matches) {
	return iconv('WINDOWS-1252', 'UTF-8', $matches[1]);
}

function unicode_character($matches) {
	if(($matches[1] == 0x9) || ($matches[1] == 0xA) || ($matches[1] == 0xD) ||
			(($matches[1] >= 0x20) && ($matches[1] <= 0xD7FF)) ||
			(($matches[1] >= 0xE000) && ($matches[1] <= 0xFFFD)) ||
			(($matches[1] >= 0x10000) && ($matches[1] <= 0x10FFFF))) {
		return $matches[0];
	}
	else {
		return ' ';
	}

}

function get_setting($key) {
	$query = 'SELECT value FROM settings WHERE "key" = ?';
	$data = db_query($query, array($key));

	// TODO undefined setting?
	return $data[0]['value'];
}

function set_setting($key, $value) {
	$query = 'INSERT INTO SETTINGS (key, value) VALUES (%s, %s) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value';
	db_query($query, array($key, $value));
}

function insert_smileys1($input) {
	$output = $input;

	// TODO enormous number of unnecessary queries
	$query = "select s.id, sc.code, s.filename, s.meaning from smiley_codes sc join smilies s on (sc.smiley = s.id)";
	$result = db_query($query);
	foreach($result as $row) {
		$code = $row['code'];
		$id = $row['id'];

		$output = str_replace($code, "XXSMILEY_${id}XX", $output);
	}

	return $output;
}

function insert_smileys2($input) {
	$output = $input;

	// TODO enormous number of unnecessary queries
	$query = "select s.id, s.filename, s.meaning from smilies s";
	$result = db_query($query);
	foreach($result as $row) {
		$id = $row['id'];
		$filename = $row['filename'];
		$meaning = htmlentities($row['meaning'], ENT_QUOTES, 'UTF-8');

		$html = "<img src='images/smilies/$filename' alt='$meaning' />";
		$output = str_replace("XXSMILEY_${id}XX", $html, $output);
	}

	return $output;
}

function colorize_nick($text, $nick, $color, $user_link) {
	if($nick == '' || $color == '') {
		return $text;
	}

	$pos = strpos($text, $nick);
	if ($pos !== false) {
		$replacement = '<a style="color: #' . $color . ';" href="' . $user_link . '">' . $nick . '</a>';
		$text = substr_replace($text, $replacement, $pos, strlen($nick));
	}

	return $text;
}

function get_messages($channel = '', $text = '', $user = '', $date = '', $offset = 0, $limit = 100, $last_shown_id = -1, $regex) {
	$errors_to_ignore = array();

	$filters = array('deleted = false', 'c.name = ?');
	$params = array($channel);
	if($text != '') {
		if($regex) {
			$errors_to_ignore[] = '2201B'; // invalid regular expression

			$filters[] = 'm.text ~* ?';
			$params[] = "$text";
		}
		else {
			$filters[] = 'm.text ILIKE ?';
			$params[] = "%$text%";
		}
	}
	if($user != '') {
		$filters[] = 'LOWER(u.username) = LOWER(?)';
		$params[] = $user;
	}
	if($date != '') {
		$filters[] = "TO_DAY(m.timestamp) = ?";
		$params[] = $date;
	}
	$filter = implode(' AND ', $filters);

	$new_messages = 0;
	if($last_shown_id != -1) {
		$count_query = "SELECT COUNT(*) anzahl
				FROM message m
					JOIN channel c ON (m.channel_fk = c.channel_pk)
					LEFT JOIN \"user\" u ON (m.user_fk = u.user_pk)
				WHERE $filter AND m.message_pk > ?";
		$count_params = $params;
		$count_params[] = $last_shown_id;
		$count_data = db_query($count_query, $count_params, $errors_to_ignore);
		$new_messages = $count_data[0]['anzahl'] - $offset;
	}

	$query = "SELECT m.message_pk, m.timestamp, u.username, m.text, m.html, u.color, u.user_pk, m.type
			FROM message m
				JOIN channel c ON (m.channel_fk = c.channel_pk)
				LEFT JOIN \"user\" u ON (m.user_fk = u.user_pk)
			WHERE $filter
			ORDER BY m.message_pk DESC
			OFFSET ? LIMIT ?";
	$params[] = intval($offset);
	$params[] = intval($limit);
	$result = db_query_resultset($query, $params, $errors_to_ignore);

	$data = array();
	$users = array();
	$ids = array();
	if($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$link = '?channel=' . urlencode($channel) . '&amp;user=' . urlencode($row['username']) . "&amp;limit=$limit";
			if($text != '') {
				$link .= '&amp;text=' . urlencode($text);
			}

			if($row['html'] != '') {
				$row['text'] = $row['html'];
			}
			else {
				$row['text'] = insert_smileys1($row['text']);
				$row['text'] = htmlspecialchars($row['text'], ENT_COMPAT, 'UTF-8');
				$row['text'] = linkify($row['text']);
				$row['text'] = insert_smileys2($row['text']);
				if($row['type'] > 0) {
					$row['text'] = colorize_nick($row['text'], $row['username'], $row['color'], $link);
				}

				db_query('UPDATE message SET html = ? WHERE message_pk = ?', array($row['text'], $row['message_pk']));
			}
			unset($row['html']);

			$message_pk = $row['message_pk'];
			unset($row['message_pk']);

			$ids[] = $message_pk;

			$user_pk = $row['user_pk'];
			$username = $row['username'];
			$color = $row['color'];

			unset($row['username']);
			unset($row['color']);
			unset($row['user_link']);

			$data[$message_pk] = $row;

			if($username != '') {
				$users[$user_pk] = array('username' => $username, 'color' => $color, 'link' => $link);
			}
		}
		db_stmt_close($result);
	}

	$last_loaded_id = -1;
	if(count($ids) > 0) {
		$last_loaded_id = $ids[0];
	}

	$query = 'SELECT visible_shouts FROM channel WHERE name = ?';
	$result = db_query($query, array($channel));

	$total_shouts = $result[0]['visible_shouts'];

	if(count($filters) > 2) {
		$query = "SELECT COUNT(*) shouts
				FROM message m
					JOIN channel c ON (m.channel_fk = c.channel_pk)
					LEFT JOIN \"user\" u ON (m.user_fk = u.user_pk)
				WHERE $filter";

		// limit and offset
		array_pop($params);
		array_pop($params);
		$db_data = db_query($query, $params, $errors_to_ignore);
		$filtered_shouts = (count($db_data) > 0) ? $db_data[0]['shouts'] : 0;

	}
	else {
		$filtered_shouts = $total_shouts;
	}

	$page_count = ceil($filtered_shouts/$limit);

	return array(
		'messages' => $data,
		'users' => $users,
		'filtered_shouts' => $filtered_shouts,
		'total_shouts' => $total_shouts,
		'page_count' => $page_count,
		'last_loaded_id' => $last_loaded_id,
		'new_messages' => $new_messages,
	);
}

function send_mail($template, $subject, $parameters = array(), $fatal = false, $attachments = array()) {
	global $email_from, $report_email;

	if(strpos($template, '..') !== false) {
		die();
	}

	$message = file_get_contents(dirname(__FILE__) . "/../templates/mails/$template");

	$patterns = array();
	$replacements = array();
	foreach($parameters as $key => $value) {
		$patterns[] = "[$key]";
		$replacements[] = $value;
	}
	$message = str_replace($patterns, $replacements, $message);

	$headers = array(
			'From' => $email_from,
			'To' => $report_email,
			'Subject' => $subject,
		);

	$mime = new Mail_Mime(array('text_charset' => 'UTF-8'));
	$mime->setTXTBody($message);
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	foreach($attachments as $attachment) {
		$mime->addAttachment($attachment, finfo_file($finfo, $attachment));
	}

	$mail = Mail::factory('smtp');
	$mail->send($report_email, $mime->headers($headers), $mime->get());

	if($fatal) {
		// TODO HTTP error code/message
		die();
	}
}

function xml_validate($data) {
	global $tmpdir;

	$document = new DOMDocument;
	$xml_error = false;
	@$document->LoadXML($data) or $xml_error = true;
	if($xml_error) {
		$filename = tempnam($tmpdir, 'api_');
		file_put_contents($filename, $data);

		$parameters = array('REQUEST_URI' => $_SERVER['REQUEST_URI']);
		$attachments = array($filename);
		// send_mail('validation_error.php', 'Validation error', $parameters, false, $attachments);

		unlink($filename);
	}

	return $data;
}

function fetch_channels() {
	$query = 'SELECT name FROM channel WHERE active = TRUE ORDER BY channel_pk ASC';
	return array_map(function($a) { return $a['name']; }, db_query($query));
}

function get_channel_id($channel_name) {
	$query = 'SELECT channel_pk FROM channel WHERE name = ?';
	$result = db_query($query, array($channel_name));
	if(count($result) != 1) {
		return null;
	}
	return $result[0]['channel_pk'];
}

function resolve_error_code($code) {
	$known_errors = array(
		'2201B' => 'Invalid regular expression',
	);
	if(isset($known_errors[$code])) {
		return "Error: {$known_errors[$code]}";
	}
	return "Error $code";
}
