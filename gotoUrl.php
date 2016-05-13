<?php 
require('api/dbconn.php');
require('api/functions.php');

list($status, $type, $data, $expireAfterDownload) = tryGet($_GET['shortcode'], true);
if ($status !== false) {
	if ($type == 1) {
		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.");
		}

		header("Location: " . $data);
		exit;
	}
	else if ($type == 2) {
		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.");
		}

		die($data);
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
<i>What is going on?</i><br>
You have visited a link that is not (yet?) available. We are waiting for it to be updated.<br>
So far this is taking <span id='secs'>0</span> seconds. You should close the page if it takes too long.<br>

<script>
	t = 300;
	
	setInterval(function() {
		document.getElementById("secs").innerHTML++;
	}, 1000);
	
	function checkForUpdate() {
		aGET('api/v1/check/<?php echo $shortcode; ?>', function(data) {
			if (data == '1') {
				location.reload();
			}
			else {
				t = Math.min(1750, t + 50);
				setTimeout(checkForUpdate, t);
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

<br>
<br>
While you are waiting, would you perhaps enjoy a game of Escapa?<br>
<br>
<iframe src='//lucb1e.com/rp/randomupload/escapa.html' width="475" height="475" border="0" frameborder="no"></iframe>

