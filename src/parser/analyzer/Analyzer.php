<?php

$scenes = array();
$separate_continuous = false;

class Scene {

	private $scene_objects;
	private $num;
	private static $count = 0;

	private $sluglines;

	function __construct()
	{
		$this->sluglines = array();
		$this->scene_objects = array();
		$this->num = self::$count++;
		$GLOBALS["scenes"][$this->get_num()] = $this;

	}

	function get_sluglines() { return $this->sluglines; }
	function get_scene_objects() { return $this->scene_objects; }
	function get_num() { return $this->num; }
	
	function get_length()
	{
		$count = 0;
		foreach ($this->scene_objects as $scene_object)
			$count += $scene_object->get_num_lines();
		return $count;
	}
	
	function add_slugline($slugline)
	{
		$this->add_scene_object(new Slugline($slugline, $slugline->get_page_num()));
		$this->sluglines[] = $slugline;
	}
		
	function add_scene_object($scene_object)
	{
		$this->scene_objects[] = $scene_object;
	}

}

class SceneObject {
	
	protected $page_num;

	function get_page_num() { return $this->page_num; }
	
	function get_num_lines()
	{
		return 1;
	}
	
	function get_string()
	{
		return "";
	}

	function get_original()
	{
	    return "";
	}
}

class Slugline extends SceneObject {

	private $slugline;

	function __construct($slugline, $page_num)
	{
		$this->page_num = $page_num;
		$this->slugline = $slugline;
	}
	
	function get_string() { return $this->slugline->get_content(); }

}

class Shot extends SceneObject {

	private $shot;

	function __construct($shot, $page_num)
	{
		$this->page_num = $page_num;
		$this->shot = $shot;
	}
	
	function get_string() { return $this->shot->get_content(); }

}

class Act extends Shot {

}

class Text extends SceneObject {

	private $text;

	function __construct($text, $page_num)
	{
		$this->page_num = $page_num;
		$this->text = $text;
	}
	
	function get_string() { return $this->text->get_content(); }

}

// Same as Text, just special internal meaning
class Title extends Text { }
class Author extends Text { }

class Action extends SceneObject {

	private $action;
	
	function __construct($action, $page_num)
	{
		$this->page_num = $page_num;
		$this->action = $action;
	}

	function get_num_lines()
	{
		return $this->action->get_num_lines();
	}
	
	function get_string()
	{
		return $this->action->get_content();
	}
}

class Dialog extends SceneObject {

	private $character;
	private $modifier;
	private $original;
	private $has_dual_line;
	private $is_dual_line;

	// List of parentheticals/dialog objects for this speech.
	private $objects;
	
	function __construct($character, $modifier, $original, $page_num, $has_dual_line, $is_dual_line)
	{
	    // Remove Final Draft's character CONT'D
	    if ($modifier == "CONT'D" || $modifier == "CONT’D")
	        $modifier = "";
	    
		$this->page_num = $page_num;
		$this->character = $character;
		$this->modifier = $modifier;
		$this->original = $original;
		$this->has_dual_line = $has_dual_line;
		$this->is_dual_line = $is_dual_line;
		
		
		$this->objects = array();
		
		$character->add_dialog($this);
	}
	
	function get_character() { return $this->character; }
	function get_modifier() { return $this->modifier; }
	function get_original() { return $this->original; }
	function get_objects() { return $this->objects; }
	function get_has_dual_line() { return $this->has_dual_line; }
	function get_is_dual_line() { return $this->is_dual_line; }
	
	function get_num_lines()
	{
		// Start at 0, count the character heading separately
		$total = 0;
		foreach ($this->objects as $object)
			$total += $object->get_num_lines();
		return $total;
	}
	
	function add_object($object)
	{
		// What else could go here?
		check($object->get_type() == "Paren" || $object->get_type() == "Dialog");
		
		$this->objects[] = $object;
	}
	
