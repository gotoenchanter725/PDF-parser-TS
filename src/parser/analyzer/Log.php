<?php

function check($cond = false, $message = "", $level = 10)
{
	if (!$cond) {
		$msg = "Warning";
		if ($level < 10)
			$msg = "Error";

		print("$msg: $message\n");

		if ($level < 10)
			exit(-1);	
	}
}

function warn($message)
{
	check(false, $message);
}
