<?php 
	function allocate() {
		$secret = bin2hex(openssl_random_pseudo_bytes(20));
		$code = getNewCode($secret);
		return [$secret, $code];
	}

	function getState() {
		return;
		global $state;
		$state = false;
		$i = 0;
		
		do {
			$state = @file_get_contents("state.cache");
			if ($state) {
				$state = unserialize($state);
			}
		} while (!$state && $i++ < 10 && sleep(rand(1,10)/10));
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
		global $db, $state;
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
		
		$chars = getAllowedShorts();

		$result = $db->query("SELECT `key` FROM shorts") or die('Database error 2539');
		$tried = array();
		$used = false;
		while ($row = $result->fetch_row()) {
			foreach ($chars as $char) {
				if ($char == $row[0]) {
					$used = true;
					break;
				}
			}
			if (!$used) {
				$chosen = $char;
				break;
			}
			else {
				$tried[$char] = true;
			}
		}
		foreach ($chars as $char) {
			if (!isset($tried[$char])) {
				$used = false;
				$chosen = $char;
				break;
			}
		}
		if ($used == true) {
			error('No more shortcodes available. This should not happen but we haven\'t implemented any limiting on usage yet, so if you read this that means we have work to do. Let me know at twitter.com/lucb1e or lucb1e.com/email-address', '503 Service Temporarily Unavailable', false);
		}

		$db->query("INSERT INTO shorts VALUES('" . $char . "', -1, '', " . (time() + 180) . ", '" . $secret . "')") or die('Database error 15735');
		
		return $chosen;
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
		$shortcode = filter_chars($shortcode);
		if ($shortcode === false) {
			return array(false, null, null);
		}
		
		$result = $db->query('SELECT `type`, `value` FROM shorts WHERE `key` = "' . $shortcode . '" AND `value` != "" AND `expires` > ' . time()) or die('Database error 53418');
		if ($result->num_rows != 1) {
			if ($checkExists) {
				$result = $db->query('SELECT `value` FROM shorts WHERE `key` = "' . $shortcode . '" AND `expires` > ' . time()) or die('Database error 7150183');
				if ($result->num_rows != 1) {
					return array("2", 2, 'This link does not exist! Perhaps you would like to <a href="./">shorten your own?</a>');
				}
			}
			return array(false, null, null);
		}
		
		$result = $result->fetch_row();
		switch ($result[0]) {
			case 0:
				return array(true, 1, $result[1]);
			
			case 1:
				$data = $db->query('SELECT `data` FROM pastes WHERE `secret` = "' . $result[1] . '"') or die('Database error 192483');
				if ($data->num_rows == 0) {
					die("Error 165929");
				}
				else {
					$data = $data->fetch_row();
				}
				return array(true, 2, '<body style="margin:0"><textarea style="border:0; padding:9px; width:100%; height:99%">' . htmlspecialchars($data[0]) . '</textarea></body>');
			
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
					return array(true, 3, array($ext, $dir . $metadata['filename'], $metadata['original_filename']));
				}
				else {
					return array(true, 3, array('file', $dir . $metadata['filename'], $metadata['original_filename']));
				}
			
			default:
				return array('2', 2, 'There is something funny about this link of yours');
		}
	}
	
	function clearUrl($secret) {
		global $db;
		$result = $db->query("SELECT `value` FROM `shorts` WHERE `type` = 2 AND `secret` = '" . $secret . "'");
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
		$db->query('UPDATE `shorts` SET `type` = -1, `value` = "" WHERE `secret` = "' . $secret . '"') or die("Database error 83293");
		return true;
	}
