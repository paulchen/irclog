<?php
function request_value($key, $default) {
	if(!isset($_REQUEST[$key])) {
		return $default;
	}
	return htmlentities($_REQUEST[$key], ENT_QUOTES, 'UTF-8');
}

function create_page_link($caption, $page) {
	global $pages;

	if($page < 1) {
		$page = 1;
	}
	if($page > $pages) {
		$page = $pages;
	}

	echo "<a href=\"?page=$page&amp;q=" . request_value('q', '') . "&amp;nickname=" . request_value('nickname', '') . "\">$caption</a> ";
}

require_once(dirname(__FILE__) . '/../lib/common.php');

if(isset($_REQUEST['permalink'])) {
	$index = $_REQUEST['permalink'];
	$stmt = $db->prepare('SELECT COUNT(*) messages FROM message WHERE message_pk > :pk');
	$stmt->execute(array($index));
	$row = $stmt->fetch(PDO::FETCH_OBJ);
	$stmt->closeCursor();
	$messages = $row->messages;
	$page = floor($messages/100)+1;
	header("Location: ?page=$page#message$index");
	die();
}

$page = 1;
if(isset($_REQUEST['page'])) {
	$page = $_REQUEST['page'];
	if(!preg_match('/^[1-9][0-9]*$/', $page)) {
		die();
	}
}
$offset = ($page-1)*100;

$where_parts = array();
$params = array();

if(isset($_REQUEST['q']) && trim($_REQUEST['q']) != '') {
	$where_parts[] = 'raw_text ILIKE :q';
	$params[] = '%' . $_REQUEST['q'] . '%';
}
if(isset($_REQUEST['nickname']) && trim($_REQUEST['nickname']) != '') {
	$where_parts[] = 'LOWER(nickname) = :nickname';
	$params[] = strtolower($_REQUEST['nickname']);
}

$where = '';
if(count($where_parts) > 0) {
	$where = 'WHERE ' . implode(' AND ', $where_parts);
}

$stmt = $db->prepare("SELECT COUNT(*) messages FROM message $where");
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_OBJ);
$stmt->closeCursor();
$messages = $row->messages;
$pages = ceil($messages/100);

?>

<form method="get" action=".">
<table>
<tr><td>Text</td><td><input type="text" name="q" value="<?php echo request_value('q', ''); ?>" /></td></tr>
<tr><td>User</td><td><input type="text" name="nickname" value="<?php echo request_value('nickname', ''); ?>" /></td></tr>
<tr><td>Page</td><td><input type="text" name="page" value="<?php echo request_value('page', 1); ?>" /></td>
<td>
<?php
create_page_link('First', 1);
create_page_link('Previous', $page-1);
create_page_link('Next', $page+1);
create_page_link('Last', $pages);
?>
</td>
</tr>
</table>
<input type="submit">
</form>

<hr />

<div style="font-family: monospace;">
<?php
$stmt = $db->prepare("SELECT * FROM message $where ORDER BY message_pk DESC LIMIT 100 OFFSET $offset");
$stmt->execute($params);
while($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	$link = '?permalink=' . $row->message_pk;
	$anchor = 'message' . $row->message_pk;
	$timestamp = $row->timestamp;
	$text = htmlentities(preg_replace('/^[^ ]+ /', '', $row->raw_text), ENT_QUOTES, 'UTF-8');
	print "<a id=\"$anchor\" href=\"$link\">$timestamp</a> $text<br />";
}

$stmt->closeCursor();
?>
</div>

