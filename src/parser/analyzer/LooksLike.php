<?php

function looks_like_transition($str) {
	if (!is_uppercase($str)) return FALSE;
	if (preg_match("/^FADE (IN|OUT|TO)/", $str)) return TRUE;
	if (preg_match("/^(JUMP |SLOW |WHIP )?(CUT|DISSOLVE|WIPE|PAN|PULL BACK) TO/", $str)) return TRUE;
	if (preg_match("/^(SMASH|SLAM) TO/", $str)) return TRUE;  // "Coffee and Cigarettes"
	if (preg_match("/^(SMASH|SLAM) CUT/", $str)) return TRUE;  // "Queen of the Dolls"
	if (preg_match("/^MUSIC BEGINS[:]?$/", $str)) return TRUE;  // "1492"
	if (preg_match("/^TRANSITION[.]?$/", $str)) return TRUE;  // "48 Hrs."
	return FALSE;
}

// Return TRUE if this slugline looks like something Celtx would consider
// a "scene heading", i.e., something introducing a new scene.
function looks_like_sceneheading($str) {
	if (preg_match("/^(IN|EX)TERIOR/", $str)) return TRUE;
	// [guyg] Added the / after INT, etc. because it could be INT/EXT
	// Also, allow a space character before the INT or whatever, because there could be a number first
	if (preg_match("#^(INT|EXT|I/E)[ ./]#", $str)) return TRUE;
	if (preg_match("/[^A-Za-z]CONTINUOUS$/", $str)) return TRUE;
	// "10 Things I Hate About You" uses sluglines without INT./EXT.,
	// with only vague place and time-of-day indications.
	static $times_of_day = array("DAY", "EVENING", "LATER", "MOMENTS LATER", "MORNING", "NIGHT", "SUNSET", "DAWN");
	foreach ($times_of_day as $time_of_day) {
		if (preg_match("/[^A-Za-z]$time_of_day$/", $str))
			return TRUE;
	}
	return FALSE;
}

function looks_like_slugline($str) {
	if (!is_uppercase($str)) return FALSE;
	if (looks_like_sceneheading($str)) return TRUE;
	if (preg_match("/^TITLE CARD/", $str)) return TRUE;
	if (preg_match("/^SERIES OF SHOTS/", $str)) return TRUE;
	if (preg_match("/^MONTAGE/", $str)) return TRUE;
	if (preg_match("/^SUPER:/", $str)) return TRUE;
	// [guyg] This seems questionable - TODO: test putting POV in name
	if (false && strpos($str, "POV") !== FALSE) return TRUE;
	
	// "10 Things I Hate About You" uses sluglines indicating
	// the camera's focus within a busier scene:
	// ON MICHAEL AND MANDELLA
	// [guyg] What if dialog was ON YOUR FACE, JERK! or something...
	// [guyg] Later: Yup, the above case happened! So could "BACK TO THE SHOW!" or something
	if (false && preg_match("/^ON /", $str)) return TRUE;
	if (false && preg_match("/^BACK TO /", $str)) return TRUE;
	if (false && $str == "ANOTHER ANGLE") return TRUE;

	return FALSE;
}

function looks_like_series_or_montage_slugline($str) {
	if (preg_match("/^SERIES OF SHOTS/", $str)) return TRUE;
	if (preg_match("/^MONTAGE/", $str)) return TRUE;
	return FALSE;
}

function looks_like_slugline_or_transition($str) {
	return looks_like_slugline($str) || looks_like_transition($str);
}

function looks_like_vo_or_os($str) {
	if (preg_match("/^m\. ?o\. ?s\.?$/i", $str)) return TRUE;
	if (preg_match("/^mos$/i", $str)) return TRUE;
	if (preg_match("/^vo$/i", $str)) return TRUE;
	if (preg_match("/^v\. ?o\.?$/i", $str)) return TRUE;
	if (preg_match("/^os$/i", $str)) return TRUE;
	if (preg_match("/^o\. ?s\.?$/i", $str)) return TRUE;
	if (preg_match("/^off[- ]?screen$/i", $str)) return TRUE;
	return FALSE;
}

function looks_like_parenthetical($str) {
	if ($str[0] == '(' && $str[strlen($str)-1] == ')' &&
			!looks_like_vo_or_os($str))
		return TRUE;
	return FALSE;
}

function contains_vo_os_notation($str) {
	if (preg_match("/\(vo\)/i", $str)) return TRUE;
	if (preg_match("/\(os\)/i", $str)) return TRUE;
	if (preg_match("/\(oc\)/i", $str)) return TRUE;
	if (preg_match("/\(v\. ?o.*\)/i", $str)) return TRUE;
	if (preg_match("/\(o\. ?s.*\)/i", $str)) return TRUE;
	if (preg_match("/\(o\. ?c.*\)/i", $str)) return TRUE;
	if (preg_match("/\(off[- ]?screen.*\)/i", $str)) return TRUE;
	return FALSE;
}

