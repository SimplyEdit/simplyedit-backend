<?php
	/*
		TOD:
			users:
				- automatisch wijzigingen direct opslaan? - dan een undo/redo optie toevoegen
				- anders: onbeforeunload check op wijzigingen -> vragen om op te slaan
				  en save button een saving animatie geven
				- foutmelding als opslaan mislukt
	*/

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
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
	} catch( \Exception $e) {
		// ignore error, just use empty list
	}
	if (is_array($settings->managers)) {
		foreach ((array)$settings->managers as $index => $manager) {
			if (!htpasswd::$users[$manager]) {
				unset($settings->managers[$index]);
			}
		}
	} else {
		$settings->managers = [];
	}
	$user     = $request['user'];
	$password = $request['password'];
	if ( $_COOKIE['simply-logout'] 
		|| (count(htpasswd::$users) && (!$user || !$password || !htpasswd::check($user, $password))) 
		|| (count((array)$settings->managers) && !in_array($user,$settings->managers))
	) {
		setcookie('simply-logout','',1,'/'); // remove the 'i logged off' cookie
		header('HTTP/1.1 401 Forced Logout');
		header('WWW-Authenticate: Basic realm="Simply Store"');
		echo '<meta http-equiv="refresh" content="0; url=//'.$_SERVER['SERVER_NAME'].'/">';
		echo '<h1>401 Forced Logout</h1>';
		die();
	}

	if ( !filesystem::exists('/.htaccess') ) {
		$htaccess = <<<EOF
<Files settings.json>
	<Limit GET>
		Order Allow,Deny
		Deny from all
	</Limit>
</Files>
RewriteEngine on
<Limit POST PUT DELETE>
	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	RewriteCond %{REQUEST_METHOD} PUT
	RewriteRule .* simply-edit/store.php [L]
	RewriteCond %{REQUEST_METHOD} DELETE
	RewriteRule .* simply-edit/store.php [L]
	RewriteCond %{QUERY_STRING} _method=(PUT|DELETE)
	RewriteRule .* simply-edit/store.php [L]
</Limit>
<Limit GET POST>
	RewriteRule ^logout$ simply-edit/logout.php [L]
	#RewriteRule ^login$ simply-edit/login.php [L]
</Limit>
<Limit GET>
	RewriteCond %{HTTP_USER_AGENT} Lynx|w3m|googlebot|baiduspider|facebookexternalhit|twitterbot|rogerbot|linkedinbot|embedly|quora\ link\ preview|showyoubot|outbrain|pinterest|slackbot|vkShare|Validator [NC,OR]
	RewriteCond %{QUERY_STRING} _escaped_fragment_

	# Only proxy the request to Prerender if it's a request for HTML
	RewriteRule ^(?!.*?(\.js|\.css|\.xml|\.less|\.png|\.jpg|\.jpeg|\.gif|\.pdf|\.doc|\.txt|\.ico|\.rss|\.zip|\.mp3|\.rar|\.exe|\.wmv|\.doc|\.avi|\.ppt|\.mpg|\.mpeg|\.tif|\.wav|\.mov|\.psd|\.ai|\.xls|\.mp4|\.m4a|\.swf|\.dat|\.dmg|\.iso|\.flv|\.m4v|\.torrent|\.ttf|\.woff))(.*) simply-edit/prerender.php [L]
</Limit>

Options +Indexes

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule .* simply-edit/router.php [L]
EOF;
		filesystem::allow('/','.*');
		try {
			filesystem::write('/', '.htaccess', $htaccess);
		} catch(\Exception $e) {
			// visual check will display the error so ignore the exception
		}
	}

?><!doctype html>
<html>
<head>
	<title>SimplyEdit dashboard</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no">
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="style.css">
	<style>
		body {
			opacity: 0;
			transition: opacity 1s ease;
		}
		body.simply-loaded {
			opacity: 1;
		}
	</style>
	<style>
#svg, #svg * {
	font-size: 20px;
}
#svg circle {
  stroke-dashoffset: 0;
  transition: stroke-dashoffset 1s linear;
  stroke: #666;
  stroke-width: 1em;
}
#svg #bar {
	stroke: #7CE54C; /*#FF9F1E;*/
	r: 90px;
	cx: 100px;
	cy: 100px;
	fill: rgba(0,0,0,0);
	stroke-dasharray: 565.48px;
	stroke-dashoffset: 565.48px;
}
#cont {
  display: block;
  height: 200px;
  width: 200px;
  margin: 30px 25px 20px 20px;
  box-shadow: 0 0 10px #888;
  border-radius: 100%;
  position: relative;
  float: left;
}
#cont:after {
  position: absolute;
  display: block;
  height: 160px;
  width: 160px;
  left: 50%;
  top: 50%;
  box-shadow: inset 0 0 10px #888;
  content: attr(data-pct)"%";
  margin-top: -80px;
  margin-left: -80px;
  border-radius: 100%;
  line-height: 160px;
  font-size: 2em;
  text-shadow: 1px 1px 2px #AAA;
  text-align: center;
  background-color: white;
  color: #666;
}
#errors div {
	word-wrap: break-word;
	overflow: hidden;
	font-size: 13px;
	line-height: 1.3em;
	font-family: monospace;
}
#cont[data-pct="100"] #bar {
	stroke: #33db6b; /*#00eb4e;*/
}
#installCheck {
	padding: 0;
	overflow: hidden;
}
#errors {
	height: 100%;
	overflow: auto;
	width: calc(100% - 245px);
	padding: 5px 5px 5px 0;
	box-sizing: border-box;
}
@media screen and (max-width: 768px) {
	#cont {
		margin: 30px auto;
		float: none;
	}
	#errors {
		clear: both;
		height: auto;
		width: auto;
	}
}
</style>
<style>
	/* extensions that will be merged into mu.css later */
	@import url('https://fonts.googleapis.com/css?family=Varela+Round');
	#simplyedit.mu-app .mu-dialog>section {
		padding: 6px;
	}
	#simplyedit.mu-app .mu-file .mu-button {
		height: 100%;
		white-space: normal;
		display: flex;
		flex-direction: column;
		align-items: center;
		border-bottom: 2px solid white;
		overflow: hidden;
	}
	#simplyedit.mu-app .mu-file .mu-button:after {
	    content: "";
	    display: block;
	    position: absolute;
	    bottom: 0px;
	    height: 10px;
	    z-index: 10;
	    width: 100%;
	    background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,1));
	}
	#simplyedit.mu-app .mu-file .mu-button i {
		font-size: 3em;
		line-height: 110%;
		margin-top: 1rem;
	}
	.mu-app [data-mu-command="simply-users-delete"],
	.mu-app [data-mu-command="simply-backups-delete"] {
		display: none;
	}
