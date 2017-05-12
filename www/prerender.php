<?php
	require_once('config.php');
    $prerenderToken = $settings->prerender_token;
    
    $baseUrl = "http://service.prerender.io/";
    $protocolScheme = "http://";
    
	$targetUrl = $baseUrl . $protocolScheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    // Create a stream
    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>"X-Prerender-Token: " . $prerenderToken . "\r\n"
      )
    );

    $context = stream_context_create($opts);

   // Open the file using the HTTP headers set above
   $file = file_get_contents($targetUrl, false, $context);
   echo $file;
?>