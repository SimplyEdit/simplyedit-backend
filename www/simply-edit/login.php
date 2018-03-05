<?php
	require_once('config.php');

	$request = http::request();

	$result = [];
	$status = 200;

	htpasswd::load('/data/.htpasswd');
	$user     = $request['user'];
	$password = $request['password'];
	if ( $_COOKIE['simply-logout'] || !$user || !$password || !htpasswd::check($user, $password)) {
		setcookie('simply-logout','',1,'/'); // remove the 'i logged off' cookie
		header('WWW-Authenticate: Basic realm="Simply Store"');
		$status = 401;
		$result = ['error' => 405, 'message' => 'Access denied'];
	} else {
		$status = 200;
		$result = 'OK';
	}

	http::response( $status, $result );