</style>
<style>
	#simplyedit.mu-app .mu-dialog > footer .mu-toolbar {
		border: 0;
		background: linear-gradient(0deg,#fff 0,#fff 95%,#ddd);
	}
    #simplyedit.mu-app .mu-fullscreen {
		margin-left: 0;
		margin-top: 0;
	}

	#simplyedit.mu-app .mu-file-list,
	#simplyedit.mu-app .mu-file {
		list-style: none;
		margin: 0;
		padding: 0;
	}
	#simplyedit.mu-app .mu-file {
		position: relative;
	    background-color: white;
	    width: 100px;
	    height: 100px;
		min-height: 100px;
	    border-radius: 3px;
	    text-align: center;
	    overflow: hidden;
	    min-width: 100px;
	    margin: 7px;
	    display: flex;
	    justify-content: center;
	    flex-direction: column;
	    box-shadow: 0 0 1px #CCC;
	    float: left;
	    transition: box-shadow 0.2s ease;
	}
	#simplyedit.mu-app .mu-file span {
		font-size: 1rem;
	}

	#simplyedit.mu-app .mu-file.mu-selected {
		box-shadow: 0 0 8px #BBB;
	}
	#simplyedit.mu-app .mu-file,
	#simplyedit.mu-app .mu-file * {
		color: #888 !important;
	}
	#simplyedit.mu-app .mu-file-select {
		position: absolute;
		top: 0;
		right: 0;
		opacity: 0.5;
		z-index: 11;
		border: 0px;
		border-radius: 0 3px 0 3px;
		padding: 5px;
		outline: 0px;
		background-color: #E5E5E5;
		transition: background-color 0.2s ease, opacity 0.2s ease;
	}
	#simplyedit.mu-app .mu-selected .mu-file-select {
		opacity: 1;
		background-color: #4F8EF7; /*#6ea0f7;*/
	}
	#simplyedit.mu-app .mu-selected .mu-file-select i {
		color: white !important;
	}
	.mu-app .mu-button.mu-visible {
		display: inline-block;
	}
	.mu-app .mu-invisible {
		display: none;
	}
	#simplyedit.mu-app .mu-file-add {
		box-shadow: 0 0 0;
	}
	#simplyedit.mu-app .mu-file-add .mu-button {
		border: 2px dashed #CCC;
	    background-color: #F0F0F0;
		margin: 0;
		padding-top: 2px;
	}
	#simplyedit.mu-app .mu-file-add .mu-button::after {
		display: none;
	}
	#simplyedit.mu-app .mu-listmode .mu-file-list,
	#simplyedit.mu-app .mu-listmode .mu-file-list .mu-file,
	#simplyedit.mu-app .mu-listmode .mu-file-list ~ .mu-file-add {
		display: block;
		float: none;
		width: auto;
		height: auto;
		min-height: 0;
		margin-bottom: 10px;
	}
	#simplyedit.mu-app .mu-listmode .mu-file-list .mu-file-select {
		height: 100%;
		width: 32px;
		border-radius: 3px 0 0 3px;
	}
	#simplyedit.mu-app .mu-listmode .mu-file-list ~ .mu-file-add .mu-button,
	#simplyedit.mu-app .mu-listmode .mu-file-list .mu-button {
		width: 100%;
		text-align: left;
		min-height: 0;
		display: inline-block;
		align-items: flex-start;
	}
	#simplyedit.mu-app .mu-listmode .mu-file-list ~ .mu-file-add .mu-button>i,
	#simplyedit.mu-app .mu-listmode .mu-file-list .mu-button>i {
		font-size: 2em;
		line-height: 1.5em;
		float: left;
		margin: 0 10px 0 0;
	}
	#simplyedit.mu-app .mu-listmode .mu-file-list ~ .mu-file-add .mu-button>span, 
	#simplyedit.mu-app .mu-listmode .mu-file-list .mu-button>span {
		line-height: 2em;
	}
	#simplyedit.mu-app .mu-listmode .mu-file-list .mu-button::after {
		display: none;
	}

</style>
<style>
	#simplyedit.mu-app .mu-file-select i,
	#simplyedit.mu-app .mu-file-select {
	    transition: background-color 0.1s linear 0.1s, opacity 0.2s linear, transform 0.2s ease;
	    perspective: 1000px;
	    transform: rotateY(0deg);
	}
	#simplyedit.mu-app .mu-selected .mu-file-select,
	#simplyedit.mu-app .mu-selected .mu-file-select i {
	    transition: background-color 0.1s linear, opacity 0.2s linear, transform 0.2s ease;
	    transform: rotateY(180deg);
	}
	#simplyedit.mu-app .mu-selected .mu-file-select {
	    border-radius: 3px 0 3px 0;
	}
