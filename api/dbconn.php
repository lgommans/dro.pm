<?php 
	$db = @new mysqli('p:localhost', 'dropm', password_changeme, 'dropm');
	if ($db->connect_error)
		die('Database connection error');
	
	define('LENGTH_SHORT', 1);
	define('LENGTH_MEDIUM', 2);
	define('LENGTH_LONG', 3);
	
