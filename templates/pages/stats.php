<?php
echo '<?xml version="1.0" ?>';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<!-- TODO HTML tags inside page title -->
	<title><?php echo htmlentities($page_title, ENT_QUOTES, 'UTF-8') ?></title>
	<style type="text/css">
	body { font-family: Tahoma, Calibri, Verdana, Geneva, sans-serif; font-size: 13px; }
	table { border: none; }
	td.date, td.user { white-space: nowrap; }
	a { text-decoration: none; color: #417394; }
	h1 > a { color: black; }
	a:hover { color: red; }
	a.user { color: #417394; }
	a.purple { color: purple; font-weight: bold; }
	a.green { color: green; font-weight: bold; }
	img { border: none; }
	th.left, td.left { text-align: left; }
	th.right, td.right { text-align: right; }
	th { cursor: pointer; }
	th:hover { color: red; }
	</style>
	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript" src="js/jquery.tablesorter.min.js"></script>
	<script type="text/javascript">
$(document).ready(function() {
	$('.sortable_table').tablesorter();
});
	</script>
</head>
<body>
	<?php /* TODO <h1><?php echo htmlentities($page_title, ENT_QUOTES, 'UTF-8') ?></h1> */ ?>
	<h1><a href="details.php"><?php echo $page_title ?></a></h1>
	<div>
		<a href="<?php echo $backlink['url'] ?>"><?php echo htmlentities($backlink['text'], ENT_QUOTES, 'UTF-8') ?></a>
		<ul>
		<?php $b=0; foreach($queries as $query): $b++; ?>
			<li><a href="#query<?php echo $b; ?>"><?php echo htmlentities($query['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
		<?php endforeach; ?>
		</ul>
	Messages (on this page/total): <?php echo "$filtered_shouts/$total_shouts" ?>
	<br /><br />
	Last update: <?php echo date('Y-m-d H:i:s', $last_update) ?>
	<br /><br />
	The tables on this page can be sorted by clicking on their column headers.
	</div>
	<hr />
	<?php $b=0; foreach($queries as $query): $b++; ?>
		<div>
			<a id="query<?php echo $b ?>"></a>
			<h2><?php echo $query['title'] ?></h2>
			<?php if(isset($query['note'])): ?>
				<div style="padding-bottom: 10px;">
					<?php echo $query['note']; ?>
				</div>
			<?php endif; ?>
			<table class="sortable_table"><thead><tr>
			<?php $a = 0; foreach($query['columns'] as $column): ?>
			<th class="<?php echo $query['column_styles'][$a] ?>"><?php echo $column; ?></th>
			<?php $a++; endforeach; ?>
			</tr></thead><tbody>
			<?php
				foreach($query['data'] as $row):
					$a = 0;
			?>
				<tr>
				<?php foreach($row as $key => $value): ?>
					<td class="<?php echo $query['column_styles'][$a] ?>"><?php echo $value ?></td>
				<?php $a++; endforeach; ?>
				</tr>
			<?php
				endforeach;
			?>
			</tbody>
			<?php if(isset($query['total'])): $a = 0; ?>
				<tfoot>
					<tr>
						<?php foreach($query['total'] as $value): ?>
							<th class="<?php echo $query['column_styles'][$a] ?>"><?php echo $value ?></th>
						<?php $a++; endforeach; ?>
					</tr>
				</tfoot>
			<?php endif; ?>
			</table>
		</div>
		<hr />
	<?php endforeach; ?>
	<?php if(isset($extra_stats)): echo $extra_stats; endif; ?>
	<p>
		<a href="http://validator.w3.org/check?uri=referer"><img src="images/xhtml.png" alt="Valid XHTML 1.1" height="31" width="88" /></a>
	</p>
</body>
</html>
