<?php 

$debug = false;

function first_word($str)
{
	$space = mb_strpos($str, " ");
	if ($space !== false)
		return mb_substr($str, 0, $space);
	else
		return $str;
}

function last_word($str)
{
	$space = mb_strrpos($str, " ");
	if ($space !== false)
		return mb_substr($str, $space + 1);
	else
		return $str;
}

function remove_first_word($str)
{
	$space = mb_strpos($str, " ");
	if ($space !== false)
		return mb_substr($str, $space);
	else
		return "";
}

function remove_last_word($str)
{
	$space = mb_strrpos($str, " ");
	if ($space !== false)
		return mb_substr($str, 0, $space);
	else
		return "";
}

$sentence_ends = array(".", "!", "?");

function sentence_count($str)
{
	$count = 0;
	foreach ($GLOBALS["sentence_ends"] as $end_char)
		$count += substr_count($str, "$end_char  ");
	// Add 1 more for the final sentence.
	return $count + 1;
}

function delete_spoken_script($spoken_id)
{
	// TODO: Delete all the (dead) symlinks that originated from partial files
	$results = get_fields("File, PublicMP3, FileWithoutMusic, WithoutMusicPublicMP3", "SpokenScripts", "SpokenID = $spoken_id");
	if ($results !== NULL) {
		if ($results["File"] != "") {
			unlink($results["File"]);
			// This is needed, or we leak the above file due to links pointing at it
			symlink(dirname(__FILE__) . "/../public_html/SecondOfSilence.mp3", $results["File"]);
		}
//		if ($results["PublicMP3"] != "")
//			unlink(dirname(__FILE__) . "/../public_html/" . $results["PublicMP3"]);
		if ($results["FileWithoutMusic"] != "" && $results["FileWithoutMusic"] != $results["File"]) {
			unlink($results["FileWithoutMusic"]);
			// This is needed, or we leak the above file due to links pointing at it
			symlink(dirname(__FILE__) . "/../public_html/SecondOfSilence.mp3", $results["FileWithoutMusic"]);
		}
//		if ($results["WithoutMusicPublicMP3"] != "" && $results["WithoutMusicPublicMP3"] != $results["PublicMP3"])
//			unlink(dirname(__FILE__) . "/../public_html/" . $results["WithoutMusicPublicMP3"]);
	}
	set_field("Deleted = True", "SpokenScripts", "SpokenID = $spoken_id");
}

