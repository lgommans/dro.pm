<?php 
	if (empty($_POST['bug'])) {
		die("No bug?");
	}

	require('api/dbconn.php'); // for loading BUGEMAILADDR

	$ok = mail(BUGEMAILADDR, 'Dro.pm bug', $_SERVER['REMOTE_ADDR'] . ' - ' . date('r') . ': ' . $_POST['bug'], 'From: ' . BUGEMAILADDR);
	if (!$ok) {
		die("Sending bug failed. If you are thinking 'wtf' right now, I totally agree. Wtf. Please let me know on twitter.com/lucgommans !");
	}

	die("Bug submitted!");

