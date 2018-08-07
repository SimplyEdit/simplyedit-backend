<?php
	require_once('config.php');
	$templateDir = '/templates/';

	$request     = http::request();
	if ($request['method']=='PUT' || $request['method']=='DELETE') {
		// some apache configurations use rewrite rules which map the request method to
		// GET. The original request method is stored in (REDIRECT_)+REQUEST_METHOD,
		// which the http::request() method correctly parses, but the .htaccess
		// file doesn't. It will send these requests to the router instead of the store
		// This check fixes that.
		include('store.php');
		die();
	}

	http::format('html'); // set output format for errors to html instead of json


	try {
		$data = json_decode( filesystem::get('/data/','data.json'), true );
		if($data === null ){
			$data = [];
		}
	} catch ( Exception $e ) {
		$data = [];
	}

	$path   = $request['target'];
	$status = 200;

	if( !isset($data[$path]) && isset($data[$path.'/']) ) {
		header('Location: '.$path.'/');
		http::response(301);
		die();
	} else if (!isset($data[$path])) {
		$path = '/404.html';
		if (!isset($data[$path])) {
			$path = '/404.html/';
		}
		$status = 404;
	}

	if ( isset($data[$path]) ) {
		$template = "index.html";

		if( isset($data[$path]['data-simply-page-template'])) {
			$pageTemplate = $data[$path]['data-simply-page-template'];
			if (preg_match("/\.html$/", $pageTemplate) && filesystem::exists($templateDir . $pageTemplate)) {
				$template = $pageTemplate;
			} else if (!preg_match("/\.html$/", $pageTemplate)) {
				echo '<!-- page template '.htmlspecialchars($pageTemplate).' skipped since it doesnt have the .html suffix -->';
			} else if ( !filesystem::exists($templateDir) ) {
				echo '<!-- template dir '.$templateDir.' not found -->';
			} else {
				echo '<!-- page template '.htmlspecialchars($pageTemplate).' not found in '.$templateDir.' -->';
			}
		}

		http::response($status);
		$contents = filesystem::get($templateDir, $template);
		$contents = preg_replace('/<html/i','<html data-simply-path="'.$path.'"', $contents);
		echo $contents;
	} else {
		http::response(404);
		echo '
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>404 Not Found</title>
</head>
<body>
	<h1>Page not found (error: 404)</h1>
</body>
</html>
';
	}
