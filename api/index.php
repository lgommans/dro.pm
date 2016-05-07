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
	
	$v = intval($_GET['v']);
	if ($v != 1) {
		error('Invalid version number');
	}
	
	require('dbconn.php');
	require('functions.php');
	
	switch ($_GET['cmd']) {
		case 'allocate':
			die(json_encode(allocate($_GET['code'])));

		case 'changeTimePeriod':
			changeTimePeriod();

		case 'set':
			set();

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

	function set() {
		global $db;
		if (!isset($_GET['secret'])) {
			error("No secret included in request.");
		}
		
		$secret = $db->escape_string($_GET['secret']);
		$data = $_POST['val'];
		
		if (empty($data)) {
			if (clearUrl($secret)) {
				die('1');
			}
			error('Something odd happened', '500 Internal Server Error');
		}
		
		$host = parse_url($data, PHP_URL_HOST);
		if ($host === false || empty($host) || strlen($data) > 25000 || strpos($data, "\n") !== false || strpos($data, " http") !== false) {
			$db->query('DELETE FROM pastes WHERE `secret` = "' . $secret . '"') or die('Database error 62871');
			$db->query('INSERT INTO pastes VALUES("' . $db->escape_string($data) . '", "' . $secret . '")') or die('Database error 518543');
			$data = substr($secret, 0, 40);
			$type = "1";
		}
		else {
			$type = "0";
		}
		
		$db->query('UPDATE shorts '
			. 'SET `value` = "' . $db->escape_string($data) . '", '
				. '`expires` = ' . (time() + (3600 * 12)) . ', '
				. '`type` = "' . $type . '" '
			. 'WHERE secret = "' . $db->escape_string($_GET['secret']) . '"')
			or die("Database error 28943");
		
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

