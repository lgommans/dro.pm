<?php 
	function error($message = 'A request was sent that could not be understood', $status = '400 Bad Request') {
		header('HTTP/1.1 ' . $status);
		echo '<h3>' . $status . '</h3>' . $message;
		exit;
	}

	if (!isset($_GET['v'])) {
		error();
	}

	$ApiVersion = intval($_GET['v']);
	if ($ApiVersion != 1 && $ApiVersion != 2) {
		error('Invalid version number');
	}

	require('dbconn.php');
	require('functions.php');

	switch ($_GET['cmd']) {
		case 'allocate':
			die(json_encode(allocate($_GET['code'], $ApiVersion)));

		case 'set':
			api_set();

		case 'setExpireAfterDownload':
			setExpireAfterDownload($_GET['secret'], $_GET['expireAfterDownload'] == 'false' ? '0' : '1');
			die('1');

		case 'check':
			check();

		case 'extend':
			extend();

		case 'move':
			move($_GET['oldsecret'], $_GET['newsecret']);
			die('1');

		case 'clear':
			$secret = $db->escape_string($_GET['secret']);
			clearUrl($secret);
			die('1');

		default:
			error('Unknown command or no command specified');
	}

	error('This code should never be reached, so some weird bug occurred', '500 Internal Server Error');

	function check() {
		$i = 0;
		do {
			list($status, $type, $data) = tryGet($_GET['val'], true);
			if ($status === true) {
				die('1');
			}
			usleep(250 * 1000);
		} while($i++ < 10);
		die("0");
	}

	function extend() {
		global $db;

		if (!isset($_GET['secret'])) {
			error("No secret included in request");
		}

		if (intval($_GET['val']) != $_GET['val'] || intval($_GET['val']) > 72 * 3600 || intval($_GET['val']) < 10) {
			error('Missing or invalid value, or value is higher than the maximum or lower than the minimum time.');
		}

		$newexpires = time() + intval($_GET['val']);
		$db->query('UPDATE shorts SET `expires` = ' . $newexpires . ' WHERE `secret` = "' . $db->escape_string($_GET['secret']) . '" AND `expires` < ' . $newexpires) or die('Database error 185302');

		die("1");
	}