function contains_vo_os($str) {
	if (preg_match("/ vo$/i", $str)) return TRUE;
	if (preg_match("/ os$/i", $str)) return TRUE;
	if (preg_match("/ oc$/i", $str)) return TRUE;
	if (preg_match("/ v\. ?o.$/i", $str)) return TRUE;
	if (preg_match("/ o\. ?s.$/i", $str)) return TRUE;
	if (preg_match("/ o\. ?c.$/i", $str)) return TRUE;
	return FALSE;
}


function looks_like_character($str) {

	// [guyg] We can't rely on this. People mess up.
	// COULD ALSO HAVE NUMBERS!! (e.g. 638Z)
	// A character name needs to be all uppercase.
	//if (!preg_match("/^[A-Z][^a-z]+$/", $str)) return FALSE;
	
	// [guyg] Don't let character names begin with a (... it's probably a Paren
	if (mb_substr($str, 0, 1) == "(") return FALSE;

	// [guyg] Do this after the starts with ( check, in case the entire line is (O.S.)!
	if (contains_vo_os_notation($str)) return TRUE;

	// Also, watch out for emphatic text like "NO!"; character names never
	// end with punctuation, except for ":" in some (BAD STYLE) scripts.
	// Also, watch out for short dialog such as "J--".
	// [guyg] I removed :, because those bad style scripts matter!
	if (preg_match("/[-!?.;…]$/", $str) && !contains_vo_os($str)) return FALSE;
	// On a page with no dialog at all, we can think that a slugline might
	// be a character, and not be corrected by reclassify_based_on_indent().
	// So, let's be a little conservative: don't identify lines of more than
	// four words as character names right off the bat.
	// [guyg] That can happen, and other things may not catch it...
	// In my case, it was "X, Y, AND Z" talking. pdftotext messed up layout
	// so spacing didn't catch it exactly. I'll be aggressive and increase the number
	// *if* it's all-caps. Seems to cause confusion otherwise...
	$num = (is_uppercase($str) ? 6 : 3);
	if (substr_count($str, " ") >= $num) return FALSE;
	return TRUE;
}

function looks_like_the_end($str) {
	if (preg_match("/^T(HE|he) E(ND|nd)[.!?]?/", $str)) return TRUE;
	if (preg_match("/^END( AND CREDITS)?[.]?$/", $str)) return TRUE;
	if ($str == "(END)") return TRUE;
	return FALSE;
}

function looks_like_page_number($str) {
	if (preg_match("/^[0-9]+[A-Z]?\.?$/", $str)) return TRUE;
	if (preg_match("/^\([0-9]+[A-Z]?\.?\)$/", $str)) return TRUE;
	return FALSE;
}

function looks_like_scene_number($str) {
	if (preg_match("/^[0-9]+[A-Z]?\.?$/", $str)) return TRUE;
	return FALSE;
}

function looks_like_hrule($str) {
	if (strlen($str) > 10 && preg_match("/^[-]*$/", $str))
		return TRUE;
	return FALSE;
}

function looks_like_changebar($lines) {
	assert(is_array($lines));
	$symbol = $lines[0];
	if ($symbol != "*")
		return FALSE;
	foreach ($lines as $line) {
		if ($line != $symbol) return FALSE;
	}
	return TRUE;
}

function looks_like_contact_info($lines) {
	assert(is_array($lines));
	$found_ST_and_zipcode = FALSE;
	$found_phone_number = FALSE;
	foreach ($lines as $line) {
		if (preg_match("/[A-Z][A-Z],? [0-9]{5}(, USA)?$/", $line))
			$found_ST_and_zipcode = TRUE;
		if (preg_match("/[^0-9][0-9]{3}[-.][0-9]{3}[-.][0-9]{4}[^0-9]/", $line))
			$found_phone_number = TRUE;
	}
	return $found_ST_and_zipcode || $found_phone_number;
}

function contains_date($str) {
	if (preg_match("#^(.*[^0-9])?[0-9]?[0-9][-./][0-9]?[0-9][-./][0-9][0-9]([^0-9].*)?$#", $str)) return TRUE;
	if (preg_match("#^(.*[^0-9])?[0-9]?[0-9][-./][0-9]?[0-9][-./](19|20)[0-9][0-9]([^0-9].*)?$#", $str)) return TRUE;
	if (preg_match("#^(.*[^0-9])?(19|20)[0-9][0-9][-./][0-9]?[0-9][-./][0-9]?[0-9]([^0-9].*)?$#", $str)) return TRUE;
	return FALSE;
}