</style>
<style>
	#simplyedit.mu-app .mu-toolbar {
		min-height: 60px;
	    padding-right: 5px;
	    box-sizing: border-box;
	}
	#simplyedit.mu-app .mu-button {
		height: 52px;
		margin-top: 3px;
		border-top: 1px solid transparent;
		transition: background 0.2s ease;
	}
	#simplyedit.mu-app .mu-button.mu-selected {
		border-top-color: #ddd;
	}
	#simpluedit.mu-app .mu-button.mu-expands {
		height: 55px;
	}
	#simplyedit.mu-app .mu-button.mu-expands:not(.mu-selected):after {
		bottom: 2px;
	}
	#simplyedit.mu-app .mu-title {
		background:linear-gradient(180deg, #ea5922, #f47b3e 95%, #d74b14 100%);
	    color: white !important;
	    border: 0 !important;
	    width: 60px;
	    height: 60px;
		margin-top: 0;
		cursor: default;
	}
	#simplyedit.mu-app .mu-title>i,
	#simplyedit.mu-app .mu-title>span {
		color: white !important;
	}

	/* alternative title style */
	#simplyedit.mu-app .mu-title {
		padding: 0 10px;
	    font-size: 16px; /* 1rem / 1em */
		width: auto;
		font-family: 'Varela Round', sans-serif;
	}
	#simplyedit.mu-app .mu-title>i {
		display: none;
	}
	#simplyedit.mu-app .mu-dialog > header ~ section {
	    border-top: 60px solid transparent;
	}
</style>
<style>
	#simplyedit.mu-app #simply-users-main.mu-listmode {
		animation-duration: 0.3s;
		animation-name: fadeIn2;
	}
	#simplyedit.mu-app #simply-users-main {
		animation-duration: 0.3s;
		animation-name: fadeIn;
	}
	@keyframes fadeIn {
	  from {
	    opacity: 0;
	  }

	  to {
	    opacity: 1;
	  }
	}
	@keyframes fadeIn2 {
	  from {
	    opacity: 0;
	  }

	  to {
	    opacity: 1;
	  }
	}

</style>
<style>
	#simplyedit.mu-app.nightmode .mu-toolbar {
	    background: linear-gradient(180deg,#222 0,#333 95%,#111);
	}
	#simplyedit.mu-app.nightmode .mu-dialog > footer .mu-toolbar {
	    background: linear-gradient(0deg,#333 0,#222 95%,#111);
	}
	#simplyedit.mu-app.nightmode .mu-dialog {
		background-color: #444;
	}
	#simplyedit.mu-app.nightmode .mu-dialog>section {
	    box-shadow: 0 0 25px #666;
	    border-top-color: #ea5922;
		background-color: #444;
	}
	#simplyedit.mu-app.nightmode button,
	#simplyedit.mu-app.nightmode button * {
		color: #E8E8E8 !important;
	}
	#simplyedit.mu-app.nightmode button.mu-title {
		color: white !important;
	}
	#simplyedit.mu-app.nightmode .mu-button.mu-selected {
		background-color: #444;
	    border-top-color: #333;
	    border-left-color: #333;
	    border-right-color: #666;
	    border-bottom-color: #666;
	    border-bottom-width: 1px;
	}
	#simplyedit.mu-app.nightmode .mu-file .mu-button:after {
		display: none;
	}
	#simplyedit.mu-app.nightmode .mu-file {
		background-color: #444;
		box-shadow: 0 0 1px #111;
	}
	#simplyedit.mu-app.nightmode .mu-file.mu-selected {
	    box-shadow: 0 0 8px #111;
	}
	#simplyedit.mu-app.nightmode .mu-file .mu-button {
		border-bottom: 2px solid #444;
	}
	#simplyedit.nightmode .mu-file-select {
		background-color: #666;
	}
	#simplyedit.mu-app.nightmode .mu-file, 
	#simplyedit.mu-app.nightmode .mu-file * {
	    color: #EEE !important;
	}
	#simplyedit.mu-app.nightmode .mu-file-add .mu-button {
	    border: 2px dashed #666;
	    background-color: #444;
	}

</style>
<style>
	.mu-app .mu-form {
		padding: 0.5em;
    	overflow: auto;
	}
	.mu-app .mu-form-field {
		margin-bottom: 0.5em;
	}
	.mu-app .mu-form-label {
	    display: block;
	    font-size: 0.9em;
	    line-height: 1.5em;
	}
	.mu-app .mu-form input[type="text"],
	.mu-app .mu-form input[type="password"],
	.mu-app .mu-form input[type="number"],
	.mu-app .mu-form-select .mu-file-list {
	    line-height: 1.5em;
	    background-color: #F4F4F4;
	    border: 0;
	    box-shadow: inset 1px 1px 4px #888;
	    padding: 2px 5px;
	    width: 340px;
	    max-width: 100%;
	    box-sizing: border-box;
 	}
	.mu-app .mu-form input[type="number"] {
		width: 170px;
	}
	.mu-app .mu-form input.mu-toolbar-height {
		line-height: 3em;
		padding-left: 1em;
    }
	.mu-app .mu-form-select .mu-file-list {
		min-height: 4em;
		overflow: auto;
	}
	.mu-app .mu-form-select .mu-file-list ul {
		margin: 0;
		float: left;
	}
	.mu-app .mu-form-help {
		margin-top: 4px;
		font-size: 0.8em;
		clear: both;
	}
</style>
<style>

	.mu-app meter {
		display: block;
	    overflow: hidden;
	    width: 1px;
	    height: 1px;
	    margin-bottom: 6px;
    }

	.mu-app meter:before {
	  position:absolute;
	  content:'';
	  display:block;
	  content:'';
	  width:350px;
	  height:4px;
	  background:#d9d9d9;
	}

	.mu-app meter:after {
	  position:absolute;
	  content:'';
	  height:4px;
	  background:#4cb24c;
	  margin-top: -1px;
	}

    /* Webkit based browsers */
	.mu-app meter[value="1"]:after { 
		background: #ff0000; 
		width: 86px;
	}
	.mu-app meter[value="2"]:after {
		background: #ea5922;
		width: 175px;
	}
	.mu-app meter[value="3"]:after {
		background: #ffa500;
		width: 261px;
	}
	.mu-app meter[value="4"]:after {
		background: #8fc400;
		width: 350px;
	}
	.mu-app #password-strength-text {
		font-size: 0.8em;
	}
	.mu-app .mu-form-error {
	    padding: 14px;
	    background-color: #FFFFCC;
	    width: 347px;
    	box-shadow: 0px 0px 1px #999;
    	position: absolute;
	    bottom: 0px;
	    height: 47px;
	    box-sizing: border-box;
	    transition: all 0.2s ease;
	    overflow: hidden;
	}
	.mu-app .mu-form-error:before {
		content: "\f071";
		display: inline-block;
	    font: normal normal normal 14px/1 FontAwesome;
	    font-size: inherit;
	    text-rendering: auto;
	    -webkit-font-smoothing: antialiased;
	    -moz-osx-font-smoothing: grayscale;
	    color: #ea5922;
	    margin-right: 11px;
    }
	.mu-app .mu-form-error:empty {
		height: 0px;
		padding: 0px 14px;
	}
