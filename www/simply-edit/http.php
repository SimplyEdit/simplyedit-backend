<?php

class http {

	private static $format = 'json';

	private static function sanitizeTarget($target)
	{
		$target = rawurldecode($target);

		// convert \ to /
		$target = str_replace('\\','/',$target);

		// Only allow A-Z, 0-9, .-_/
		$target = preg_replace('/[^A-Za-z\.\/0-9_-]/', '-', $target);

		// Remove any double periods
		$target = preg_replace('{(^|\/)[\.]{1,2}\/}', '/', $target);

		$target = preg_replace('@^/@', '', $target);

		return $target;
	}

	public static function format($format)
	{
		self::$format = $format;
	}

	private static function getHeader($list, $redirectLevel=0)
	{
		$redirect = 'REDIRECT_';
		if (!is_array($list)) {
			$list = [ $list => false ];
		}
		foreach ( $list as $header => $extraInfo ) {
			for ($i=$redirectLevel; $i>=0; $i--) {
				$check = str_repeat($redirect, $i).$header;
				if ( isset($_SERVER[$check]) ) {
					return [$header, $_SERVER[$check]];
				}
			}
		}
		return [false, ''];
	}

	private static function parseAuthUser($auth) {
		return explode(':',base64_decode(substr($auth, 6)));
	}

	public static function getUser()
	{
		$checks = [ 
			'PHP_AUTH_USER'               => false, 
			'REMOTE_USER'                 => false, 
			'HTTP_AUTHORIZATION'          => [self,parseAuthUser],
		];
		list($header, $headerValue) = self::getHeader($checks, 3);
		if (is_array($checks[$header])) {
			$headerValue = call_user_func($checks[$header], $headerValue)[0];
		}
		return $headerValue;
	}

	public static function getPassword()
	{
		$checks = [ 
			'PHP_AUTH_PW'                 => false, 
			'HTTP_AUTHORIZATION'          => [self,parseAuthUser],
		];
		list($header, $headerValue) = self::getHeader($checks, 3);
		if (is_array($checks[$header])) {
			$headerValue = call_user_func($checks[$header], $headerValue)[1];
		}
		return $headerValue;
	}

	public static function getMethod()
	{
		list($header, $headerValue) = self::getHeader('REQUEST_METHOD',3);
		if ($headerValue==='POST') {
			if ($_GET['_method']=='PUT'||$_GET['_method']=='DELETE') {
				$headerValue = $_GET['_method'];
			}
		}
		return $headerValue;
	}

	public static function request()
	{
		$target = preg_replace('@\?.*$@','',$_SERVER["REQUEST_URI"]);
		$target = self::sanitizeTarget($target);

		preg_match('@(?<dirname>.+/)?(?<filename>[^/]*)@',$target,$matches);

		$filename = isset($matches['filename']) ? $matches['filename'] : '';
		$dirname  = ( isset($matches['dirname']) ? filesystem::path($matches['dirname']) : '/');
		$docroot  = $_SERVER['DOCUMENT_ROOT'];
		$subdir   = filesystem::path( substr( dirname(dirname($_SERVER['SCRIPT_FILENAME'])), strlen($docroot) ) );
		$dirname  = filesystem::path( substr($dirname, strlen($subdir) ) );
		$request = [
			'protocol'  => $_SERVER['SERVER_PROTOCOL']?:'HTTP/1.1',
			'method'    => self::getMethod(),
			'target'    => '/'.$target,
			'directory' => $dirname,
			'filename'  => $filename,
			'user'      => self::getUser(),
			'password'  => self::getPassword(),
			'docroot'   => $docroot
		];
		return $request;
	}

	public static function response($status, $data='')
	{
		http_response_code($status);
		switch(self::$format) {
			case 'html':
				echo $data;
			break;
			case 'json':
			default:
				echo json_encode($data, JSON_UNESCAPED_UNICODE);
			break;
		}
	}

}
