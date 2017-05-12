<?php
	require_once('config.php');

	$templateDir = '/templates/';

	http::format('html'); // set output format for errors to html instead of json

	$request     = null;
	$request     = http::request();
	try {
		$data        = json_decode( filesystem::get('/data/','data.json'), true );
		if($data === null ){
			$data = [];
		}
	} catch ( Exception $e ) {
		$data = [];
	}

	$path        = $request['target'];
	$status      = 200;

	if( !isset($data[$path]) ) {
		$path   = '/404.html';
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
		filesystem::readfile($templateDir, $template);

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