</style>
<style>
	#simplyedit.mu-app .mu-button .mu-state {
		display: none;
	}
	#simplyedit.mu-app .mu-button .mu-state-default {
		display: block;
	}
	#simplyedit.mu-app .mu-button.mu-state-busy .mu-state {
		display: none;
	}
	#simplyedit.mu-app .mu-button.mu-state-busy .mu-state-busy {
		display: block;
	}
	#simplyedit.mu-app .mu-button.mu-state-done .mu-state {
		display: none;
	}
	#simplyedit.mu-app .mu-button.mu-state-done .mu-state-done {
		display: block;
	}
	#simplyedit.mu-app .mu-button.mu-state-error .mu-state {
		display: none;
	}
	#simplyedit.mu-app .mu-button.mu-state-error .mu-state-error {
		display: block;
	}

	#simplyedit.mu-app .mu-button.mu-state-busy i {
		color: #4F8EF7 !important;
	}
	#simplyedit.mu-app .mu-button.mu-state-error i {
		color: #ea5922 !important;
	}
	#simplyedit.mu-app .mu-button.mu-state-done i {
		color: #8fc400 !important;
		color: #ea5922 !important;
	}
	#simplyedit.mu-app .mu-hidden {
		display: none;
	}
</style>
<style>
	#simplyedit.mu-app section.simply-text {
		padding: 15px 10px;
	}
	#simplyedit.mu-app section.simply-text p,
	#simplyedit.mu-app section.simply-text a {
		line-height: 1.4em;
		font-size: 15px;
	}
	#simplyedit.mu-app section.simply-text h1,
	#simplyedit.mu-app section.simply-text h2,
	#simplyedit.mu-app section.simply-text h3 {
		font-family: 'Varela Round', sans-serif;
	}

