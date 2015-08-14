<?php

my_init();

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
	header("Location: https://" . $_SERVER['SERVER_NAME']);
} elseif (isset($_POST['username']) && isset($_POST['password'])) {
	my_session_login($_POST['username'], $_POST['password']);
} elseif (isset($_GET['logout'])) {
	my_session_logout();
} elseif (my_session_valid()) {

	$path = $GLOBALS['config']['my_file_path'];
	if (isset($_GET['c'])) { $path = my_validate_path($_GET['c']); }
	if (isset($_GET['p'])) { $path = my_validate_path($_GET['p']); }
	if (is_file($path) && !is_link($path)) {
		if (isset($_GET['c'])) {
			show_checksum($path);
		} else {
			show_download($path);
		}
	} elseif(is_dir($path)) {
		show_files($path);
	} else {
		header("HTTP/1.0 404 Not Found");
		show_error("404 - Not found"); 
	}

} else {
	show_login();
}

exit;

################################################################################

function my_init() {
	if (is_readable('../depot.conf')) {
		$GLOBALS['config'] = parse_ini_file('../depot.conf');
	} else {
		show_error('Could not read config file.');
	}
	ini_set('session.use_cookies', 1);
	ini_set('session.use_only_cookies', 1);
	session_name($GLOBALS['config']['session_name']);
	session_set_cookie_params(0, '/', $GLOBALS['config']['my_host_name'], true, true);
	session_start();
	if (mt_rand(0, 4) === 0) { session_regenerate_id(); }
}

function my_session_login($username, $password) {
	$domain = $GLOBALS['config']['my_ad_domain'];
	if (strpos($username, "\\")) { list($domain, $username) = explode("\\", $username, 2); }
	$domain   = preg_replace("/[^0-9A-Za-z \-\.]/", "", $domain);
	$username = preg_replace("/[^0-9A-Za-z \-\.]/", "", $username);
	$ldap = ldap_connect($GLOBALS['config']['my_ad_server']);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	$bind = @ldap_bind($ldap, $domain . "\\" . $username, $password);
	if (!$bind) { show_error("Invalid username and/or password."); }
	$result = ldap_search($ldap, $GLOBALS['config']['my_ad_basedn'], "(sAMAccountName=$username)");
	$info = ldap_get_entries($ldap, $result);
	@ldap_close($ldap);
	if ($info['count'] != 1) { show_error("Account not found."); }
	$_SESSION['username']   = my_encrypt($info[0]["samaccountname"][0]);
	$_SESSION['fullname']   = my_encrypt($info[0]["displayname"][0]);
	$_SESSION['last_seen']  = my_encrypt(time());
	$_SESSION['user_agent'] = my_encrypt($_SERVER['HTTP_USER_AGENT']);
	$action = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
	header("Location: " . $action);
}

function my_session_logout() {
	$_SESSION = array();
	setcookie($GLOBALS['config']['session_name'], '', time() - 42000, '/', $GLOBALS['config']['my_host_name'], true, true);
	session_destroy();
	header("Location: /");
}

function my_session_valid() {
	if (!isset($_SESSION['username']))   { return false; }
	if (!isset($_SESSION['fullname']))   { return false; }
	if (!isset($_SESSION['last_seen']))  { return false; }
	if (!isset($_SESSION['user_agent'])) { return false; }
	$username   = my_decrypt($_SESSION['username']);
	$fullname   = my_decrypt($_SESSION['fullname']);
	$last_seen  = my_decrypt($_SESSION['last_seen']);
	$user_agent = my_decrypt($_SESSION['user_agent']);
	if (!is_int($last_seen) && !ctype_digit($last_seen)) { return false; }
	if ($last_seen == '' || $last_seen < 0 || time() - $last_seen > $GLOBALS['config']['session_ttl']) { return false; }
	if ($user_agent == $_SERVER['HTTP_USER_AGENT']) { return true; }
	return false;
}

function my_encrypt($plaintext) {
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	$key = hash('md5', $GLOBALS['config']['session_key']);
	$hash = hash('md5', $plaintext);
	$plaintext = $hash . $plaintext;
	$ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $plaintext, MCRYPT_MODE_CBC, $iv);
	$ciphertext = base64_encode($iv . $ciphertext);
	return $ciphertext;
}