function get_extension($name)
{
	return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

function make_generic_link($file)
{
	$ext = get_extension($file);
	$link = tempnam(dirname(__FILE__) . "/../public_html/script_links", "file_" . rand_string(20)) . ".$ext";
	symlink($file, $link);
	// TODO: use strstr()
	return substr($link, strpos($link, "script_links"));	
}

function make_public_link($spoken_id, $script_id, $file)
{
	// Make the files accessible through symlinks in public_html directory

	if ($script_id != -1)
		$script_name = get_field("ScriptName", "Scripts", "ScriptID = $script_id", $exists);
	else if ($spoken_id != -1)
		$script_name = get_field("ScriptName", SPOKEN_SCRIPTS_INNER_JOIN, "SpokenID = $spoken_id", $exists);
	else
		$script_name = NULL;
		
	if ($script_name == NULL)
		$script_name = "Screenplay";
	$script_name_ext = pathinfo($script_name, PATHINFO_EXTENSION);
	$filename = urlencode(basename($script_name, ".$script_name_ext"));
	
	$ext = get_extension($file);
	$filename = make_alpha_numeric($filename);
	$link = tempnam(dirname(__FILE__) . "/../public_html/script_links", "${filename}_" . rand_string(20)) . ".$ext";
	symlink($file, $link);
	return substr($link, strpos($link, "script_links"));
}

function make_script_links($spoken_id, $file, $contains_music, $pages, $current_length, $final = true)
{
	
	// Avoid deleting the file we're saving to the DB!
	dont_delete_tmp($file);

	$old_mp3 = NULL;
	$old_mp3_10 = NULL;
	$old_ogg = NULL;

	// Don't leave around unreferenced, unneeded output files.
	$results = get_fields("File, FileOgg, PublicMP3, PublicOgg, FileWithoutMusic, File_10, FileWithoutMusic_10", "SpokenScripts", "SpokenID = $spoken_id");
	if ($results !== NULL && $results["PublicMP3"] != "") {

		// Don't delete if the new one contains music, and the old one didn't. Save.
		if (!$contains_music || $results["File"] != $results["FileWithoutMusic"]) {
			$old_mp3 = $results["File"];
		}
		if (!$contains_music || $results["File_10"] != $results["FileWithoutMusic_10"]) {
			$old_mp3_10 = $results["File_10"];
		}
			
		if ($results["FileOgg"] != "")
			$old_ogg = $results["FileOgg"];

		//if ($results["PublicMP3"] != "")
			// dirname(__FILE__) . "/../public_html/" . $results["PublicMP3"];
		//if ($results["PublicOgg"] != "")
			// dirname(__FILE__) . "/../public_html/" . $results["PublicOgg"];

	}

	$public_mp3 = make_public_link($spoken_id, -1, $file);
	
	$first_10_file = "";
	
	// Create a 10-page version of this mp3. Chop it and save.
	if ($final) {
		// Note that page "12" is actually 11, because of the title page
		$time_start_of_11 = get_field("StartTime", SPOKEN_SCRIPT_OBJECTS_INNER_JOIN, "SpokenID = $spoken_id AND PageNumber > 11", $dummy, "StartTime");
		if ($time_start_of_11 !== NULL) {
			$file_10 = get_clip($file, 0, $time_start_of_11, false);
			dont_delete_tmp($file_10);
			$public_mp3_10 = make_public_link($spoken_id, -1, $file_10);
		} else {
			// There's nothing beyond page 10, so just use the original file
			$time_start_of_11 = $current_length;
			$file_10 = $file;
			$public_mp3_10 = $public_mp3;
		}
		
		$first_10_file = "Length_10 = $time_start_of_11, File_10 = '$file_10', PublicMP3_10 = '$public_mp3_10', ";
		if (!$contains_music)
			$first_10_file .= "FileWithoutMusic_10 = '$file_10', WithoutMusicPublicMP3_10 = '$public_mp3_10', ";
	}

	$file_without_music = ($contains_music ? "" : " FileWithoutMusic = '$file', WithoutMusicPublicMP3 = '$public_mp3', ");
	$pages_completed = ($pages == -1 ? "" : " PagesCompleted = $pages, ");
	$length = ($current_length == -1 ? "" : " Length = $current_length, ");
	
	set_field("File = '$file', $file_without_music $length $pages_completed $first_10_file" .
			  "DateCompleted = Now(), PublicMP3 = '$public_mp3'",
			  "SpokenScripts", "SpokenID = $spoken_id");

	// Keep the old file still available, in case someone has an old reference
	if ($old_mp3 != NULL && $old_mp3 != $file) {
		unlink($old_mp3);
		symlink($file, $old_mp3);

	}

	if ($old_mp3_10 != NULL && $old_mp3_10 != $file_10 && $old_mp3_10 != $old_mp3) {
		unlink($old_mp3_10);
		symlink($file_10, $old_mp3_10);
	}
	
	// Don't bother with .ogg files. They're a waste of space.
	// I have seen 0 users on Firefox without flash thus far
	$use_ogg = false;
	if ($final && $use_ogg) {
		// For the final version, make a .ogg file for Firefox HTML5
		$file_ogg = mp3_to_ogg($file, false);
		dont_delete_tmp($file_ogg);

		$public_ogg = make_public_link($spoken_id, -1, $file_ogg);

		$file_without_music = ($contains_music ? "" : " FileWithoutMusicOgg = '$file_ogg', WithoutMusicPublicOgg = '$public_ogg', ");
		set_field("FileOgg = '$file_ogg', $file_without_music PublicOgg = '$public_ogg'",
				  "SpokenScripts", "SpokenID = $spoken_id");

		// Keep the old file still available, in case someone has an old reference
		if ($old_ogg != NULL && $old_ogg != $file_ogg)
			`rm $old_ogg && ln $file_ogg $old_ogg`;
	} else
		set_field("FileOgg = '', PublicOgg = ''", "SpokenScripts", "SpokenID = $spoken_id");

}

function get_character_rehearsal_str($characters)
{
	return implode("____", $characters);
}




function get_character_script_object_where($safe_character)
{
	if ($safe_character == "*Narrator*")
		$where = "Type <> 'Dialog' AND Type <> 'Slugline' AND Type <> 'Shot' AND Type <> 'Act'";
	elseif ($safe_character == "*Sluglines*")
		$where = "Type = 'Slugline' OR Type = 'Shot' OR Type = 'Act'";
	else
		$where = "Type = 'Dialog' AND Content = '$safe_character'";
	return "($where)";
}

function write_as_format_from_objects($objects, $format, $header_footer)
{
	$output_file = get_tmp($format);
	switch ($format) {
		case "fountain":
			write_fountain_file($objects, $output_file);
			break;
		default:
			write_log("write_as_format() ERROR! Tried format: $format");
			break;
	}
	
	return $output_file;
}
