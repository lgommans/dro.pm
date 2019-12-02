<?php 
	$db = @new mysqli('p:localhost', 'dropm', password_changeme, 'dropm');
	if ($db->connect_error)
		die('Database connection error');

	$uploaddir = 'uploads';