function my_decrypt($ciphertext) {
	$ciphertext = base64_decode($ciphertext);
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	$iv = substr($ciphertext, 0, $iv_size);
	$ciphertext = substr($ciphertext, $iv_size);
	$key = hash('md5', $GLOBALS['config']['session_key']);
	$plaintext = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext, MCRYPT_MODE_CBC, $iv);
	$plaintext = rtrim($plaintext, "\0"); # leftover from base64 encoding
	$hash = substr($plaintext, 0, 32);
	$plaintext = substr($plaintext, 32);
	if ($hash == hash('md5', $plaintext)) { return $plaintext; }
	return false;
}

function my_directory_list($dir) {
	$results = array();
	$dir = realpath($dir); # returns false on error, e.g. doesn't exist
	if ($dir && is_dir($dir)) {
		$dir = rtrim($dir, DIRECTORY_SEPARATOR); 
		$files = scandir($dir);
		sort($files);
		if (my_relative_path($dir) != "") {
			$results[] = my_file_info($dir . DIRECTORY_SEPARATOR . '..');
		}
		foreach ($files as $file) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if (substr($file, 0, 1) != '.' && !is_link($path)) {
				if (is_dir($path) || is_file($path)) {
					$results[] = my_file_info($path);
				}
			}
		}
	}
	return $results;
}

function my_file_info($file) {
	$abs_path = realpath($file);
	$rel_path = my_relative_path($abs_path);
	$name = basename($file);
	$size = '-';
	if (is_file($abs_path)) {
		$size = filesize($abs_path);
		$unit = " bytes";
		if ($size > 1024) { $size = floor($size / 1024); $unit = " KB"; }
		if ($size > 1024) { $size = floor($size / 1024); $unit = " MB"; }
		if ($size > 1024) { $size = floor($size / 1024); $unit = " GB"; }
		$size = $size . $unit;
	}
	$mod = date('Y-m-d H:i:s', filemtime($abs_path));
	$dir = is_dir($abs_path) ? true : false;
	$info = array(
		"abs_path" => $abs_path,
		"rel_path" => $rel_path,
		"name"     => $name, 
		"size"     => $size, 
		"mod"      => $mod, 
		"dir"      => $dir
	);
	return $info;
}

function my_relative_path ($path) {
	$dir  = explode(DIRECTORY_SEPARATOR, rtrim($GLOBALS['config']['my_file_path'], DIRECTORY_SEPARATOR));
	$file = explode(DIRECTORY_SEPARATOR, $path);
	while ($dir && $file && $dir[0] == $file[0]) {
		array_shift($dir);
		array_shift($file);
	}
	$path = implode(DIRECTORY_SEPARATOR, $file);
	return $path;
}

function my_validate_path ($path) { # sanitize path coming from URL
	$path = $GLOBALS['config']['my_file_path'] . DIRECTORY_SEPARATOR . $path;
	$path = realpath($path);
	$root_size = strlen($GLOBALS['config']['my_file_path']);
	$path_part = substr($path, 0, $root_size);
	if ($path_part == $GLOBALS['config']['my_file_path']) { return $path; }
	return false;
}

function show_header() {
	$title = isset($GLOBALS['config']['my_site_name']) ? $GLOBALS['config']['my_site_name'] : '';
	echo "<!DOCTYPE html>\n";
	echo "<html>\n";
	echo "\t<title>" . $title . "</title>\n";
	echo "\t<link rel='stylesheet' type='text/css' href='/css/bootstrap-3.3.5-dist/css/bootstrap.min.css'>\n";
	echo "\t<link rel='stylesheet' type='text/css' href='/css/bootstrap-3.3.5-dist/css/bootstrap-theme.min.css'>\n";
	echo "\t<link rel='stylesheet' type='text/css' href='/css/font-awesome-4.4.0/css/font-awesome.min.css'>\n";
	echo "\t<link rel='stylesheet' type='text/css' href='/css/google-fonts.open-sans.css'>\n";
	echo "\t<link rel='stylesheet' type='text/css' href='/css/depot.css'>\n";
	echo "\t<script src='/js/jquery-2.1.4.min.js'></script>\n";
	echo "\t<script src='/js/depot.js'></script>\n";
	echo "\t<script src='/css/bootstrap-3.3.5-dist/js/bootstrap.min.js'></script>\n";
	echo "\t<style type='text/css'>\n";
	echo "\t</style>\n";
	echo "<body>\n";
	echo "<!--=========================================================================-->\n\n";
	echo "<div class='container'>\n";
}

