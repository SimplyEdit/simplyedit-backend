<?php
    $version = explode('.', phpversion());
    $major = (int)$version[0];
    $minor = (int)$version[1];
    if ($major<=5 & $minor<6) {
		$errors[] = 'The quickstart templates require at least PHP 5.6 to work.'; 
		return;
    }
    require_once('config.php');
    
    if (function_exists('posix_getpwuid')) {
        $user = posix_getpwuid(posix_geteuid())['name'];
    } else {
        $user = "[ user account unknown, try 'www-data']";
    }

    $checks = [
        'data' => [
            'title' => 'Checking data directory',
            'checks' => [
                'exists' => [
                    'title' => 'Does it exist?',
                    'check' => function() {
                        if ( filesystem::exists('/data/') ) {
                            return ok();
                        }
                        return fail(filesystem::$basedir.'/data/ directory is missing');
                    }
                ],
                'readable' => [ 
                    'title' => 'Is it readable?',
                    'check' => function() use ($user) {
                        if ( is_readable(filesystem::$basedir.'/data/') ) {
                            if ( is_dir(filesystem::$basedir.'/data/') ) {
                                return ok();
                            } else {
                                return fail(filesystem::$basedir.'/data/ is not a directory');
                            }
                        } else {
                            return fail(filesystem::$basedir.'/data/ directory is not readable. Grant read and write access for user '.$user);
                        }
                    }
                ],
                'writable' => [
                    'title' => 'Is it writable?',
                    'check' => function() use ($user) {
                        if ( is_writable(filesystem::$basedir.'/data/') ) {
                            return ok();
                        } else {
                            return fail(filesystem::$basedir.'/data/ directory is not writable. Grant read and write access for user '.$user);
                        }
                    }
                ]
            ]
        ],
        'img' => [
            'title' => 'Checking img directory',
            'checks' => [
                'exists' => [
                    'title' => 'Does it exist?',
                    'check' => function() {
                        if ( filesystem::exists('/img/') ) {
                            return ok();
                        }
                        return fail(filesystem::$basedir.'/img/ directory is missing');
                    }
                ],
                'readable' => [ 
                    'title' => 'Is it readable?',
                    'check' => function() use ($user) {
                        if ( is_readable(filesystem::$basedir.'/img/') ) {
                            if ( is_dir(filesystem::$basedir.'/img/') ) {
                                return ok();
                            } else {
                                return fail(filesystem::$basedir.'/img/ is not a directory');
                            }
                        } else {
                            return fail(filesystem::$basedir.'/img/ directory is not readable. Grant read and write access for user '.$user);
                        }
                    }
                ],
                'writable' => [
                    'title' => 'Is it writable?',
                    'check' => function() use ($user) {
                        if ( is_writable(filesystem::$basedir.'/img/') ) {
                            return ok();
                        } else {
                            return fail(filesystem::$basedir.'/img/ directory is not writable. Grant read and write access for user '.$user);
                        }
                    }
                ]
            ]
        ],
        'files' => [
            'title' => 'Checking files directory',
            'checks' => [
                'exists' => [
                    'title' => 'Does it exist?',
                    'check' => function() {
                        if ( filesystem::exists('/files/') ) {
                            return ok();
                        }
                        return fail(filesystem::$basedir.'files directory is missing');
                    }
                ],
                'readable' => [ 
                    'title' => 'Is it readable?',
                    'check' => function() use ($user) {
                        if ( is_readable(filesystem::$basedir.'/files/') ) {
                            if ( is_dir(filesystem::$basedir.'/files/') ) {
                                return ok();
                            } else {
                                return fail(filesystem::$basedir.'/files/ is not a directory');
                            }
                        } else {
                            return fail(filesystem::$basedir.'/files/ directory is not readable. Grant read and write access for user '.$user);
                        }
                    }
                ],
                'writable' => [
                    'title' => 'Is it writable?',
                    'check' => function() use ($user) {
                        if ( is_writable(filesystem::$basedir.'/files/') ) {
                            return ok();
                        } else {
                            return fail(filesystem::$basedir.'/files/ directory is not writable. Grant read and write access for user '.$user);
                        }
                    }
                ]
            ]
        ],
        '.htaccess' => [
            'title' => '.htaccess (Apache configuration)',
            'checks' => [
                'exists' => [
                    'title' => 'Checking if .htaccess file exists',
                    'check' => function() {
                        $contents = filesystem::get('/', '.htaccess');
                        if ($contents && strlen($contents)) {
                            return ok();
                        } else {
                            return fail('Missing or empty .htaccess file in the document root.');
                        }
                    }
                ] 
            ]
        ],
        '.htpasswd' => [
            'title' => '.htpasswd (users and passwords file)',
            'checks' => [
                'exists' => [
                    'title' => 'Checking if .htpasswd file exists',
                    'check' => function() {
                        $contents = filesystem::get('/data/', '.htpasswd');
                        if ($contents && strlen($contents)) {
                            return ok();
                        } else {
                            return fail('Missing or empty .htpasswd file in the document root.');
                        }
                    }
                ],
                'user' => [
                    'title' => 'Checking if it contains a valid user.',
                    'check' => function() {
                        htpasswd::load('/data/','.htpasswd');
                        if ( htpasswd::$users && count(htpasswd::$users)) {
                            return ok();
                        } else {
                            return fail('No users defined in .htpasswd file. Please add at least one user through the "Users" dialog.');
                        }
                    }
                ],
                'simplyedit' => [
                    'title' => 'Checking if simplyedit user has been disabled.',
                    'check' => function() {
                        if ( htpasswd::$users['simplyedit'] ) {
                            return fail('Please delete the user simplyedit and replace it with a personal account.');
                        }
                        return ok();
                    }
                ]
            ]
        ],
        'key' => [
            'title' => 'Checking API keys',
            'check' => function() {
                $fails = [];
				$files = [];
                // check index.html or index.php
				if ( !is_readable(filesystem::$basedir.'/index.html') && !is_readable(filesystem::$basedir.'/index.php')) {
					$fails[] = fail(filesystem::$basedir.'/ has no readable index.php or index.html. Create one and grant read access for user '.$user);
				} else if (is_readable(filesystem::$basedir.'/index.html')) {
					$files[] = filesystem::$basedir.'/index.html';
				}
                // check templates/
                if ( is_dir(filesystem::$basedir.'/templates/') ) {
                    if ( !is_readable(filesystem::$basedir.'/templates/') ) {
                        $fails[] = fail(filesystem::$basedir.'/templates/ directory is not readable. Grant read access for user '.$user);
                    } else {
	                    $dir = opendir(filesystem::$basedir.'/templates/');
					}
                    if ( $dir ) {
                        while (false !== ($entry = readdir($dir))) {
                            $entry = filesystem::$basedir.'/templates/'.$entry;
                            if (is_file($entry) && is_readable($entry)) {
                                $files[] = $entry;
                            }
                        }
                    }
                }
                foreach ( $files as $file ) {
                    $error = checkKey($file);
                    if ( $error ) {
                        $fails[] = fail($error);
                    }
                }
                if ( !count($fails) ) {
                    return ok();
                } else {
                    return join("<br>\n",$fails);
                }
            }
        ]
    ];

    function checkKey($file) {
        global $user;
        $contents = file_get_contents($file);
        if ( $contents === false ) {
            return 'Could not read '.$file.'. Grant read access for user '.$user;
        }
        if (!preg_match('/data-api-key\s*=\s*"([^"]*)"/',$contents, $matches)) {
            return $file.' has no data-api-key attribute.';
        }
        if ($matches[1]=='localhost') {
            return $file.' has API key "localhost", which will only work locally.';
        }
        if ($matches[1]=='simplyedit') {
            return $file.' has an invalid API key "simplyedit", please grab a license key and enter it.';
        }
    }

    function ok() {
        return true;
    }

    function fail($msg) {
        return '<div class="simplyedit-fail">'.$msg.'</div>';
    }

    function run_check($check) {
        if ( $check['checks'] ) {
            $result = run_checks($check['checks']);
        } else {
            try {
                $result = $check['check']();
            } catch(\Exception $e) {
                $result = fail($check['title'].': '.$e->getMessage());
            }
        }
        return $result;
    }

    function run_checks($checks) {
        $results = [];
        foreach ($checks as $name => $check) {
            $results[$name] = run_check($check);
        }
        return $results;            
    }

    function flatten($array) {
        $flat = [];
        array_walk_recursive( $array, function($value) use(&$flat) {
            $flat[] = $value;
        });
        return $flat;
    }

    $results = flatten(run_checks($checks));
    $errors = array_filter($results, function($value) {
        return $value !== true;
    });
    $percentage = 100;
    if ( count($errors) ) {
        $percentage = 100 - round(( count($errors) / count($results) ) * 100);
    }