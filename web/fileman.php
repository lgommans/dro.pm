<?php 
	// TODO there seems to be encoding applied by firefox on file upload, like %22 for quotes maybe. Should test with more browsers and decode that, especially if % is escaped as %25 by all.
	require('api/dbconn.php');
	require('api/functions.php');

	if (!isset($_FILES['f'])) {
		header("HTTP/1.1 400 Bad Request");
		die("No file given?");
	}

	$original_filename = $_FILES['f']['name'];
	if (strpos($original_filename, '/') !== false || strpos($original_filename, "\\") !== false || empty($original_filename)) {
		header('HTTP/1.1 400 Bad Request');
		die('Forward and backward slashes are not allowed in the filename, and it must not be empty.');
	}

	$retval = "1";

	$expireAfterDownload = '0';
	if (isset($_GET['expireAfterDownload']) && $_GET['expireAfterDownload'] == 'true') {
		$expireAfterDownload = '1';
	}

	if (empty($_GET['secret'])) {
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'cli') === 0 || strpos($_SERVER['HTTP_USER_AGENT'], 'dro.pm-androidapp') === 0) {
			list($secret, $key) = allocate();
			$retval = htmlspecialchars($secret) . ' dro.pm/' . htmlspecialchars($key);
		}
		else {
			die('Missing required parameter.');
		}
	}
	else {
		$secret = $_GET['secret'];

		$key = $db->query('SELECT `key` FROM `shorts` WHERE `secret` = "' . $db->escape_string($secret) . '"') or die('Database error 3984');
		if ($key->num_rows != 1) {
			die('?secret=' . htmlspecialchars($secret) . ' not found');
		}
		$key = $key->fetch_row()[0];
	}

	$success = move_uploaded_file($_FILES['f']['tmp_name'], 'uploads/' . $key);
	if (!$success) {
		die('Error moving file. It may be that the server\'s storage space is full, or some other cause I am not aware of. Let me know if you run into this!');
	}

	$data = ['original_filename' => $original_filename, 'filename' => $key];
	$data = $db->escape_string(json_encode($data));
	// TODO put this magic 18*3600 in a file somewhere so that it can also be shared/sync'd with api/functions.php
	$db->query('UPDATE `shorts` SET `expires` = ' . (time() + (18 * 3600)) . ', `type` = 2, `value` = "' . $data . '", expireAfterDownload = ' . $expireAfterDownload
		. ' WHERE `secret` = "' . $db->escape_string($secret) . '"') or die('Database error 29348');

	die($retval);