	function get_paren_lines()
	{
		$total = 0;
		foreach ($this->objects as $object) {
			if ($object->get_type() == "Paren")
				$total += $object->get_num_lines();
		}
		return $total;
	
	}
	
	function get_dialog_string()
	{
		$str = "";
		foreach ($this->objects as $object) {
			if ($object->get_type() == "Dialog") {
				if ($str != "")
					$str .= " ";
				$str .= $object->get_content();
			}
		}
		return $str;
	}

	function get_string()
	{
		return $this->get_dialog_string();
	}
}


class Character {
	private $name;
	private $all_dialog;
	private $percent_male;
	private $num_male_female;

	function __construct($name)
	{
		$this->name = $name;
		$this->all_dialog = array();
		$this->percent_male = 50; // Default is 50/50
		$this->num_male_female = 0;
	}

	function set_percent_male($percent_male) { $this->percent_male = $percent_male; }
	function get_percent_male() { return $this->percent_male; }
	function set_num_male_female($num_male_female) { $this->num_male_female = $num_male_female; }
	function get_num_male_female() { return $this->num_male_female; }
	
	function get_name() { return $this->name; }
	function get_all_dialog() { return $this->all_dialog; }
	
	function add_dialog($dialog)
	{
		$this->all_dialog[] = $dialog;
		
	}
	
	function set_intro($intro) { $this->intro = $intro; }
	
	function get_sort_value()
	{
		return count($this->all_dialog);
	}
}

class Transition extends SceneObject {

	private $line;
	
	function __construct($line, $page_num)
	{
		$this->page_num = $page_num;
		$this->line = $line;
	}

	function get_num_lines()
	{
		return $this->line->get_num_lines();
	}
	
	function get_string()
	{
		return $this->line->get_content();
	}
}

function character_sort($a, $b)
{
	$a_count = $a->get_sort_value();
	$b_count = $b->get_sort_value();
	if ($a_count == $b_count)
		return 0;
   return ($a_count < $b_count) ? 1 : -1;
	
}

function ends_with(&$name, $end)
{
	$strlen_end = strlen($end);
	if (substr($name, -$strlen_end) == $end) {
		$name = substr($name, 0, strlen($name) - $strlen_end);
		return true;
	}
}

function split_character_modifier(&$character_name, &$modifier)
{
    $modifier = "";
    while (in_array(substr($character_name, -1), array(")", "]"))) {
        $paren_begins = strrpos($character_name, "(");
        if ($paren_begins === false)
			$paren_begins = strrpos($character_name, "[");
        if ($paren_begins !== false) {
            $trimmed = trim(substr($character_name, 0, $paren_begins));
            if ($trimmed != "") {
                $modifier = trim(substr($character_name, $paren_begins + 1, strlen($character_name) - $paren_begins - 2));
				$character_name = $trimmed;
            } else
				break;
        } else
            break;
    }
}

function strip_parens($str)
{
	split_character_modifier($str, $dummy);
	return $str;
}

function trim_non_breaking_space($str)
{
	$non_breaking_space = chr(0xC2).chr(0xA0);
	$length = mb_strlen($str, "UTF-8");
	for ($i = 0; $i < $length; $i++) {
	    if (mb_substr($str, 0, 1, "UTF-8") == $non_breaking_space) {
	        $str = mb_substr($str, 1, $length - 1, "UTF-8");
	        $length--;
	    } else
	        break;
	}
	for ($i = $length - 1; $i >= 0; $i--) {
	    if (mb_substr($str, $i, 1, "UTF-8") == $non_breaking_space) {
	        $str = mb_substr($str, 0, $i, "UTF-8");
	        $length--;
	    } else
	        break;
	}
	return $str;
}

class Analyzer {

	private $objects;
	
	private $characters;
	private $scenes;
	
	private $title_scene;

	function __construct($objects)
	{
		$this->objects = $objects;
		
		$this->characters = array();
		$this->scenes = array();
		
	}
	
