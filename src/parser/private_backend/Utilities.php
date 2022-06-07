<?php

/******** LOGGING ********/

// Gets overridden, depending on use
$log = "general";

function get_log_file($log_name)
{
	$files_path = dirname(__FILE__) . "/"; // . "/../logs/";
	return $files_path . $log_name . ".log";
}

function always_write_log($str, $type=NULL)
{
    // $user_id = get_user_id();
    if ($type == "user_action") {
        $str = date("d/M/Y:H:i:s O") . " - " . $str;
        $log = fopen(dirname(__FILE__) . "/../user_action.log", 'a');
    }

    if ($type==NULL) {
        $str = date("F j, Y, g:i a T") . " - " . $str;
        // if ($user_id == 88057 || $user_id == 4937 || $user_id == 5847) {
        //     $log = fopen(dirname(__FILE__) . "/../logs/dev.log", 'a');
        // } else {
            $log = fopen(get_log_file($GLOBALS["log"]), 'a');
        //}
    }

	fwrite($log, $str . "\n");
	fclose($log);
}

function write_log($str)
{
    always_write_log($str);
}

function write_log_user_action($str, $ab_groups, $user_id, $date_joined, $is_pro, $count_projects) {
    #NOTE:
    // could add number of collaborators
    // could add data about logins

    // ab testing data
    $test_groups_str = "";
    foreach ($ab_groups as $value) {
        if (!empty($test_groups_str)) {
            $test_groups_str = $test_groups_str . " ";
        }
        $test_groups_str = $test_groups_str . $value["TestKey"] . ":" . $value["TestGroup"];
    }

    $session = $GLOBALS["_SESSION"];
    $tracking_id = $session["TrackingID"];
    // $results = get_fields("WDPro, DateJoined", "Users", "UserID = $user_id");
    // $is_pro = filter_var($results["WDPro"], FILTER_VALIDATE_BOOLEAN);
    // $count_projects = get_field("COUNT(*)", "Projects", "UserID = $user_id And OfflineTemp != 1", $dummy);
    // $date_joined = $results["DateJoined"];
    $log = $user_id . " - " . ($is_pro ? "true" : "false") . " - " . $count_projects . " - " . $date_joined . " - " . $tracking_id . " - " . $str . " - " . $test_groups_str;
    always_write_log($log, "user_action");
}

$debug_level = 0;
function write_log_db_high($str)
{
	if ($GLOBALS["debug_level"] > 0)
		write_log("DEBUG: $str");
}

function caller_string($level = 1)
{
	$trace = debug_backtrace();
	if (count($trace) > $level + 1) {
		$call = $trace[$level + 1];
		$caller = $call["function"];
		$line = $call["line"];
		$file = $call["file"];
		return "$caller on line $line of $file";
	} else
		return "No caller";
}


function convenient_assumption($bool)
{
	if ($GLOBALS["debug_level"] > 0) {
		write_log("Convenient assumption failed!");
	}
}

function assert_handler($file, $line, $code)
{
	write_log("Assert: $file, $line, $code from: " . caller_string(2));
}

function error_handler($errno, $errstr, $errfile, $errline)
{
	$u_errfile = strtoupper($errfile);
	if (is_suffix($u_errfile, "MAIL.PHP") || is_suffix($u_errfile, "PEAR.PHP") || is_suffix($u_errfile, "SMTP.PHP") || is_suffix($u_errfile, "VOICEFORGE.PHP"))
		// Don't care about this error which I can't do anything about, in Pear's mail files
		return;
	write_log("Error: $errno, $errstr, $errfile, $errline from: " . caller_string(2));
}

// Set up the callback
assert_options(ASSERT_CALLBACK, "assert_handler");
set_error_handler("error_handler");


/******** LOCKING ********/

define("MAX_FLOCK_TRIES", 1000);
function unlock($lock_file_pointer)
{
	assert($lock_file_pointer != NULL);
	return flock($lock_file_pointer, LOCK_UN);
}

function lock($lock_file_pointer, $wait = true, $wait_time = 100000, $lock_type = LOCK_EX)
{
	assert($lock_file_pointer != NULL);

	$start_time = microtime();
	$count = 0;

	if (!$wait)
		$lock_type |= LOCK_NB;

    while ($count < MAX_FLOCK_TRIES && !flock($lock_file_pointer, $lock_type)) {
    	if (!$wait) {
    		$count = MAX_FLOCK_TRIES;
    		break;
    	}
    	$count++;
        // Sleep and try again... you had better be able to get it eventually...
		usleep($wait_time);
    }
    if ($count >= MAX_FLOCK_TRIES) {
    	write_log("Locking file failed");
		return false;
	}
	return true;
}

