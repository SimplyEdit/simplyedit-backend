<?php
	require_once('config.php');

	$statusCodes = [
		1   => 412,
		2   => 403,
		3   => 403,
		102 => 412,	// precondition failed
		103 => 412,
		104 => 412,
		105 => 404,	// not found
		106 => 403, // access denied
		107 => 403,
		108 => 403,
		109 => 412,
		110 => 403
	];

	$request = http::request();

	$result = [];
	$status = 200;

	try {
		htpasswd::load('/data/.htpasswd');
	} catch(\Exception $e) {
		// if no htpasswd is set, accept anyone, should only happen during install
	}
	try {
		$user     = $request['user'];
		$password = $request['password'];
		if ( $_COOKIE['simply-logout'] 
			|| (count(htpasswd::$users) && (!$user || !$password || !htpasswd::check($user, $password))) 
		) {
			setcookie('simply-logout','',1,'/'); // remove the 'i logged off' cookie
			header('WWW-Authenticate: Basic realm="Simply Store"');
			$status = 401;
			$result = ['error' => 405, 'message' => 'Access denied'];
		} else if ( $request['method']=='PUT' ) {
			if ($request['directory']=='/data/' && $request['filename']=='htpasswd') {
				$request['filename'] = '.htpasswd';
			}
			if ( $request['directory']=='/data/' && $request['filename']=='data.json') {
				filesystem::copy('/data/','data.json','/data/','data.'.strftime('%Y-%m-%d-%H').'.json');
				// check number of backups vs max_backups
				$list = glob('../data/data.*.json');
				if ( count($list) > $settings->max_backups ) {
					// clean up old backups
					sort($list);
					while (count($list) > $settings->max_backups) {
						$remove = basename(array_shift($list));
						filesystem::delete('/data/',$remove);
					}
				}
			}
			$result = filesystem::put($request['directory'], $request['filename']);
		} else if ( $request['method']=='DELETE' ) {
			$result = filesystem::delete($request['directory'], $request['filename']);
		} else {
			$status = 405; //Method not allowed
			// why?
			$explain = '';
			if ($request['method']!='PUT' && $request['method']!='DELETE') {
				$explain .= 'Method "'.$request['method'].'" is unknown. ';
			}
			$result = [ 'error' => 405, 'message' => 'Method not allowed. '.$explain ];
		}
	} catch( \Exception $e ) {
		$code = $e->getCode();
		if ( isset($statusCodes[$code]) ) {
			$status = $statusCodes[$code];
		} else {
			$status = 500; // internal error
		}
		$result = [ 'error' => $code, 'message' => $e->getMessage() ];
	}

	http::response( $status, $result );
