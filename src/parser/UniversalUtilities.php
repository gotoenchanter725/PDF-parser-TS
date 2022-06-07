<?php

/*** Core functionality, may be used elsewhere (e.g. in Database.php below) ***/

function is_local()
{
	$host = gethostname();
	return ($host == "guy" || $host == "guy.local" || strpos($host, "MacBook-Pro") !== false || $host == "mbp.local" || strpos($host, "MBP"));
}

// Until this function exists in PHP...
function mb_my_strcasecmp($str1, $str2)
{
	return strcmp(mb_strtolower($str1), mb_strtolower($str2));
}

function is_suffix($str, $suffix, $case_sensitive = true)
{
	$str = mb_substr($str, -mb_strlen($suffix));
	return ($case_sensitive ? $suffix == $str : mb_my_strcasecmp($suffix, $str) == 0);
}

function remove_suffix($str, $suffix)
{
	return mb_substr($str, 0, -mb_strlen($suffix));
}

function is_prefix($str, $prefix, $case_sensitive = true)
{
	$str = mb_substr($str, 0, mb_strlen($prefix));
	return ($case_sensitive ? $prefix == $str : mb_my_strcasecmp($prefix, $str) == 0);
}

function remove_prefix($str, $prefix)
{
	return mb_substr($str, mb_strlen($prefix));
}

function is_uppercase($line)
{
	return (mb_convert_case($line, MB_CASE_UPPER, "UTF-8") === $line);
}
/*** End core functionality ***/


$tmp_dir = dirname(__FILE__) . "/../screenplaypen_tmp";

$shared_files_dir = (is_local() ? $tmp_dir : "/share/user_files/0317");

if (!isset($get_config_override))
	$get_config_override = array();

function get_config($name)
{
	if (isset($GLOBALS["get_config_override"][$name])) {
		return $GLOBALS["get_config_override"][$name];
	}

	if (true) {
		return "";
	};

//	file_put_contents("/Users/guyg42/foo.txt", "$name\n", FILE_APPEND);
	$files_path = dirname(__FILE__) . "/../screenplaypen_config";
//	if (substr($name, 0, 2) != "db")
//		write_log($name);
	return trim(file_get_contents("$files_path/$name.txt"));
}

function clean_share_path($tmp_file)
{
    $share_pos = strpos($tmp_file, "/share");
    if ($share_pos !== false) {
       $tmp_file = substr($tmp_file, $share_pos);
    }
    return $tmp_file;
}

function get_temp_file($prefix, $ext)
{
	$tmp_file = clean_share_path(tempnam($GLOBALS["shared_files_dir"], $prefix));
    // HACK for server symlink stupidness
	$tmp_file .= ".$ext";
	return $tmp_file;
}

function get_local_temp_file($prefix, $ext)
{
	$tmp_file = tempnam($GLOBALS["tmp_dir"], $prefix);
	$tmp_file .= ".$ext";
	return $tmp_file;
}



class RunInParallel {
	private $num;
	private $pids;

	function __construct($num)
	{
		$this->num = $num;
		$this->pids = array();
	}

	function check_remaining()
	{
		$remaining = array();
		foreach ($this->pids as $pid) {
			$result = `ps $pid`;
			if (strpos($result, "$pid") !== false)
				$remaining[] = $pid;

		}
		$this->pids = $remaining;
		return count($this->pids);
	}

	function wait_until_only($count)
	{
		while ($this->check_remaining() > $count) {
			usleep(100);
		}
	}
	function wait_until_done()
	{
		$this->wait_until_only(0);
	}

	function launch($cmd)
	{
		$this->wait_until_only($this->num - 1);

		$pid = trim(`$cmd > /dev/null 2>/dev/null & echo $!`);
		$this->pids[] = $pid;
	}

}

function reduce_spaces($str)
{
	return preg_replace("/[[:blank:]]+/", " ", $str);
}

function strip_html_chars($str)
{
	return preg_replace("/[<>]/", "", $str);
}

function make_alpha_numeric($str)
{
	return preg_replace("/[^A-Za-z0-9]/", "", $str);
}

function make_alpha($str)
{
	return preg_replace("/[^A-Za-z]/", "", $str);
}

function base64_url_encode($str)
{
	return strtr(base64_encode($str), "+/=", "-_,");
}

function base64_url_decode($str)
{
	return base64_decode(strtr($str, "-_,", "+/="));
}

function encrypt($str, $key)
{
	return bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $str, MCRYPT_MODE_CBC, md5(md5($key))));
}

function decrypt($str, $key)
{
	return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), pack("H*", $str), MCRYPT_MODE_CBC, md5(md5($key))), "\0");
}

/****** Real version exists in PHP 5.4, use when upgrading PHP on server *******/
function hex2bin_local($hex)
{
	$bin = "";
    $length = strlen($hex);
    for ($i = 0; $i < $length; $i += 2) {
        $bin .= chr(hexdec(substr($hex, $i, 2)));
    }

   return $bin;
}

