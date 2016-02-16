<?php
if(!$ajax):
	echo '<?xml version="1.0" ?>';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<title>Chatbox archive</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<style type="text/css">
	body { font-family: Tahoma, Calibri, Verdana, Geneva, sans-serif; font-size: 13px; }
	table { border: none; }
	td.date, td.user { white-space: nowrap; }
	a { text-decoration: none; color: #417394; }
	h1 > a { color: black; }
	a.user, span.user { color: #417394; }
	a.purple, span.purple { color: purple; font-weight: bold; }
	a.green, span.green { color: green; font-weight: bold; }
	a.red, span.red { color: red; font-weight: bold; }
	a.blue, span.blue { color: blue; font-weight: bold; }
	td.date { white-space: nowrap; }
	td.date > a { color: black; }
	td.date > a:hover { color: red; }
	td.user { white-space: nowrap; }
	td.message { width: 100%; }
	a:hover { color: red; }
	img { border: none; }
	td { padding: 2px; }
	span.servicemsg { font-style: italic; }
	</style>
        <link href="css/jquery-ui.css" rel="stylesheet" type="text/css"></link>
	<script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/jquery-ui.js"></script>
	<script type="text/javascript">
<!--
var timeout;

var last_loaded_id = <?php echo $last_loaded_id ?>;
var last_shown_id = <?php echo $last_loaded_id ?>;
var last_shown_id_backup = <?php echo $last_loaded_id ?>;

function refresh() {
	var url = document.location.href;
	if(url.indexOf('#') > -1) {
		url = url.substring(0, url.indexOf('#'));
	}
	if(url.indexOf('?') == -1) {
		url += "?";
	}
	else {
		url += "&";
	}
	url += 'ajax=on';
	if(!tab_active) {
		url += '&last_shown_id=' + last_shown_id;
	}

	$.ajax({
		url : url,
		success : function(data, textStatus, xhr) {
			var pos = data.indexOf('$$');
			var parts = data.substring(0, pos).split(' ');
			data = data.substring(pos+2);

			pos = data.indexOf('$$');
			var ids = data.substring(0, pos).split(' ');
			data = data.substring(pos+2);

			$('#content').children().remove();
			$('#content').append(data);
			$('#shouts_filtered').text(parts[1]);
			$('#shouts_total').text(parts[2]);
			$('.page_count').text(parts[0]);
			last_loaded_id = parts[3];
			new_messages = parts[4];
			if(tab_active) {
				last_shown_id = last_loaded_id;
				new_messages = 0;
			}
			else {
				show_unread_message_count();
			}
			last_shown_id_backup = last_loaded_id;
			$('.next_link').attr('href', "<?php echo $generic_link ?>" + Math.min(parts[0], <?php echo $page+1 ?>));
			$('.last_link').attr('href', "<?php echo $generic_link ?>" + parts[0]);
		},
		complete : function(xhr, textStatus) {
			update_refresh();
		}
	});
}

function update_refresh() {
	clearTimeout(timeout);
	if($('#refresh_checkbox').is(':checked')) {
		timeout = setTimeout('refresh();', <?php echo $refresh_time*1000 ?>);
	}
}

function reset_form() {
	if($('#refresh_checkbox').is(':checked')) {
		document.location.href = '?refresh=on';
	}
	else {
		document.location.href = '?';
	}
}

function highlight(id, color, initial_wait, step_wait, step) {
	$(window.location.hash).parents('tr').css('background-color', '#' + color.toString(16));
	if(color < 0xFFFFFF) {
		new_color = color+step;
		if(new_color > 0xFFFFFF) {
			new_color = 0xFFFFFF;
		}
		window.setTimeout('highlight("' + id + '",' + new_color + ',0,' + step_wait + ',' + step + ');', initial_wait+step_wait);
	}
}

$(document).ready(function() {
	if(window.location.hash != '') {
		highlight(window.location.hash, 0xFFFF00, 3000, 100, 0x000005);
	}
	update_refresh();

	$('#refresh_checkbox').change(function() {
		update_refresh();
	});

	$('#name_input').autocomplete({
		source : <?php echo $users; ?>,
	});

	$('#date_input').datepicker({
		firstDay : 1,
		dateFormat : 'yy-mm-dd',
	});

	$(window).on("focus hover", function(e) {
		tab_enabled();
	});

	$(window).blur(function(e) {
		tab_disabled();
	});
});

var tab_active = true;
var new_messages = 0;

function reset_unread_message_count() {
	new_messages = 0;
	show_unread_message_count();
}

function show_unread_message_count() {
	var title = '';
	if(new_messages > 0) {
		title = '(' + new_messages + ') ';
	}
	title += 'Chatbox archive';
	$(document).prop('title', title);
}

function tab_enabled() {
	if(tab_active) {
		return;
	}

	last_shown_id = last_shown_id_backup;
	reset_unread_message_count();
	tab_active = true;
}

function tab_disabled() {
	if(!tab_active) {
		return;
	}

	reset_unread_message_count();
	tab_active = false;
}
// -->
	</script>
</head>
<body>
	<h1><a href="index.php">#chatbox archive</a></h1>
	<div>
		<a href="details.php" style="white-space: nowrap;">Spam overview</a>
		<fieldset><legend>Filters</legend>
		<form method="get" action="<?php echo htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
		<table>
		<tr><td>Text:</td><td><input type="text" name="text" value="<?php if(isset($_GET['text'])) echo htmlentities($_GET['text'], ENT_QUOTES, 'UTF-8') ?>" /></td></tr>
		<tr><td>User:</td><td><input type="text" name="user" value="<?php if(isset($_GET['user'])) echo htmlentities($_GET['user'], ENT_QUOTES, 'UTF-8') ?>" id="name_input" /></td></tr>
		<tr><td>Messages per page:</td><td><input type="text" name="limit" value="<?php echo $limit; ?>" /></td></tr>
		<tr><td>Page:</td><td><span style="white-space: nowrap;"><input type="text" name="page" value="<?php echo $page; ?>" /> (of <span class="page_count"><?php echo $page_count; ?></span>)</span> <span style="white-space: nowrap;"><a href="<?php echo $first_link ?>">First</a> <a href="<?php echo $previous_link ?>">Previous</a> <a href="<?php echo $next_link ?>" class="next_link">Next</a> <a href="<?php echo $last_link ?>" class="last_link">Last</a></span></td></tr>
		<tr><td>Date:</td><td><input type="text" name="date" value="<?php if(isset($_GET['date'])) echo htmlentities($_GET['date'], ENT_QUOTES, 'UTF-8') ?>" id="date_input" /></td></tr>
		<tr><td></td><td><input type="submit" value="Filter" /><input type="button" value="Reset" onclick="reset_form();" /></td></tr>
		<tr><td></td><td><input id="refresh_checkbox" type="checkbox" name="refresh" <?php if($refresh) echo 'checked="checked"'; ?> />&nbsp;<label for="refresh_checkbox">Auto-refresh every <?php echo $refresh_time ?> seconds.</label></td></tr>
		</table>
		</form>
		</fieldset>
		<div style="padding: 10px 5px 10px 5px;">
			Messages (filtered/total): <span id="shouts_filtered"><?php echo $filtered_shouts ?></span>/<span id="shouts_total"><?php echo $total_shouts ?></span>
			<span id="post_status"></span>
		</div>
		<div id="content">
<?php else:
	echo "$page_count $filtered_shouts $total_shouts $last_loaded_id $new_messages$$";
	echo '$$';
endif; /* if(!$ajax) */ ?>
			<table style="border-collapse: collapse; width: 100%;">
				<?php foreach($messages as $message): ?>
					<tr>
						<td class="date"><a id="message<?php echo $message['message_pk'] ?>"></a><a href="?limit=<?php echo $limit ?>&amp;id=<?php echo $message['message_pk'] ?>"><?php echo $message['timestamp'] ?></a></td>
						<td class="user"><a style="color: #<?php echo $message['color'] ?>" href="<?php echo $message['user_link'] ?>"><?php echo $message['username'] ?></a></td>
						<td class="message">
							<?php if($message['username'] == ''): ?>
								<span class="servicemsg"><?php echo $message['text'] ?></span>
							<?php else: ?>
								<?php echo $message['text'] ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
<?php if(!$ajax): ?>
		</div>
		<div style="padding-top: 15px; padding-left: 5px; white-space: nowrap;">
			Page <?php echo $page; ?> of <span class="page_count"><?php echo $page_count; ?></span> &ndash; <a href="<?php echo $first_link ?>">First</a> <a href="<?php echo $previous_link ?>">Previous</a> <a href="<?php echo $next_link ?>" class="next_link">Next</a> <a href="<?php echo $last_link ?>" class="last_link">Last</a>
		</div>
	</div>
	<hr />
	<p>
		<a href="http://validator.w3.org/check?uri=referer"><img src="images/xhtml.png" alt="Valid XHTML 1.1" height="31" width="88" /></a>
	</p>
</body>
</html>
<?php endif; /* if(!$ajax) */ ?>

