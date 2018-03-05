<?php
	error_reporting(E_ERROR & E_WARNING);
	ini_set('display_errors',1);

	require_once('http.php');
	require_once('filesystem.php');
	require_once('htpasswd.php');

	filesystem::basedir(dirname(__DIR__)); // parent of current directory

	filesystem::allow('/data/','application/json.*');
	filesystem::allow('/data/','text/.*');
	filesystem::allow('/img/','image/.*');
	
	filesystem::allow('/files/','.*');

	filesystem::check('put', '/data/data.json', function($filename, $realfile) {
		$contents = file_get_contents($realfile);
		$result   = json_decode($contents);
		if ( $result === null ) {
			throw new \Exception('File does not contain valid JSON',1);
		}
		return true;
	});

	filesystem::check('delete', '/data/data.json', function() {
		throw new \Exception('You cannot delete the data.json file',3);
	});

	filesystem::check('put', '/', function($filename, $realfile) {
		$disallowed = ['php','phtml','inc','phar','cgi'];
		$extension  = pathinfo($filename, PATHINFO_EXTENSION);
		if ( in_array($extension, $disallowed) ) {
			throw new \Exception('Extension '.$extension.' is disallowed', 2);
		}
	});

	$settings = json_decode(filesystem::get('/data/','settings.json'));
