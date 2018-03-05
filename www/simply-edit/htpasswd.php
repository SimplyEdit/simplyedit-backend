<?php
// Copyright (C) 2017 Muze <info@muze.nl>
// based partly on https://elonen.iki.fi/code/misc-notes/htpasswd-php/
//
// Copyright (C) 2004,2005 Jarno Elonen <elonen@iki.fi>
//
// Redistribution and use in source and binary forms, with or without modification,
// are permitted provided that the following conditions are met:
//
// * Redistributions of source code must retain the above copyright notice, this
//   list of conditions and the following disclaimer.
// * Redistributions in binary form must reproduce the above copyright notice,
//   this list of conditions and the following disclaimer in the documentation
//   and/or other materials provided with the distribution.
// * The name of the author may not be used to endorse or promote products derived
//   from this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE AUTHOR ''AS IS'' AND ANY EXPRESS OR IMPLIED
// WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
// AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR
// BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
// DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
// LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
// ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
// NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
// EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

    class htpasswd {
        static $users = [];

        public static function load($filename) {
            $lines = preg_split('/\r\n|\r|\n/',filesystem::get('/', $filename));
			foreach ($lines as $line) {
				list($user,$password) = array_map('trim', explode(':',$line));
                if ($user && $password) {
    				self::$users[$user] = $password;
                }
			}
        }

        public static function check($user, $password) {
            if ( !isset(self::$users[$user]) ) {
                return false;
            }
            $checks = [ '{SSHA}' => 'saltedSHA1', '{SHA}' => 'SHA1', '$apr1$' => 'MD5', '$2y$' => 'bcrypt' ];
            $crypted = self::$users[$user];
            $check = 'crypt';
            foreach($checks as $match => $algorithm) {
                if ( strpos($crypted, $match) === 0 ) {
                    $check = $algorithm;
                    break;
                }
            }
            //echo $check;
            return self::$check($crypted, $password);
        }

        private static function crypt($crypted, $password) {
            return (crypt( $password, substr($crypted,0,CRYPT_SALT_LENGTH) ) == $crypted);
        }

        private static function saltedSHA1($crypted, $password) {
            $hash = base64_decode(substr($crypted, 6));
            return (substr($hash, 0, 20) == pack('H*', sha1($password . substr($hash, 20))));
        }

        private static function SHA1($crypted, $password) {
			$non_salted_sha1 = "{SHA}" . base64_encode(pack("H*", sha1($password)));
            //echo "[".$crypted.' == '.$non_salted_sha1.']';
            return ($non_salted_sha1 == $crypted);
        }

        private static function MD5($crypted, $password) {
            // thanks to http://blog.ethlo.com/2013/02/01/using-php-and-existing-htpasswd-file-for-authentication.html
            $passParts = explode('$', $crypted);
            $salt      = $passParts[2];
            $hashed    = self::cryptApr1Md5($password, $salt);
            return $hashed == $crypted;
        }

        private static function bcrypt($crypted, $password) {
            return password_verify($password, $crypted);
        }

        private function cryptApr1Md5($plainpasswd, $salt) {
            $len  = strlen($plainpasswd);
            $text = $plainpasswd.'$apr1$'.$salt;
            $bin  = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
            for ($i = $len; $i > 0; $i -= 16) { 
                $text .= substr($bin, 0, min(16, $i));
            }
            for ($i = $len; $i > 0; $i >>= 1) {
                $text .= ($i & 1) ? chr(0) : $plainpasswd{0};
            }
            $bin = pack("H32", md5($text));
            for ($i = 0; $i < 1000; $i++) {
                $new = ($i & 1) ? $plainpasswd : $bin;
                if ($i % 3) {
                    $new .= $salt;
                }
                if ($i % 7) {
                    $new .= $plainpasswd;
                }
                $new .= ($i & 1) ? $bin : $plainpasswd;
                $bin = pack("H32", md5($new));
            }
            $tmp = '';
            for ($i = 0; $i < 5; $i++) {
                $k = $i + 6;
                $j = $i + 12;
                if ($j == 16) {
                    $j = 5;
                }
                $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
            }
            $tmp = chr(0).chr(0).$bin[11].$tmp;
            $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
            "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
            return "$"."apr1"."$".$salt."$".$tmp;
        }
    }