</style>
</head>
<body id="simplyedit" class="mu-app xnightmode">
	<header>
		<section id="simply-main-toolbar">
			<ul class="mu-toolbar">
				<li>
					<button class="mu-button">
						<img id="logo" src="simply-edit-centered.svg" alt="SimplyEdit">
					</button>
				</li>
				<li>
					<button class="mu-button" data-mu-command="mu-show" data-mu-value="simply-users">
						<i class="fa fa-users"></i>Users
					</button>
				</li>
				<li>
					<button class="mu-button" data-mu-command="mu-show" data-mu-value="simply-backups">
						<i class="fa fa-life-ring"></i>Backups
					</button>
				</li>
				<li>
					<button class="mu-button" data-mu-command="mu-show" data-mu-value="simply-settings">
						<i class="fa fa-gear"></i>Settings
					</button>
				</li>
				<li>
					<button class="mu-button" data-mu-command="mu-show" data-mu-value="simply-check">
						<i class="fa fa-check-square-o"></i>Check
					</button>
				</li>
				<li>
					<button class="mu-button" data-mu-command="mu-show" data-mu-value="simply-help">
						<i class="fa fa-question"></i>Help
					</button>
				</li>
				<li>
					<button class="mu-button" data-mu-command="simply-logout">
						<i class="fa fa-power-off"></i>Logout
					</button>
				</li>
			</ul>
		</section>
	</header>
	<main>
		<article id="simply-settings" class="mu-dialog">
			<header>
				<ul class="mu-toolbar">
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-settings">
							<i class="fa fa-close"></i>Close
						</button>
					</li>
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="fullscreen" data-mu-value="simply-settings">
							<i class="fa fa-expand"></i>Full screen
						</button>
					</li>
					<li>
						<button class="mu-button mu-title">
							<i class="fa fa-gears"></i>Settings
						</button>
					</li>
				</ul>
			</header>
			<footer>
				<ul class="mu-toolbar">
					<li class="mu-dialog-ok">
						<button class="mu-button mu-state-default" data-mu-command="simply-settings-save">
							<span class="mu-state mu-state-default">
								<i class="fa fa-check"></i>
								Save
							</span>
							<span class="mu-state mu-state-busy">
								<i class="mu-animation fa fa-circle-o-notch fa-spin fa-3x fa-fw"></i>
								Saving
							</span>
							<span class="mu-state mu-state-done">
								<i class="fa fa-check mu-success"></i>
								Saved
							</span>
							<span class="mu-state mu-state-error">
								<i class="fa fa-warning mu-error"></i>
								Save
							</span>
						</button>
					</li>
				</ul>
			</footer>
			<section>
				<form class="mu-form simply-settings-form">
					<div class="mu-form-field mu-form-select">
						<label>
							<span class="mu-form-label">Managers</span>
						</label>
						<input class="mu-toolbar-height" type="text" name="managers" disabled data-simply-field="managers" value="<?php
							echo join(',',(array)$settings->managers);
						?>">
						<button type="button" class="mu-browse mu-button" data-mu-command="simply-settings-select-users" data-mu-value="managers">
							<i class="fa fa-users"></i>
							<span>Select</span>
						</button>
						<div class="mu-form-help">
							Select the users with access to this management screen. They will be able to add, edit and delete
							users, as well as restore backups.
						</div>
					</div>
					<div class="mu-form-field">
						<label>
							<span class="mu-form-label">Max. number of backups</span>
							<input type="number" name="simply-max-backups" data-simply-field="simply-max-backups" value="<?php
								echo $settings->max_backups ?: '10';
							?>">
						</label>
						<div class="mu-form-help">
							SimplyEdit stores at most one backup per hour. When the maximum number of backups is reached,
							SimplyEdit will remove the oldest entry whenever a new backup file is created.
						</div>
					</div>
					<div class="mu-form-field">
						<label>
							<span class="mu-form-label">Prerender.io token</span>
							<input type="text" name="simply-prerender-token" data-simply-field="simply-prerender-token" value="<?php
								echo $settings->prerender_token ?: '';
							?>">
						</label>
						<div class="mu-form-help">
							<a href="https://prerender.io/" target="_blank">Prerender.io</a> is a third-party service that renders a statically cached version of your pages for
							a specific set of crawlers and browsers that cannot run javascript natively.
						</div>
					</div>
					<div class="mu-form-error" data-simply-field="simply-settings-error"></div>
				</form>
			</section>
		</article>
		<article id="simply-users" class="mu-dialog">
			<header>
				<ul class="mu-toolbar">
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-users">
							<i class="fa fa-close"></i>Close
						</button>
					</li>
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="fullscreen" data-mu-value="simply-users">
							<i class="fa fa-expand"></i>Full screen
						</button>
					</li>
					<li>
						<button class="mu-button mu-title">
							<i class="fa fa-users"></i>Users
						</button>
					</li>
					<li>
						<button class="mu-button" data-mu-command="simply-users-add">
							<i class="fa fa-user-plus"></i>Add User
						</button>
					</li>
					<li>
						<button class="mu-button" data-mu-command="simply-users-delete">
							<i class="fa fa-trash"></i>Delete
						</button>
					</li>
					<li>
						<button class="mu-button" data-mu-command="mu-listmode" data-mu-value="simply-users-main">
							<i class="fa fa-list"></i>List mode
						</button>
					</li>

				</ul>
			</header>
			<footer>
				<ul class="mu-toolbar">
					<li class="mu-dialog-ok mu-dialog-save">
						<button class="mu-button mu-state-default" data-mu-command="simply-users-save">
							<span class="mu-state mu-state-default">
								<i class="fa fa-check"></i>
								Save
							</span>
							<span class="mu-state mu-state-busy">
								<i class="mu-animation fa fa-circle-o-notch fa-spin fa-3x fa-fw"></i>
								Saving
							</span>
							<span class="mu-state mu-state-done">
								<i class="fa fa-check mu-success"></i>
								Saved
							</span>
							<span class="mu-state mu-state-error">
								<i class="fa fa-warning mu-error"></i>
								Save
							</span>
						</button>
					</li>
					<li class="mu-dialog-ok mu-dialog-select mu-hidden">
						<button class="mu-button" data-mu-command="simply-users-selected">
							<i class="fa fa-check"></i>
							Select
						</button>
					</li>
				</ul>
			</footer>
			<section id="simply-users-main">
				<ul id="simplyUsers" data-simply-list="users" class="mu-file-list">
					<template>
						<li class="mu-file">
							<button class="mu-file-select" data-mu-command="simply-users-select">
								<i class="fa fa-check"></i>
							</button>
							<button class="mu-button" data-mu-command="simply-users-update">
								<i class="fa fa-user"></i>
								<span data-simply-field="login">User</span>
							</button>
						</li>
					</template>
				</ul>
				<div class="mu-file mu-file-add">
					<button class="mu-button" data-mu-command="simply-users-add">
						<i class="fa fa-user-plus"></i>
						<span>Add User</span>
					</button>
				</div>
			</section>
		</article>
		<article id="simply-users-update" class="mu-dialog">
			<header>
				<ul class="mu-toolbar">
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-users-update">
							<i class="fa fa-close"></i>Close
						</button>
					</li>
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="fullscreen" data-mu-value="simply-users-update">
							<i class="fa fa-expand"></i>Full screen
						</button>
					</li>
					<li>
						<button class="mu-button mu-title">
							<i class="fa fa-users"></i>User
						</button>
					</li>
				</ul>
			</header>
			<footer>
				<ul class="mu-toolbar">
					<li class="mu-dialog-ok">
						<button class="mu-button" data-mu-command="simply-users-update-save">
							<i class="fa fa-check"></i>Update
						</button>
					</li>
				</ul>
			</footer>
			<section id="simply-users-update-main">
				<ul id="simplyUsers" class="mu-file-list">
					<li class="mu-file">
						<button class="mu-button">
								<i class="fa fa-user"></i>
								<span data-simply-field="simply-user-name">User</span>
						</button>
					</li>
				</ul>
				<form class="mu-form simply-users-update-form">
					<div class="mu-form-field">
						<label>
							<span class="mu-form-label">Name</span>
							<input type="text" name="simply-user-name-new" data-simply-field="simply-user-name-new">
						</label>
					</div>
					<div class="mu-form-field">
						<label>
							<span class="mu-form-label">Password</span>
							<input type="password" id="password" name="simply-user-password" data-simply-field="simply-user-password">
						</label>
					</div>
					<div class="mu-form-field">
						<label>
							<span class="mu-form-label">Password (Repeat)</span>
							<input type="password" id="password2" name="simply-user-password2" data-simply-field="simply-user-password2">
						</label>
					</div>
					<meter max="4" id="password-strength-meter"></meter>
					<p id="password-strength-text"></p>
					<div class="mu-form-error" data-simply-field="simply-user-error"></div>
				</form>
			</section>
		</article>

		<article id="simply-backups" class="mu-dialog">
			<header>
				<ul class="mu-toolbar">
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-backups">
							<i class="fa fa-close"></i>Close
						</button>
					</li>
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="fullscreen" data-mu-value="simply-backups">
							<i class="fa fa-expand"></i>Full screen
						</button>
					</li>
					<li>
						<button class="mu-button mu-title">
							<i class="fa fa-life-ring"></i>Backups
						</button>
					</li>
					<li>
						<button class="mu-button" data-mu-command="simply-backups-delete">
							<i class="fa fa-trash"></i>Delete
						</button>
					</li>
					<li>
						<button class="mu-button mu-selected" data-mu-command="mu-listmode" data-mu-value="simply-backups-main">
							<i class="fa fa-list"></i>List mode
						</button>
					</li>

				</ul>
			</header>
			<section id="simply-backups-main" class="mu-listmode">
				<ul id="simplyBackups" data-simply-list="backups" class="mu-file-list">
					<template>
						<li class="mu-file">
							<button class="mu-file-select" data-mu-command="simply-backups-select">
								<i class="fa fa-check"></i>
							</button>
							<button class="mu-button" data-mu-command="simply-backups-view">
								<i class="fa fa-file-text-o"></i>
								<span data-simply-field="filename">File</span>
							</button>
						</li>
					</template>
				</ul>
			</section>
		</article>
		<article id="simply-help" class="mu-dialog">
			<header>
				<ul class="mu-toolbar">
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-help">
							<i class="fa fa-close"></i>Close
						</button>
					</li>
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="fullscreen" data-mu-value="simply-help">
							<i class="fa fa-expand"></i>Full screen
						</button>
					</li>
					<li>
						<button class="mu-button mu-title">
							<i class="fa fa-question"></i>Help
						</button>
					</li>
				</ul>
			</header>
			<section class="simply-text">
				<h1>About this dashboard</h1>
				<p>This is the management dashboard for your SimplyEdit website. Here you can manage who can 
					edit your site and who can manage users and restore backups.</p>
				<p>This dashboard will also help you install SimplyEdit and show if there are any problems with
					your installation.</p>
				<p>If the <a href="#" data-mu-command="mu-show" data-mu-value="simply-check">Check</a> screen shows 100% green, but you still can't
					save your changes in the SimplyEdit editor, it may be that your webserver is configured to deny all
					PUT requests. Please contact your system administrator to remedy that.</p>
				<p>If you still have problems, you can always join us at <a href="https://riot.im/app/#/room/#SimplyEdit:matrix.org" target="_blank">
					https://riot.im/app/#/room/#SimplyEdit:matrix.org</a> and ask for help there.</P>
			</section>
		</article>
		<article id="simply-check" class="mu-dialog">
			<header>
				<ul class="mu-toolbar">
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-check">
							<i class="fa fa-close"></i>Close
						</button>
					</li>
					<li class="mu-dialog-close">
						<button class="mu-button" data-mu-command="fullscreen" data-mu-value="simply-check">
							<i class="fa fa-expand"></i>Full screen
						</button>
					</li>
					<li>
						<button class="mu-button mu-title">
							<i class="fa fa-check-square-o"></i>
							Check
						</button>
					</li>
					<li>
						<button class="mu-button" data-mu-command="simply-reload">
							<i class="fa fa-refresh"></i>Check Again
						</button>
					</li>
				</ul>
			</header>
			<footer>
				<ul class="mu-toolbar">
					<li class="mu-dialog-ok">
						<button class="mu-button" data-mu-command="mu-hide" data-mu-value="simply-check">
							<i class="fa fa-check"></i>Ok
						</button>
					</li>
				</ul>
			</footer>
			<section id="installCheck">