	function get_characters() { return $this->characters; }
	
	function get_title()
	{
		$title_objects = $this->title_scene->get_scene_objects();
		foreach ($title_objects as $object) {
			if (get_class($object) == "Title")
				return $object->get_string();
		}
		return "";
	}
	
	function get_author()
	{
		$title_objects = $this->title_scene->get_scene_objects();
		foreach ($title_objects as $object) {
			if (get_class($object) == "Author") {
				return $object->get_string();
			}
		}
		return "";
	}
	
	function get_character($character_name)
	{
		$character_name = trim(mb_convert_case($character_name, MB_CASE_UPPER, "UTF-8"));
		$character_name = trim_non_breaking_space($character_name);
		if (!array_key_exists($character_name, $this->characters)) {
			$this->characters[$character_name] = new Character($character_name);
	    }
		return $this->characters[$character_name];
	}
	
	function create_character_dialog($character_name, $page_num, $current_scene, $has_dual_line, $is_dual_line)
	{
	    $original = $character_name;
	    
		// Note that the i makes AND case-insensitive
		$character_name_pieces_temp = preg_split('/(&| AND )/iu', $character_name, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		// Make sure we don't split the character name in the middle of a parenthetical
		// e.g. NICK (front *and* center) shouldn't be split
		$character_name_pieces = array();
		for ($num = 0; $num < count($character_name_pieces_temp); $num += 2) {
			$character_name = $character_name_pieces_temp[$num];
			$paren_begins = strrpos($character_name, "(");
			$close = ")";
			if ($paren_begins === false) {
				$paren_begins = strrpos($character_name, "[");
				$close = "]";
			}
			if ($paren_begins !== false) {
				$paren_ends = strpos($character_name, $close, $paren_begins);
				if ($paren_ends === false && $num + 2 < count($character_name_pieces_temp)) {
					// That means the paren doesn't close in this chunk, so we
					// should merge it with the next chunk (and delimeter) and try again
					$character_name_pieces_temp[$num] .= $character_name_pieces_temp[$num + 1] . $character_name_pieces_temp[$num + 2];
					array_splice($character_name_pieces_temp, $num + 1, 2);
					$num -= 2;
					continue;
				}
			}
			$character_name_pieces[] = $character_name;
		}
		
		$dual_line = (count($character_name_pieces) > 1);
		$current_dialog = array();
		
		// If it's X, Y and Z we should split on the comma as well
		if ($dual_line) {
			$new_character_name_pieces = array();
			foreach ($character_name_pieces as $num => $character_name) {
				$sub_pieces = preg_split('/,/iu', $character_name);
				$new_character_name_pieces = array_merge($new_character_name_pieces, $sub_pieces);
			}
			$character_name_pieces = $new_character_name_pieces;
		}
		foreach ($character_name_pieces as $num => $character_name) {
			$character_name = trim($character_name);
			if ($character_name == "")
				continue;
			
			// Arguments are passed by reference, e.g. splits DANIEL (O.S.)
			split_character_modifier($character_name, $modifier);

			if ($character_name == "")
				continue;

			$character = $this->get_character($character_name);

			$dialog = new Dialog($character, $modifier, $original, $page_num, $has_dual_line || ($num == 0 && $dual_line), $is_dual_line || ($num > 0));
			$current_dialog[] = $dialog;
			$character->add_dialog($dialog);
    	    
			$current_scene->add_scene_object($dialog);
		}
		return $current_dialog;
	}
	
	function analyze()
	{
		
		$this->title_scene = new Scene();
//		$dummy_scene->add_slugline(new Object("Slugline", "Title Page", -1, -1));
//		$this->scenes[] = $dummy_scene;
		
		$current_scene = $this->title_scene;
		$current_dialog = NULL;
		$last_character_name = "*UNNAMED*";
		$last_characters = "";
		foreach ($this->objects as $object) {
			$object_type = $object->get_type();
			$terminates_dialog = true;
			switch ($object_type) {
				case "Character":
					$terminates_dialog = false;
					$character_object = $object;
					// If this is the same dialog as the object directly before
					// before, minus parentheses, keep the old object
					// FIXME: This is done in case of NICK followed on the next page by NICK (CONT'D)
					// because we want to combine those. However, wouldn't want to combine if intentional...
					$stripped = strip_parens($object->get_content());
					$dual_part = $object->get_has_dual_line() || $object->get_is_dual_line();
					if ($stripped != "" && ($current_dialog === NULL || $last_characters != $stripped || $dual_part)) {
						if (!$dual_part)
							$last_characters = $stripped;
						else
							$last_characters = NULL;
						$last_character_name = $object->get_content();
						$current_dialog = $this->create_character_dialog($last_character_name, $object->get_page_num(), $current_scene, $object->get_has_dual_line(), $object->get_is_dual_line());
					}
					break;
				case "Paren":
					$paren_content = $object->get_content();
					// No need to add (), and can be wrong if there's formatting to start. Trust document
					/*
					if (substr($paren_content, 0, 1) == "[" && substr($paren_content, -1) == "]")
						$paren_content = "(" . substr($paren_content, 1, -1) . ")";
					if (substr($paren_content, 0, 1) != "(")
						$paren_content = "($paren_content";
					if (substr($paren_content, -1) != ")")
						$paren_content = "$paren_content)";
					*/
					$object->set_content($paren_content);
				case "Dialog":
					$terminates_dialog = false;
					if ($current_dialog == NULL) {
						$current_dialog = $this->create_character_dialog($last_character_name, $object->get_page_num(), $current_scene, false, false);
					}

					foreach ($current_dialog as $dialog)
						$dialog->add_object($object);
					break;
				case "Text":
					$current_scene->add_scene_object(new Text($object, $object->get_page_num()));
					break;
				case "Title":
					$current_scene->add_scene_object(new Title($object, $object->get_page_num()));
					break;
				case "Author":
					$current_scene->add_scene_object(new Author($object, $object->get_page_num()));
					break;
				case "Slugline":
					if (!$GLOBALS["separate_continuous"] || strpos($object->get_content(), "CONTINUOUS") === false) {
						$current_scene = new Scene();
						$this->scenes[] = $current_scene;
					}
					$int_ext_prefixes = array("int./ext.", "int.", "ext.");
					$content = $object->get_content();
					foreach ($int_ext_prefixes as $prefix)
						if (is_prefix($content, $prefix, false)) {
							$prefix_len = strlen($prefix);
							if (ctype_alnum(substr($content, $prefix_len, 1)))
								$content = substr_replace($content, " ", $prefix_len, 0);
							break;
						}
					$object->set_content($content);
					$current_scene->add_slugline($object);
					break;
				case "Action":
					$current_scene->add_scene_object(new Action($object, $object->get_page_num()));
					break;
				case "Shot":
					$current_scene->add_scene_object(new Shot($object, $object->get_page_num()));
					break;
				case "Act":
					$current_scene->add_scene_object(new Act($object, $object->get_page_num()));
					break;
				case "Transition":
					$current_scene->add_scene_object(new Transition($object, $object->get_page_num()), $object->get_page_num());
					break;
					
			}
			if ($terminates_dialog)
				$current_dialog = NULL;
		}

		uasort($this->characters, "character_sort");
		
	}

	function analyze_scenes($analyzer)
	{
		// Analyze the first "scene", before there's an actual scene header.
		// This likely is for the title page.
		if (!$analyzer->analyze($this->title_scene))
			return false;
		
		$count = 0;
		foreach($this->scenes as $scene) {
			if (!$analyzer->analyze($scene))
				return false;
			if (false && $count++ > 2)
				break;
		}
		return true;
	}
	
	function print_structure()
	{
		$this->analyze_scenes(new StructurePrinter());
	}


}


