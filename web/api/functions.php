<?php 
	// Returns an array with either:
	// if api version 1: [string secret, string code]
	// if api version 2: [bool already_exists, string secret, string code]
	function allocate($code = false, $ApiVersion = 1) {
		global $db;

		$secret = bin2hex(openssl_random_pseudo_bytes(20));
		if (empty($code)) {
			$code = getNewCode($secret);
		}
		else {
			$exists = codeExists($code);
			if ($exists === "semi") {
				clearUrl(getSecretByCode($code));
				$db->query('DELETE FROM shorts WHERE `key` = "' . $db->escape_string($code) . '"') or die('Database error 95184');
			}
			if ($exists === true) {
				return [true];
			}
			else {
				$db->query("INSERT INTO shorts (`key`, `type`, `value`, `expires`, `secret`) VALUES('" . $db->escape_string($code) . "', -1, '', " . (time() + 900) . ", '" . $secret . "')") or die('Database error 81935');
			}
		}

		if ($ApiVersion == 2) {
			return [false, $secret, $code];
		}

		return [$secret, $code];
	}

	// Returns true for taken; false for not taken; "semi" for taken but expired (will evaluate to true for unaware functions)
	function codeExists($code) {
		global $db;
		$result = $db->query('SELECT `secret`, `type` FROM shorts WHERE `key` = "' . $db->escape_string($code) . '"') or die('Database error 671948');
		if ($result->num_rows > 0) {
			$result = $result->fetch_row();
			if ($result[1] == 2) {
				$result = $db->query('SELECT `data` FROM pastes WHERE `secret` = "' . $db->escape_string($secret) . '"') or die('Database error 95279');
				if ($result->fetch_row()[0] == 'This link has already been downloaded.') {
					return "semi";
				}
			}
			return true;
		}
		return false;
	}

	function getAllowedCharset() {
		$allowedCharSet = 'a-hj-km-z2-9';
		$chars = array();
		for ($i = 0; $i < strlen($allowedCharSet); $i += 3) {
			for ($j = ord($allowedCharSet[$i]); $j < ord($allowedCharSet[$i + 2]); $j++) {
				$chars[] = chr($j);
			}
		}
		return $chars;
	}

	function getAllowedShorts() {
		$chars = getAllowedCharset();
		$shorts = [];
		foreach ($chars as $char1) {
			$shorts[] = $char1;
		}
		foreach ($chars as $char1) {
			foreach ($chars as $char2) {
				$shorts[] = $char1 . $char2;
			}
		}
		foreach ($chars as $char1) {
			foreach ($chars as $char2) {
				foreach ($chars as $char3) {
					$shorts[] = $char1 . $char2 . $char3;
				}
			}
		}
		return $shorts;
	}

	function getNewCode($secret) {
		global $db;
		$result = $db->query("SELECT `value` FROM `shorts` WHERE `type` = 2 AND `expires` < " . time());
		if ($result->num_rows > 0) {
			while ($row = $result->fetch_row()) {
				$data = unserialize($row[0]);
				if (strpos(substr(getcwd(), strlen(getcwd()) - 4), 'api') === false) {
					$dir = 'uploads/';
				}
				else {
					$dir = '../uploads/';
				}
				@unlink($dir . $data['filename']);
			}
		}
		$db->query("DELETE FROM shorts WHERE `expires` < " . time());
		if (rand(1, 10) == 5) {
			$db->query("DELETE FROM pastes WHERE NOT EXISTS (SELECT secret FROM shorts WHERE shorts.`secret` = pastes.`secret`)");
		}

		// Get all possible shortcodes
		$possibleCodes = array_flip(getAllowedShorts());

		// Now remove all used ones
		$result = $db->query("SELECT `key` FROM shorts") or die('Database error 2539');
		while ($row = $result->fetch_row()) {
			if (isset($possibleCodes[$row[0]])) {
				unset($possibleCodes[$row[0]]);
			}
		}

		// Are there any left? (I.e. are there possible shortcodes that are unused?)
		if (count($possibleCodes) == 0) {
			error('No more shortcodes available. This should not happen but we haven\'t implemented any limiting on usage yet, so if you read this that means we have work to do. Let me know at twitter.com/lucgommans', '503 Service Temporarily Unavailable', false);
		}

		// Take the first one
		foreach ($possibleCodes as $possibleCode=>$useless) {
			$code = $possibleCode;
			break;
		}

		$db->query("INSERT INTO shorts (`key`, `type`, `value`, `expires`, `secret`) VALUES('" . $code . "', -1, '', " . (time() + 180) . ", '" . $secret . "')") or die('Database error 15735'.$db->error);

		return $code;
	}

	function filter_chars($shortcode) {
		$shortcode = strtolower($shortcode);
		$chars = getAllowedCharset();
		for ($i = 0; $i < strlen($shortcode); $i++) {
			if (!in_array($shortcode[$i], $chars)) {
				return false;
			}
		}
		return $shortcode;
	}

	// Returns [dataAvailable, dataType, data]
	// Where dataAvailable = true when there is data, false when there is no data, or string "2" when there is an error
	// Where dataType = 1 for redirect, 2 for html to display or 3 for a file download
	function tryGet($shortcode, $checkExists = false) {
		global $db;

		$result = $db->query('SELECT `type`, `value`, `expireAfterDownload` FROM shorts WHERE `key` = "' . $db->escape_string($shortcode) . '" AND `value` != "" AND `expires` > ' . time()) or die('Database error 53418');
		if ($result->num_rows != 1) {
			if ($checkExists) {
				$result = $db->query('SELECT `value`, `expireAfterDownload` FROM shorts WHERE `key` = "' . $db->escape_string($shortcode) . '" AND `expires` > ' . time()) or die('Database error 7150183');
				if ($result->num_rows != 1) {
					return array("2", 2, 'This link does not exist! Perhaps you would like to <a href="./">shorten your own?</a>', false);
				}
			}
			return array(false, null, null, false);
		}

		$result = $result->fetch_row();
		switch ($result[0]) {
			case 0:
				return array(true, 1, $result[1], $result[2]);

			case 1:
				$data = $db->query('SELECT `data` FROM pastes WHERE `secret` = "' . $result[1] . '"') or die('Database error 192483');
				if ($data->num_rows == 0) {
					die("Error 165929");
				}
				else {
					$data = $data->fetch_row();
				}
				return array(true, 2, '<meta charset="utf-8"/><body style="margin:0"><textarea style="border:0; padding:9px; width:100%; height:99%">' . htmlspecialchars($data[0]) . '</textarea></body>', $result[2]);

			case 2:
				if (strpos(substr(getcwd(), strlen(getcwd()) - 4), 'api') === false) {
					$dir = 'uploads/';
				}
				else {
					$dir = '../uploads/';
				}
				$metadata = unserialize($result[1]);
				$ext = strtolower(pathinfo($metadata['original_filename'], PATHINFO_EXTENSION));
				if ($ext == 'jpg' || $ext == 'png' || $ext == 'bmp'|| $ext == 'gif') {
					return array(true, 3, array($ext, $dir . $metadata['filename'], $metadata['original_filename']), $result[2]);
				}
				else {
					return array(true, 3, array('file', $dir . $metadata['filename'], $metadata['original_filename']), $result[2]);
				}

			default:
				return array('2', 2, 'There is something funny about this link of yours', $result[2]);
		}
	}

	function clearUrl($secret) {
		global $db;
		$result = $db->query("SELECT `value` FROM `shorts` WHERE `type` = 2 AND `secret` = '" . $db->escape_string($secret) . "'");
		if ($result->num_rows > 0) {
			$row = $result->fetch_row();
			$data = unserialize($row[0]);
			if (strpos(substr(getcwd(), strlen(getcwd()) - 4), 'api') === false) {
				$dir = 'uploads/';
			}
			else {
				$dir = '../uploads/';
			}
			unlink($dir . $data['filename']);
		}
		$db->query('UPDATE `shorts` SET `type` = -1, `value` = "", `expires` = ' . (time() + 300) . ' WHERE `secret` = "' . $db->escape_string($secret) . '"') or die("Database error 83293");
		return true;
	}

	function getSecretByCode($code) {
		global $db;
		$result = $db->query('SELECT `secret` FROM `shorts` WHERE `key` = "' . $db->escape_string($code) . '"') or die('Database error 2957');
		if ($result->num_rows != 1) {
			return false;
		}
		$secret = $result->fetch_row()[0];
		return $secret;
	}

	function api_set($secret = false, $data = false, $expireAfterDownload = false) {
		global $db;
		if ($secret === false) {
			if (!isset($_GET['secret'])) {
				error("No secret included in request.");
			}
			$secret = $_GET['secret'];
		}

		if ($expireAfterDownload === false) {
			$expireAfterDownload = '0';
			if (isset($_GET['expireAfterDownload']) && $_GET['expireAfterDownload'] == 'true') {
				$expireAfterDownload = '1';
			}
		}

		if ($data === false) {
			$data = $_POST['val'];
		}

		if (empty($data)) {
			if (clearUrl($secret)) {
				die('1');
			}
			error('Something odd happened', '500 Internal Server Error');
		}

		$host = parse_url($data, PHP_URL_HOST);
		if ($host === false || empty($host) || strlen($data) > 25000 || strpos($data, "\n") !== false || strpos($data, " http") !== false) {
			$db->query('DELETE FROM pastes WHERE `secret` = "' . $secret . '"') or die('Database error 62871');
			$db->query('INSERT INTO pastes VALUES("' . $db->escape_string($data) . '", "' . $db->escape_string($secret) . '")') or die('Database error 518543');
			$data = substr($secret, 0, 40);
			$type = "1";
		}
		else {
			$type = "0";
		}

		$db->query('UPDATE shorts '
			. 'SET `value` = "' . $db->escape_string($data) . '", '
				. '`expires` = ' . (time() + (3600 * 12)) . ', '
				. '`type` = "' . $type . '", '
				. '`expireAfterDownload` = ' . $expireAfterDownload . ' '
			. 'WHERE secret = "' . $db->escape_string($secret) . '"')
			or die("Database error 28943");

		die('1');
	}

