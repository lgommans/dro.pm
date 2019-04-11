<?php 
require('api/dbconn.php');
require('api/functions.php');

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

list($status, $type, $data, $expireAfterDownload) = tryGet($_GET['shortcode'], true);
if ($status !== false) {
	if ($_SERVER['HTTP_USER_AGENT'] === "TelegramBot (like TwitterBot)" && date('m-d') == '04-01') {
		// Warning: NSFW
		header("Location: https://p.im9.eu/img-2806.jpg");
		exit;
	}
	else if ($type == 1) {
		header('HTTP/1.1 307 Temporary Redirect');
		header('Location: ' . $data);

		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.");
		}
		exit;
	}
	else if ($type == 2) {
		echo $data;

		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.");
		}

		exit;
	}
	else {
		if ($data[0] != 'file') {
			header('Content-type: image/' . $data[0]);
		}
		else {
			header('Content-type: application/octet-stream');
			header('Content-disposition: attachment; filename="' . $data[2] . '"');
		}
		header('Content-Length: ' . filesize($data[1]));
		flush();
		readfile($data[1]);

		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.");
		}

		exit;
	}
}

?>
<br>
<h3><img src='res/img/loading.gif'/> Loading...</h3>
<br/>
You have visited a link that is not (yet?) available. Usually, the sender is still uploading the file. We are waiting for it to become available.<br>
<br>
<span id='lastcheck'></span>
<noscript><font color=red>JavaScript is disabled. Could not check for update.</font></noscript>

<script>
	t = 350;

	function checkForUpdate() {
		document.getElementById('lastcheck').innerText += ' Checking for update...';
		aGET('api/v1/check/<?php echo htmlspecialchars($_GET['shortcode']); ?>', function(data) {
			if (data == '1') {
				location.reload();
			}
			else {
				document.getElementById('lastcheck').innerText = 'Last check: ' + new Date().toLocaleTimeString() + '.';
				setTimeout(checkForUpdate, t *= 1.1);
			}
		});
	}
	
	function aGET(uri, callback) {
		var req = new XMLHttpRequest();
		req.open("GET", uri, true);
		req.send(null);
		req.onreadystatechange = function() {
			if (req.readyState == 4)
				callback(req.responseText);
		}
	}
	
	checkForUpdate();
</script>

