<?php 
	function error($message = 'A request was sent that could not be understood', $status = '400 Bad Request', $includeDefaultMessage = true) {
		header('HTTP/1.1 ' . $status);
		if ($includeDefaultMessage) {
			$defaultMessage = '. API documentation is currently unavailable. You can however always contact the author twitter.com/lucgommans';
		}
		echo '<h3>' . $status . '</h3>' . $message . $defaultMessage;
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

		case 'changeTimePeriod':
			changeTimePeriod();

		case 'set':
			api_set();

		case 'check':
			check();

		case 'extend':
			extend();

		case 'clear':
			$secret = $db->escape_string($_GET['secret']);
			if (clearUrl($secret)) {
				die("1");
			}
			error('Something odd happened', '500 Internal Server Error');

		case 'subscribe':
			subscribe();
			break;

		default:
			error('Unknown command or no command specified');
	}

	error('This code should never be reached, so some weird bug occurred', '500 Internal Server Error');

	function changeTimePeriod() {
		global $db;
		if (!isset($_GET['secret'])) {
			error("No secret included in request.");
		}
		$period = intval($_GET['period']);
		if ($period < 15 && $period > (3600 * 12)) {
			error("Time too short or too long (min. 15 seconds, max. 12 hours)");
		}
		$db->query('UPDATE shorts SET `expires` = ' . (time() + $period) . ' WHERE secret = "' . $db->escape_string($_GET['secret']) . '"') or die("Database error 295710");
		die('1');
	}

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
		if (intval($_GET['val']) == $_GET['val'] && intval($_GET['val']) < 72 * 3600) {
			$newexpires = (time() + intval($_GET['val']));
			$db->query('UPDATE shorts SET `expires` = ' . $newexpires . ' WHERE `secret` = "' . $db->escape_string($_GET['secret']) . '" AND `expires` < ' . $newexpires) or die('Database error 185302');
			die("1");
		}
		error('No value passed or value is higher than maximum time.');
	}