// Get a shared lock, with default parameters
function lock_shared($lock_file_pointer)
{
	lock($lock_file_pointer, true, 100000, LOCK_SH);
}


function get_lock_file($lock_id)
{
	return dirname(__FILE__) . "/../locks/${lock_id}.txt";
}

function create_lock($lock_id, $wait = true, $wait_time = 100000, $lock_type = LOCK_EX)
{
	$lock_file = get_lock_file($lock_id);
	$lock_fp = fopen($lock_file, "w");
	if ($lock_fp == NULL)
		return NULL;

	if (!lock($lock_fp, $wait, $wait_time, $lock_type)) {
		write_log("Couldn't create lock $lock_file");
		return NULL;
	}

	return $lock_fp;
}

// Get a shared lock, with same default parameters
function create_lock_shared($lock_id, $wait = true, $wait_time = 100000)
{
	create_lock($lock_id, $wait, $wait_time, LOCK_SH);
}


function release_lock($lock_fp)
{
	// You may NOT call this unless we already locked it
	assert($lock_fp != NULL);

	if (!unlock($lock_fp))
		return false;

	if (!fclose($lock_fp))
		return false;

	return true;
}

function delete_lock($lock_id, $lock_fp)
{
	if (!release_lock($lock_fp))
		return false;

	$lock_file = get_lock_file($lock_id);
	unlink($lock_file);
}

/******** Session Management *********/

function playground_check(&$var)
{
	if ($GLOBALS["is_playground"] && $var == "user_id")
		$var = "playground_user_id";
}

function set_session($var, $value)
{
	playground_check($var);
	$GLOBALS["_SESSION"][$var] = $value;
}

function unset_session($var)
{
	playground_check($var);
	unset($GLOBALS["_SESSION"][$var]);
}

function get_session($var)
{
	playground_check($var);
	if (array_key_exists($var, $GLOBALS["_SESSION"]))
		return $GLOBALS["_SESSION"][$var];
	else
		return NULL;
}

function get_session_fingerprint()
{
	$fingerprint = 'FINGER' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "");
	return md5($fingerprint . session_id());
}

function get_session_variable($var)
{
    // Fingerprint based on User Agent is bad. Chrome will auto-update, which changes the User Agent too frequently!
	if (true || get_session("fingerprint") == get_session_fingerprint())
		return get_session($var);
	else
		return NULL;
}

/***** Forms ******/

function get_data($str)
{
	return $_GET[$str];
}

function rand_string($length)
{
    $str = "";
    $chars = "23456789ABCDEFGHJKMNPQRSTUVWXYZ";
    for ($p = 0; $p < $length; $p++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

function get_unique_code($code_field, $table, $length = 8, $additional_where = "")
{
    if ($additional_where != "")
        $additional_where = "AND ($additional_where)";
	$code = "";
	while ($code == "") {
		$code = rand_string($length);
		if (get_field($code_field, $table, "$code_field = '$code' $additional_where", $dummy) !== NULL)
			$code = "";
	}
	return $code;
}



function replace_special_with_underscore($str)
{
	return preg_replace("/[^A-Za-z0-9]/", "_", $str);
}

function translate($text, $email = NULL)
{
	global $translate_headings_php;

	$language = get_language_to_email($email);

	if (!isset($translate_headings_php[$language]))
		return $text;

	if (isset($translate_headings_php[$language][$text]))
		return ($translate_headings_php[$language][$text]);
	else
		return translate_lang_fileoutphp($language, $text);

}

function translate_lang_fileoutphp($language, $text)
{
	$translate_languages = array("chinese" => "zh-CN", "german" => "de", "spanish" => "es");
	$target = $translate_languages[$language];
	//translate_text(text, "de", "en")



//function unsafe_translated_chin($unsafe_text) {
//    translate_text($unsafe_text, "zh-CN", "en");
//}

//function unsafe_translated_chin($unsafe_text) {
//    translate_text($unsafe_text, "de", "en");
//}

//$unsafe_translated_chin = translate_text($unsafe_text, "zh-CN", "en");

//$unsafe_translated_germ = translate_text($unsafe_text, "de", "en");

	$result = translate_text($text, $target, "en");
	file_put_contents(dirname(__FILE__) . "/../needed_translations/php_$language.txt", "\"$text\"=>\"$result\",\n", FILE_APPEND);

	return $result;
}