function show_footer() {
	echo "</div>\n\n";
	echo "<!--=========================================================================-->\n";
	echo "</body>\n";
	echo "</html>";
}

function show_error($msg = 'An unknown error has occured.') {
	show_header();
	echo "<h1>Error</h1>\n";
	echo "<div>" . htmlentities($msg) . "</div>\n";
	show_footer();
	exit; # IMPORTANT!  Die on error!
}

function show_login() {
	show_header();
	$action = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	echo "<form class='form-signin' action='" . $action . "' method='POST'>\n";
	echo "<h2 class='form-signin-heading'>" . $GLOBALS['config']['my_site_name'] . "</h2>\n";
	echo "<label for='username' class='sr-only'>Username</label>\n";
	echo "<input type='text' id='username' name='username' class='form-control' placeholder='Username' required autofocus>\n";
	echo "<label for='inputPassword' class='sr-only'>Password</label>\n";
	echo "<input type='password' id='password' name='password' class='form-control' placeholder='Password' required>\n";
	echo "<button class='btn btn-lg btn-primary btn-block' type='submit'>Sign in <i class='fa fa-sign-in'></i></button>\n";
	echo "<div class='small'><b>NOTE:</b> Cookie support required to sign in.</div>\n";
	echo "</form>\n";
	show_footer();
}

function show_files ($dir) {
	ini_set('max_execution_time', 120);
	show_header();
	$_SESSION['last_seen'] = my_encrypt(time());
	$username = my_decrypt($_SESSION['username']);
	$fullname = my_decrypt($_SESSION['fullname']);
	echo "<div class='row'>\n";
	echo "\t<h1 class='col-md-7'><a href='/'>" . htmlentities($GLOBALS['config']['my_site_name']) . "</a></h1>\n";
	echo "\t<div class='col-md-5 text-right my-userinfo'>\n";
	echo "\t\t"  . htmlentities($fullname) . " \n";
	echo "\t\t(" . htmlentities($username) . ") \n";
	echo "\t\t<a class='btn btn-primary' href='/?logout'>Sign out <i class='fa fa-sign-out'></i></a>\n";
	echo "\t</div>\n";
	echo "</div>\n";
	echo "<table class='table table-hover my-filelist'>\n";
	echo "<thead>\n";
	echo "<tr>\n";
	echo "\t<th class='col-md-7'>FILE NAME</th>\n";
	echo "\t<th class='col-md-2 text-right'>FILE SIZE</th>\n";
	echo "\t<th class='col-md-3'>LAST MODIFIED</th>\n";
	echo "</tr>\n";
	echo "</thead>\n";
	echo "<tbody>\n";
	$files = my_directory_list($dir);
	foreach ($files as $file) {
		echo "<tr>\n";
		$icon = "fa-file-o";
		if ($file['dir']) {
			$icon = 'fa-folder-o';
			if ($file['name'] == '..') { 
				$icon = 'fa-level-up'; 
				$file['name'] = "Parent Directory";
			}
		}
		echo "\t<td>";
		if (!$file['dir']) { echo "<a href='/?c=" . urlencode($file['rel_path']) . "'>"; }
		echo "<i class='fa fa-fw " . $icon . "'></i>";
		if (!$file['dir']) { echo "</a>"; }
		echo " <a class='my-filelink' href='/?p=" . urlencode($file['rel_path']) . "'>";
		echo htmlentities($file['name']) . "</a>";
		echo "</td>\n";
		echo "\t<td class='text-right'>" . htmlentities($file['size']) . "</td>\n";
		echo "\t<td>"                    . htmlentities($file['mod'])  . "</td>\n";
		echo "</tr>\n";
	}
	echo "</tbody>\n";
	echo "</table>\n";
	show_footer();
}

