<?php 
	// Returns an array with either:
	// if api version 1: [string secret, string code]
	// if api version 2: [bool already_exists, string secret, string code]
	function allocate($code = false, $ApiVersion = 1) {
		global $db;

		if (strlen($code) > 250) {
			header('HTTP/1.1 400 Bad Request');
			die('Requested short link is too long (the maximum is 250 characters)');
		}

		$secret = substr(hash('sha256', openssl_random_pseudo_bytes(20)), 0, 40);
		if (empty($code)) {
			$code = getNewCode($secret);
		}
		else {
			$exists = codeExists($code);
			if ($exists === 'semi') {
				clearUrl(getSecretByCode($code));
				$db->query('DELETE FROM shorts WHERE `key` = "' . $db->escape_string($code) . '"') or die('Database error 95184');
			}
			if ($exists === true) {
				return [true, $code];
			}
			else {
				// TODO why did this error trigger when pasting a custom link
				$db->query("INSERT INTO shorts (`key`, `type`, `value`, `expires`, `secret`) VALUES('" . $db->escape_string($code) . "', -1, '', " . (time() + 900) . ", '" . $secret . "')") or die('Database error 81935: ' . $db->error);
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
					return 'semi';
				}
			}
			return true;
		}
		return false;
	}

	function getAllowedCharset() {
		return ['a','b','c','d','e','f','g','h','j','k','m','n','p','q','r','s','t','u','v','w','x','y','z','2','3','4','5','6','7','8','9']; // upon modifying the code below, be sure to update this value ;)

		$allowedCharSet = 'a-hj-km-np-z2-9';
		$chars = array();
		for ($i = 0; $i < strlen($allowedCharSet); $i += 3) {
			for ($j = ord($allowedCharSet[$i]); $j <= ord($allowedCharSet[$i + 2]); $j++) {
				$chars[] = chr($j);
			}
		}
		return $chars;
	}

	function getAllowedShorts() {
		$chars = getAllowedCharset();
		$shorts = [];
		foreach ($chars as $char1) {
			yield $char1;
		}
		foreach ($chars as $char1) {
			foreach ($chars as $char2) {
				yield $char1 . $char2;
			}
		}
		foreach ($chars as $char1) {
			foreach ($chars as $char2) {
				foreach ($chars as $char3) {
					yield $char1 . $char2 . $char3;
				}
			}
		}
		yield false;
	}

	function cleanup() {
		global $db, $uploaddir;

		$result = $db->query("SELECT `value` FROM `shorts` WHERE `type` = 2 AND `expires` < " . time());
		if ($result->num_rows > 0) {
			while ($row = $result->fetch_row()) {
				$data = json_decode($row[0], true);
				if (strpos(substr(getcwd(), strlen(getcwd()) - 4), '/api') === false) {
					$dir = $uploaddir . '/';
				}
				else {
					$dir = "../$uploaddir/";
				}
				@unlink($dir . $data['filename']);
			}
		}
		$db->query("DELETE FROM shorts WHERE `expires` < " . time());
		if (rand(1, 10) == 5) {
			$db->query("DELETE FROM pastes WHERE NOT EXISTS (SELECT secret FROM shorts WHERE shorts.`secret` = pastes.`secret`)");
		}
	}

	function getNewCode($secret) {
		global $db;

		cleanup();

		// Get all used shortcodes
		$results = [];
		$result = $db->query("SELECT `key` FROM shorts") or die('Database error 2539');
		while ($row = $result->fetch_row()) {
			$results[$row[0]] = true;
		}

		// Find the first one that's free
		$generator = getAllowedShorts();
		foreach ($generator as $code) {
			if (!isset($results[$code])) break;
		}

		if ($code === false) {
			error('No more short links available. This should not happen but we haven\'t implemented any limiting on usage yet, so if you read this that means we have work to do. Let me know at https://github.com/lgommans/dro.pm/issues', '503 Service Temporarily Unavailable', false);
		}

		$result = false;
		$i = 0;
		while (!$result && $i++ < 10) {
			if ($code === false || $generator->valid() === false) {
				error('No more short links available. This should not happen but we haven\'t implemented any limiting on usage yet, so if you read this that means we have work to do. Let me know at https://github.com/lgommans/dro.pm/issues', '503 Service Temporarily Unavailable', false);
			}
			$result = @$db->query("INSERT INTO shorts (`key`, `type`, `value`, `expires`, `secret`) VALUES('" . $code . "', -1, '', " . (time() + 180) . ", '" . $secret . "')");
			if (!$result) { // duplicate key, most likely
				foreach (range(0, mt_rand(1, 5)) as $useless) {
					$generator->next();
				}
				$code = $generator->current();
			}
		}

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

	// Returns [dataAvailable, dataType, data, expireAfterDownload]
	// Where dataAvailable = true when there is data, false when there is no data, or string "2" when there is an error
	// Where dataType = 0 for nonexistent link, 1 for redirect, 2 for html to display, 3 for a file download
	// The remaining parameters are optional (data, expireAfterDownload) and have changing types (data), depending on the type
	function tryGet($shortcode, $checkExists = false) {
		global $db, $uploaddir;

		$result = $db->query('SELECT `type`, `value`, `expireAfterDownload`, `expires` FROM shorts WHERE `key` = "' . $db->escape_string($shortcode) . '" AND `value` != "" AND `expires` > ' . time()) or die('Database error 53418');
		if ($result->num_rows != 1) {
			$expires = null;
			if ($checkExists) {
				$result = $db->query('SELECT `value`, `expireAfterDownload`, `expires` FROM shorts WHERE `key` = "' . $db->escape_string($shortcode) . '" AND `expires` > ' . time()) or die('Database error 7150183');
				if ($result->num_rows != 1) {
					return array('2', 0);
				}
				else {
					$result = $result->fetch_row();
					$expires = $result[2];
				}
			}
			return array(false, null, null, false, $expires);
		}

		$result = $result->fetch_row();
		switch ($result[0]) {
			case 0:
				return array(true, 1, $result[1], $result[2], $result[3]);

			case 1:
				$data = $db->query('SELECT `data` FROM pastes WHERE `secret` = "' . $result[1] . '"') or die('Database error 192483');
				if ($data->num_rows == 0) {
					die("Error 165929");
				}
				else {
					$data = $data->fetch_row();
				}
				header('Content-Type: text/plain; charset=utf-8');
				return array(true, 2, $data[0], $result[2], $result[3]);

			case 2:
				if (strpos(substr(getcwd(), strlen(getcwd()) - 4), 'api') === false) {
					$dir = $uploaddir . '/';
				}
				else {
					$dir = "../$uploaddir/";
				}
				$metadata = json_decode($result[1], true);

				return array(true, 3, array($dir . $metadata['filename'], $metadata['original_filename']), $result[2], $result[3]);

			default:
				return array('2', 2, 'There is something funny about this link of yours', $result[2], $result[3]);
		}
	}

	function clearUrl($secret) {
		global $db;

		clearFile($secret);

		$db->query('UPDATE `shorts` SET `type` = -1, `value` = "", `expires` = ' . (time() + 600) . ' WHERE `secret` = "' . $db->escape_string($secret) . '"') or die("Database error 83293");
	}

	function clearFile($secret) {
		global $db, $uploaddir;

		$result = $db->query("SELECT `value` FROM `shorts` WHERE `type` = 2 AND `secret` = '" . $db->escape_string($secret) . "'");
		if ($result->num_rows > 0) {
			$row = $result->fetch_row();
			$data = json_decode($row[0], true);
			if (strpos(substr(getcwd(), strlen(getcwd()) - 4), '/api') === false) {
				$dir = $uploaddir . '/';
			}
			else {
				$dir = "../$uploaddir/";
			}
			unlink($dir . $data['filename']);
		}
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

	function move($oldsecret, $newsecret) {
		// Note: this is totally raceable but the person calling this has both secrets. If they want inconsistent states on their own links, whatever.
		global $db;

		if ($oldsecret == $newsecret) {
			error('This violates the definition of moving.');
		}

		$oldsecret = $db->escape_string($oldsecret);
		$newsecret = $db->escape_string($newsecret);

		$result = $db->query('SELECT `type` FROM `shorts` WHERE `secret` = "' . $oldsecret . '"') or die('Database error 585193');
		if ($result->num_rows != 1) {
			error('Old secret not found');
		}

		$type = $result->fetch_row()[0];
		if ($type == -1) {
			error("Can't move an empty link");
		}

		$result = $db->query('SELECT `type` FROM `shorts` WHERE `secret` = "' . $newsecret . '"') or die('Database error 995193');
		if ($result->num_rows != 1) {
			error('New secret not found');
		}

		if ($result->fetch_row()[0] != -1) {
			error('New secret already filled');
		}

		$db->query("UPDATE `shorts` SET
			`type` = (SELECT `type` FROM `shorts` WHERE `secret` = '$oldsecret'),
			`value` = (SELECT `value` FROM `shorts` WHERE `secret` = '$oldsecret'),
			`expireAfterDownload` = (SELECT `expireAfterDownload` FROM `shorts` WHERE `secret` = '$oldsecret')
			WHERE `secret` = '$newsecret'") or die('Database error 131324');
		$db->query("UPDATE `shorts` SET `type` = -1, value = '' WHERE `secret` = '$oldsecret'") or die('Database error 173754');
		$db->query("UPDATE `pastes` SET `secret` = '$newsecret' WHERE `secret` = '$oldsecret'") or die('Database error 848299'.$db->error);
	}

	function setExpireAfterDownload($secret, $expireAfterDownload) {
		global $db;

		if ($expireAfterDownload != '0' && $expireAfterDownload != '1') {
			error('Invalid internal call', '500 Internal Server Error');
		}

		$db->query("UPDATE `shorts` SET expireAfterDownload = $expireAfterDownload WHERE secret = '" . $db->escape_string($secret) . "'") or die('Database error 848493');
	}

	function api_set($secret = false, $data = false, $expireAfterDownload = false, $noecho = false) {
		global $db;
		if ($secret === false) {
			$secret = $_GET['secret'];
			if (!isset($secret) || strlen($secret) != 40) {
				error('No secret included in request or token not 40 characters.');
			}
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
			clearUrl($secret);
			if ($noecho) {
				return;
			}
			die('1');
		}

		clearFile($secret);

		// - arbitrary limit to prevent resource exhaustion by extra URL checking we do
		// - The 'file' protocol, I'd love to support if any browsers did. They don't prompt to execute the redirect or something, nor do they show what it refused to redirect you to; no, they just break the page completely (you'd need to know that you can get your data using the dro.pm/o/view option), so this is entirely useless to users and we need to fix that browser limitation. (Ironically, some browsers suggest to "contact the website owner to fix this problem")
		if (strlen($data) > 10e6 || substr($data, 0, 5) == 'file:') {
			$validurl = false;
		}
		else {
			// Instead of testing only for www., we could also just try prepending a protocol and see if it validates. Not doing that right now because it might give too many false positives.
			// Better unintended viewing of a URL than unintended redirection to something which they might have meant to be for viewing.
			if (substr($data, 0, 4) === 'www.') { // seems to have wanted to post a link but missing protocol, let's prepend one and retry...
				// Might want to use http:// because it works more universally but I think that should be rare enough we can prioritize security here
				$validurl = filter_var('https://' . trim($data), FILTER_VALIDATE_URL);
				if ($validurl !== false) {
					$data = $validurl;
				}
			}
			else {
				// trim() because people like to (unnecessarily) press enter to submit out of habit
				$validurl = filter_var(trim($data), FILTER_VALIDATE_URL);
				if ($validurl !== false) {
					$data = $validurl; // might have been trimmed or otherwise filtered by this function
				}
			}
		}

		if ($validurl === false || empty($validurl)) {
			// Doesn't parse as a URL, so it's a paste!
			$db->query('INSERT INTO pastes VALUES("' . $db->escape_string($data) . '", "' . $db->escape_string($secret) . '")
				ON DUPLICATE KEY UPDATE data = "' . $db->escape_string($data) . '"') or die('Database error 62872');
			$data = $secret;
			$type = '1';
		}
		else {
			$type = '0';
		}

		$db->query('UPDATE shorts '
			. 'SET `value` = "' . $db->escape_string($data) . '", '
				. '`expires` = ' . (time() + (3600 * 18)) . ', '
				. '`type` = "' . $type . '", '
				. '`expireAfterDownload` = ' . $expireAfterDownload . ' '
			. 'WHERE secret = "' . $db->escape_string($secret) . '"')
			or die("Database error 28943");

		if ($db->affected_rows !== 1) {
			// Either the secret doesn't exist, or the data was already up-to-date. Let's check that to not return an inappropriate error...
			$result = $db->query('SELECT expires FROM shorts WHERE secret = "' . $db->escape_string($secret) . '"') or die('Database error 77314');
			if ($result->num_rows !== 1) {
				die('secret not found');
			}
		}

		if ($noecho) {
			return;
		}
		die('1');
	}