<?php 
	// ><h1 class="simplyeedit-fail">The quickstart templates require PHP (at least 5.6) to work</h1>
	// <!--
	try {
		require_once('checks.php');
	} catch ( \Exception $e ) {
		if (!$errors) {
			$errors = array();
		}
		$errors[] = "Could not run the installation checks, got this error: ".$e->getMessage();
	}
	// -->
?>
				<div id="cont" data-pct="<?php echo $percentage; ?>">
					<svg id="svg" width="200" height="200" viewPort="0 0 100 100" version="1.1" xmlns="http://www.w3.org/2000/svg">
					  <circle r="90" cx="100" cy="100" fill="transparent" stroke-dasharray="565.48" stroke-dashoffset="0"></circle>
					  <circle id="bar" r="90" cx="100" cy="100" fill="transparent" stroke-dasharray="565.48" stroke-dashoffset="565.48"></circle>
					</svg>
				</div>
				<div id="errors"><?php
					if ( count($errors) ) {
						echo join("<br>\n", $errors);
					} else {
						echo 'Congratulations! You are ready to edit your site.';
					}
				?></div>
			</section>
		</article>
		<div class="mu-dialog-background"></div>
	</main>
	<footer>
	</footer>
	<script src="mu.min.js"></script>
	<script>
		var selected = [];
		var selectedfiles = [];
		var count = 0;
		var currentItem = null;
		var dashboard = mu.app({
			container: document.body,
			commands: {
				'simply-users-select': function(value, el) {
					value = el.nextElementSibling.innerText.trim();
					if ( el.parentElement.classList.toggle('mu-selected') ) {
						selected.push(value);
					} else {
						selected = selected.filter(function(v) {
							return v != value;
						});
					}
					if ( selected.length ) {
						document.querySelector('[data-mu-command="simply-users-delete"]').classList.add('mu-visible');
						document.querySelector('[data-mu-command="simply-users-add"]').classList.add('mu-invisible');
					} else {
						document.querySelector('[data-mu-command="simply-users-delete"]').classList.remove('mu-visible');
						document.querySelector('[data-mu-command="simply-users-add"]').classList.remove('mu-invisible');
					}
				},
				'mu-listmode': function(value, el) {
					if (document.getElementById(value).classList.toggle('mu-listmode')) {
						el.classList.add('mu-selected');
					} else {
						el.classList.remove('mu-selected');
					}

				},
				'simply-users-add': function(value, el) {	
					// add empty user and show edit screen
					selected = [];
					document.querySelector('[data-mu-command="simply-users-delete"]').classList.remove('mu-visible');
					document.querySelector('[data-mu-command="simply-users-add"]').classList.remove('mu-invisible');
					this.action('addUser', 'New user'+(count++?' '+count:''), '');
					var that = this;
					window.setTimeout(function() {
						document.querySelector('#simplyUsers li:last-child').scrollIntoView();
						that['simply-users-update'](document.querySelector('#simplyUsers li:last-child button.mu-button').innerText, document.querySelector('#simplyUsers li:last-child button.mu-button'));
					}, 100);
				},
				'simply-users-update': function(value, el) {
					if ( selected && selected.length ) {
						return this['simply-users-select'](value, el.previousElementSibling);
					}
					currentItem = el;
					editor.pageData['simply-user-name'] = new String(el.innerText).trim();
					editor.pageData['simply-user-name-new'] = new String(el.innerText).trim();
 					document.getElementById('password').value = '';
 					document.getElementById('password2').value = '';
					editor.pageData['simply-user-error'] = '';
					document.getElementById('password-strength-meter').value=0;
					document.getElementById('password-strength-text').innerHTML = '';
 					this['mu-show']('simply-users-update');
				},
				'simply-users-update-save': function(value, el) {
					var originalName = editor.pageData['simply-user-name'];
					var newName = editor.pageData['simply-user-name-new'].trim();
					var found = false;
					for (var i=0; i<editor.pageData.users.length; i++) {
						if ( editor.pageData.users[i].login == originalName ) {
							var found = i;
						}
					}	
					if (newName!=originalName) {
						// check name not empty
						if (newName) {
							// check name not duplicate					
							for (var i=0; i<editor.pageData.users.length; i++) {
								if ( editor.pageData.users[i].login==newName ) {
									// duplicate -> error
									editor.pageData['simply-user-error'] = 'Username '+newName+' is already taken.';
									return;
								}
							}
						}
					}
					editor.pageData['simply-user-error'] = '';
					var p1 = document.getElementById('password').value.trim();
					var p2 = document.getElementById('password2').value.trim();					
					var hash = null;
					if (p1) {
						// check password == password2
						if (p1 != p2) {
							editor.pageData['simply-user-error'] = 'Passwords do not match';
							return;
						}
						var shaObj = new jsSHA('SHA-1', 'TEXT');
						shaObj.update(p1);
						hash = shaObj.getHash('B64');
					}
					if (found === false) {
						found = editor.pageData.users.length;
						editor.pageData.users.push({
							login: newName
						});
					} else {
						editor.pageData.users[found].login = newName
					}
					if ( hash ) {
						editor.pageData.users[found].password = '{SHA}'+hash;
					}
					this['mu-hide']('simply-users-update');
				},
				'simply-users-delete': function(value, el) {
					this.action('deleteUsers',selected);
					selected = [];
				},
				'simply-users-save': function(value, el) {
					setButtonState(el, 'mu-state-busy');
					var resetButton = function() {
						document.removeEventListener('databind:resolved', resetButton);
						setButtonState(el, 'mu-state-default');
					};
					this.action('saveUsers').then(function(response) {
						window.setTimeout(function() { 
							setButtonState(el, 'mu-state-done');
							document.addEventListener('databind:resolved', resetButton);
						}, 1000);
					})
					.catch(function(error) {
						alert(error);
						window.setTimeout(function() { 
							setButtonState(el, 'mu-state-error');
							document.addEventListener('databind:resolved', resetButton);
						}, 1000);
					});
				},
				'simply-settings-save': function(value, el) {
					setButtonState(el, 'mu-state-busy');
					var resetButton = function() {
						document.removeEventListener('databind:resolved', resetButton);
						setButtonState(el, 'mu-state-default');
					};
					this.action('saveSettings')
					.then(function(response) {
						window.setTimeout(function() { 
							setButtonState(el, 'mu-state-done');
							document.addEventListener('databind:resolved', resetButton);
						}, 1000);
					})
					.catch(function(error) {
						alert(error);
						window.setTimeout(function() {
							setButtonState(el, 'mu-state-error');
							document.addEventListener('databind:resolved', resetButton);
						}, 1000);
					});
				},
				'simply-settings-select-users': function(value, el) {
					document.querySelector('#simply-users li.mu-dialog-save').classList.add('mu-hidden');
					document.querySelector('#simply-users li.mu-dialog-select').classList.remove('mu-hidden');
					this['mu-show']('simply-users');
					this['simply-users-selected'] = function(value, el) {
						this['mu-hide']('simply-users');
						editor.pageData.users = editor.pageData.users;
						document.querySelector('#simply-users li.mu-dialog-save').classList.remove('mu-hidden');
						document.querySelector('#simply-users li.mu-dialog-select').classList.add('mu-hidden');
						document.querySelector('[data-mu-command="simply-users-delete"]').classList.remove('mu-visible');
						document.querySelector('[data-mu-command="simply-users-add"]').classList.remove('mu-invisible');
						// FIXME: this should be an action
						editor.pageData.managers = selected;
						selected = [];
						delete this['simply-users-selected'];
					}
				},
				'simply-reload': function(value, el) {
					window.location.reload(true);
				},
				'fullscreen': function(value, el) {
					var fullscreen = document.getElementById(value).classList.toggle('mu-fullscreen');
					if (fullscreen) {
						el.classList.add('mu-selected');
					} else {
						el.classList.remove('mu-selected');
					}
				},
				'simply-backups-select': function(value, el) {
					value = el.nextElementSibling.innerText.trim();
					if ( el.parentElement.classList.toggle('mu-selected') ) {
						selectedfiles.push(value);
					} else {
						selectedfiles = selectedfiles.filter(function(v) {
							return v != value;
						});
					}
					if ( selectedfiles.length ) {
						document.querySelector('[data-mu-command="simply-backups-delete"]').classList.add('mu-visible');
					} else {
						document.querySelector('[data-mu-command="simply-backups-delete"]').classList.remove('mu-visible');
					}
				},
				'simply-backups-delete': function(value, el) {
					this.action('deleteBackups',selectedfiles);
					selectedfiles = [];
					document.querySelector('[data-mu-command="simply-backups-delete"]').classList.remove('mu-visible');
				},
				'simply-backups-view': function(value, el) {
					if (confirm('Restore backup '+el.innerText+'?')) {
						this.action('restoreBackup',el.innerText).catch(function(error) {
							alert(error.message);
						});
					}
				},
				'simply-logout': function(value, el) {
					window.location = "logout.php";
				}
			},
			actions: {
				'addUser': function(name, password) {
					editor.pageData.users.push({
						login: name,
						password: password
					});
				},
				'deleteUsers': function(names) {
					var newUsers = editor.pageData.users.slice(); // copy
					for ( var i=newUsers.length-1; i>=0; i--) {
						if (names.indexOf(editor.pageData.users[i].login)!=-1) {
							newUsers.splice(i, 1);
						}
					}
					editor.pageData.users = newUsers;
				},
				'updateUser': function(name, newName, password) {
					for ( var i=0; i<editor.pageData.users.length; i++) {
						if (editor.pageData.users[i].login == name) {
							editor.pageData.users[i] = {
								login: newName,
								password: password
							};
							break;
						}
					}
				},
				'saveUsers': function() {
					// generate htpasswd file
					var htpasswd = '';
					for (var i=0; i<editor.pageData.users.length;i++) {
						htpasswd += editor.pageData.users[i].login + ':' + editor.pageData.users[i].password + "\n";
					}
					// send put request
					return fetch('../data/htpasswd?_method=PUT', {
						method: 'POST',
						credentials: 'include',
						body: htpasswd
					}).then(function(response) {
						return checkStatus(response);
					});
				},
				'saveSettings': function() {
					var settings = {
						'managers': editor.pageData.managers,
						'max_backups': editor.pageData['simply-max-backups'],
						'prerender_token': editor.pageData['simply-prerender-token']
					};
					content = JSON.stringify(settings);
					return fetch('../data/settings.json?_method=PUT', {
						method: 'POST',
						credentials: 'include',
						body: content
					}).then(function(response) {
						return checkStatus(response);
					});
				},
				'deleteBackups': function(files) {
					var deleteNext = function() {
						var file = files.pop();
						if (file) {
							return fetch('../data/'+file+'?_method=DELETE', {
								method: 'POST',
								credentials: 'include'
							})
							.then(function(response) {
								return checkStatus(response);
							})
							.then(function(response) {
								// remove file from backups list
								editor.pageData.backups = editor.pageData.backups.filter(function(item) {
									if (item.filename==file) {
										return false;
									}
									return true;
								});
								if (files.length) {
									return deleteNext();
								}
							});
						}
					}
					return deleteNext();
				},
				'restoreBackup': function(file) {
					return fetch('../data/'+file)
					.then(function(response) {
						return checkStatus(response);
					})
					.then(function(contents) {
						return fetch('../data/data.json?_method=PUT', {
							method: 'POST',
							credentials: 'include',
							body: contents
						});
					})
					.then(function(response) {
						return checkStatus(response);
					});
				}
			}
		});

		function checkStatus(response) {
			if ( response.ok ) {
				return response.text();
			}
			if ( response.status == 422 ) {
				return response.json().then(function(err) {
					throw err;
				});
			}
			var status = response.status;
			var statusText = response.statusText;
			return response.json().catch(function(response) {
				var error = new Error(statusText || response);
				error.response = statusText || response;
				throw error;
			}).then(function(json) {
				var error = new Error(json.message || response.text());
				error.response = response;
				throw error;
			});
		}

		function setButtonState(el, state) {
			if (!el) {
				return;
			}
			for ( var i=el.classList.length-1; i>=0; i-- ) {
				var c = new String(el.classList.item(i));
				if (c.substr(0, 'mu-state-'.length) === 'mu-state-' && c!==state) {
					el.classList.remove(c);
				}
			}
			el.classList.add(state);
		}

		function resetSaveButton() {
			document.removeEventListener('databind:resolved', resetSaveButton);
			setButtonState(
				document.querySelector('button[data-mu-command="simply-users-save"]'),
				'mu-state-default'
			);
		}

		function progress() {
			var val = parseInt(document.getElementById('cont').dataset.pct);
			var circle = document.getElementById('bar');

			if (isNaN(val)) {
				val = 100; 
			} else {
				var r = circle.getAttribute('r');
				var c = Math.PI*(r*2);

				if (val < 0) { val = 0;}
				if (val > 100) { val = 100;}

				var pct = ((100-val)/100)*c;

				circle.style.strokeDashoffset = pct;
			}
		}
		window.setTimeout(progress, 100);
		<?php
			if (count($errors)) {
				echo "document.getElementById('simply-check').classList.add('mu-visible');\n";
			}
		?>
	</script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
	<script>
		if (typeof window.zxcvbn != 'undefined') {
			var strength = {
			  0: "Worst",
			  1: "Bad",
			  2: "Weak",
			  3: "Good",
			  4: "Strong"
			}
			var password = document.getElementById('password');
			var meter = document.getElementById('password-strength-meter');
			var text = document.getElementById('password-strength-text');

			password.addEventListener('input', function() {
			  var val = password.value;
			  var result = zxcvbn(val);

			  // Update the password strength meter
			  meter.value = result.score;

			  // Update the text indicator
			  if (val !== "") {
			    text.innerHTML = "Strength: " + strength[result.score]; 
			  } else {
			    text.innerHTML = "";
			  }
			});
		}
	</script>
	<script src="sha1.js"></script>
	<script src="https://cdn.polyfill.io/v2/polyfill.minify.js?features=default,fetch|gated"></script>
	<script>
		document.addEventListener('simply-content-loaded', function(){
			fetch('/data/')
			.then(function(response) {
				return response.text();
			})
			.then(function(text) {
				var temp = document.createElement('div');
				temp.innerHTML = text;
				var links = temp.querySelectorAll('a');
				var backups = [];
				for ( var i = 0; i<links.length; i++) {
					var found=links[i].innerText.match(/^data\.(([0-9]+)\-([0-9]+)\-([0-9]+)\-([0-9]+).*)\.json$/);
					if (found) {
						backups.push({
							filename: links[i].innerText,
							year: found[2],
							month: found[3],
							day: found[4],
							hour: found[5]
						});
					}
				}
				editor.pageData.backups = backups;
			});
		});
	</script>
	<script>
		<?php
			htpasswd::load('/data/','.htpasswd');
			echo 'var users = '.json_encode(htpasswd::$users).';'; 
		?>
		document.addEventListener('simply-content-loaded', function(){
			document.body.classList.add('simply-loaded');
			var userlist = [];
			for ( var u in users ) {
				userlist.push({
					login: u,
					password: users[u]
				});
			}
			editor.pageData.users = userlist;
		});
	</script>

	<script src="//cdn.simplyedit.io/1/simply-edit.js"></script>
	<script>
		editor.field.registerType(
			"input[type=number]", 
			function(field) {
				return field.value;
			},
			function(field, data) {
				field.value = data;
			}
		);

	</script>
</body>
</html>