function show_download($path) {
	// turn off compression on the server
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 'Off');
	$path_parts = pathinfo($path);
	$file_name  = $path_parts['basename'];
	$file_ext   = $path_parts['extension'];
	$file_size  = filesize($path);
	$file       = @fopen($path, "rb");
	if ($file) {
		// set the headers, prevent caching
		header("Pragma: public");
		header("Expires: -1");
		header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
		// set appropriate headers for attachment or streamed file
		if (isset($_REQUEST['stream'])) {
			header('Content-Disposition: inline;');
		} else {
			header("Content-Disposition: attachment; filename=\"$file_name\"");
		}
		// set the mime type based on extension, add yours if needed.
		$content_types = array(
			"avi"    => "video/x-msvideo",
			"class"  => "application/java-vm",
			"css"    => "text/css",
			"dll"    => "application/octet-stream",
			"exe"    => "application/x-msdownload",
			"idx"    => "application/octet-stream",
			"iso"    => "application/octet-stream",
			"java"   => "text/x-java-source",
			"jpg"    => "image/jpeg",
			"jpeg"   => "image/jpeg",
			"js"     => "application/javascript",
			"json"   => "application/json",
			"jsp"    => "application/octet-stream",
			"md5"    => "text/plain",
			"mp3"    => "audio/mpeg",
			"mpg"    => "video/mpeg",
			"msi"    => "application/octet-stream",
			"pack"   => "application/octet-stream",
			"pdb"    => "application/octet-stream",
			"png"    => "image/png",
			"prefs"  => "application/octet-stream",
			"ps1"    => "text/plain",
			"rpm"    => "application/octet-stream",
			"sha256" => "text/plain",
			"sql"    => "text/plain",
			"txt"    => "text/plain",
			"xml"    => "application/xml",
			"zip"    => "application/zip",
		);
		$ctype = isset($content_types[$file_ext]) ? $content_types[$file_ext] : "application/octet-stream";
		header("Content-Type: " . $ctype);
 		// check if http_range is sent by browser (or download manager)
		$seek_start = '';
		$seek_end   = '';
		if (isset($_SERVER['HTTP_RANGE'])) {
			list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if ($size_unit == 'bytes') {
				// http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
				// multiple ranges could be specified at the same time, 
				// but for simplicity only serve the first range. then
				// figure out download piece from range
				list($range, $extra_ranges)  = explode(',', $range_orig, 2);
				list($seek_start, $seek_end) = explode('-', $range, 2);
			} else {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				show_error("416 - Requested range not satisfiable");
			}
		}
		// set start and end based on range (if set), else set defaults
		// also check for invalid ranges.
		$seek_end   = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)), ($file_size - 1));
		$seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);
		// Only send partial content header if downloading a piece of the file (IE workaround)
		if ($seek_start > 0 || $seek_end < ($file_size - 1)) {
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $file_size);
			header('Content-Length: ' . ($seek_end - $seek_start + 1));
		} else {
			header("Content-Length: $file_size");
		}
		header('Accept-Ranges: bytes');
		set_time_limit(0);
		fseek($file, $seek_start);
		while(!feof($file)) {
			print(@fread($file, 1024 * 8));
			ob_flush();
			flush();
			if (connection_status() != 0) { @fclose($file); exit; }
		}
 		// file save was a success
		@fclose($file);
		exit;
	}
	// file couldn't be opened
	header("HTTP/1.0 500 Internal Server Error");
	show_error("500 - Internal server error");
}

function show_checksum($path) {
	show_header();
	$_SESSION['last_seen'] = my_encrypt(time());
	$username = my_decrypt($_SESSION['username']);
	$fullname = my_decrypt($_SESSION['fullname']);
	echo "<div class='row'>\n";
	echo "\t<h1 class='col-md-7'><a href='/'>" . htmlentities($GLOBALS['config']['my_site_name']) . "</a></h1>\n";
	echo "\t<div class='col-md-5 text-right my-userinfo'>\n";
	echo "\t\t"  . htmlentities($fullname) . " \n";
	echo "\t\t(" . htmlentities($username) . ") \n";
	echo "\t\t<a class='btn btn-primary' href='/?logout'>Sign out <i class='fa fa-sign-out'></i></a>\n";
	echo "\t</div>\n";
	echo "</div>\n";
	echo "<div class='panel panel-default'>\n";
	echo "\t<div class='panel-heading'>" . basename($path) . "</div>\n";
	echo "\t<table class='table'>\n";
	echo "\t<tbody>\n";
	echo "\t\t<tr><td class='col-md-1'>MD5</td><td class='col-md-11'>"  . hash_file('md5',  $path) . "</td></tr>\n";
	echo "\t\t<tr><td class='col-md-1'>SHA1</td><td class='col-md-11'>" . hash_file('sha1', $path) . "</td></tr>\n";
	echo "\t</tbody>\n";
	echo "\t</table>\n";
	echo "</div>\n\n\n\n\n";
	show_footer();
}
