<?php

function compress_spaces($str)
{
    // Only need to compress extra spaces left behind in a PDF - leave other formats as-is
    if ($GLOBALS["PARSE_TEXT_FILE"])
	    return preg_replace('!\s+!', ' ', $str);
	else
	    return $str;
}

function strip_color_formatting($str)
{
    $find = "";
    for ($i = 15; $i <= 30; $i++)
        $find .= chr($i);
    return preg_replace("/[$find]/", "", $str);
}

function replace_accents($string) 
{ 
	static $from = array('à','á','â','ã','ä', 'ç', 'è','é','ê','ë', 'ì','í','î','ï', 'ñ', 'ò','ó','ô','õ','ö', 'ù','ú','û','ü', 'ý','ÿ', 'À','Á','Â','Ã','Ä', 'Ç', 'È','É','Ê','Ë', 'Ì','Í','Î','Ï', 'Ñ', 'Ò','Ó','Ô','Õ','Ö', 'Ù','Ú','Û','Ü', 'Ý');
	static $to = array('a','a','a','a','a', 'c', 'e','e','e','e', 'i','i','i','i', 'n', 'o','o','o','o','o', 'u','u','u','u', 'y','y', 'A','A','A','A','A', 'C', 'E','E','E','E', 'I','I','I','I', 'N', 'O','O','O','O','O', 'U','U','U','U', 'Y');
	return str_replace($from, $to, $string); 
}

class ScriptObject {
	private $type;
	private $num_lines;
	private $content;
	private $page_num;
	private $has_dual_line;
	private $is_dual_line;
	private $given_page_num;
	private $attributes;
	public $colors;

	function __construct($type, $content, $page_num, $given_page_num, $colors, $fontSize, $textAttributes, $numberObject)
	{
		static $valid_types = array("Text", "Slugline", "Act", "Action", "Character",
									"Dialog", "Paren", "Transition", "Shot", "The End",
									"Page Header", "Title", "Author", "Act",
									"Fly Page Text");
		if (!in_array($type, $valid_types)) {
			assert(false);
			$type = "Text";
		}
		// assert($content != "");

		// NOTE: My local Mac removes FD's ’, but on the server it correctly converts them. Not worrying.

		// iconv fails to convert at least some of these on my server.
		// Replace them first so iconv doesn't mess them up...
//		$content = replace_accents($content);
		
		// UTF-8 display doesn't work well in browsers?
		// This in in the test script Test45.fdx
//		$content = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $content);

 		// Clean up any remaining problem characters
//		ini_set('mbstring.substitute_character', "none");
//		$content = mb_convert_encoding($content, "UTF-8", "UTF-8");

        $this->type = $type;

        $content = strip_color_formatting($content);
		$this->content = compress_spaces($content);

		$this->page_num = $page_num;
		$this->num_lines = 1;
		$this->has_dual_line = false;
		$this->is_dual_line = false;
		$this->given_page_num = $given_page_num;
		$this->attributes = array();
		$this->colors = $colors;
		$this->attributes['fontSize'] = $fontSize;
		$this->attributes['textAttributes'] = $textAttributes;
		$this->attributes['numberObject'] = $numberObject;
	}

	function get_type_json()
	{ 
		 $type = $this->type;

		 switch ($type) {
			 case "Dialog":
				$type = "Dialogue";
				break;
			case "Slugline":
				$type = "Scene";
				break;
			case "Paren":
				$type = "Parens";
				break;
			case "Act":
				$type = "New Act";
				break;
		}
		 return $type;
	}

	function get_type() { return $this->type; }
	function set_type($t) { $this->type = $t; }
	function get_content() { return $this->content; }
	function set_content($c) { $this->content = compress_spaces($c); }
	function get_colors() { return $this->colors; }
	function get_color_IDs() 
	{
		return array_keys($this->colors);
	}
	function get_num_lines() { return $this->num_lines; }
	function set_num_lines($x) { $this->num_lines = $x; }  // used by NGParseText
	function get_page_num() { return $this->page_num; }
	function get_given_page_num() { return $this->given_page_num; }
	
	function get_has_dual_line() { return $this->has_dual_line; }
	function set_has_dual_line($has_dual_line) { $this->has_dual_line = $has_dual_line; }
	function get_is_dual_line() { return $this->is_dual_line; }
	function set_is_dual_line($is_dual_line) { $this->is_dual_line = $is_dual_line; }

	function getAttribute($attribute) {
		return isset($this->attributes[$attribute]) ? $this->attributes[$attribute] : NULL;
	}

	function setAttribute($attribute, $value) {
		$this->attributes[$attribute] = $value;
	}

	function getNumberObject()
	{
		if (isset($this->attributes["numberObject"]))
		{
			return $this->attributes["numberObject"];
		}
		else
		{
			return array();
		}
	}

	function getTextAttributes()
	{
		if (isset($this->attributes["textAttributes"]))
		{
			return $this->attributes["textAttributes"];
		}
		else
		{
			return array();
		}
	}

	function getFontSize()
	{
		if (isset($this->attributes["fontSize"]))
		{
			return $this->attributes["fontSize"];
		}
		else
		{
			return 16;
		}
	}

	function deleteNumberObjectLeft()
	{
		if (isset($this->attributes["numberObject"]["left"]))
		{
			unset($this->attributes["numberObject"]["left"]);
		}
	}
	function deleteNumberObjectRight()
	{
		if (isset($this->attributes["numberObject"]["right"]))
		{
			unset($this->attributes["numberObject"]["right"]);
		}
	}

	function getAllAttributesForJSON() {
		$returnArray = array();
		foreach($this->attributes as $attrKey => $attr)
		{
			if (is_array($attr))
			{
				if (count($attr) > 0)
				{
					$returnArray[$attrKey] = $attr;
				}
			}
			else
			{
				if ($attrKey == "fontSize" && $attr == "16")
				{
					//do nothing, we don't want to send fontSize = 16 on every line
				}
				else
				{
					$returnArray[$attrKey] = $attr;
				}
			}
		}
		return $returnArray;
	}

	function addRevision($weight_num, $instance)
	{
		$this->attributes["revisions"][$weight_num][] = $instance;
	}

	function get_merged_attribute($this_attribute, $passed_attribute, $offset)
	{
		$merged_attribute = $this_attribute;
		foreach ($passed_attribute as $attr_ID => $entries)
		{
			foreach($entries as $entry)
			{
				$new_entry['start'] = $entry['start'] + $offset;
				$new_entry['end'] = $entry['end'] + $offset;
				$merged_attribute[$attr_ID][] = $new_entry;
			}
		}
		return $merged_attribute;
	}

	function get_merged_colors($passed_ScriptObect, $string_inbetween)
	{
		$offset = strlen($this->get_content()) + strlen($string_inbetween);

		return $this->get_merged_attribute($this->get_colors(), $passed_ScriptObect->get_colors(), $offset);
	}

	function get_merged_textAttributes($passed_ScriptObect, $string_inbetween)
	{
		$offset = strlen($this->get_content()) + strlen($string_inbetween);

		// print_r($passed_ScriptObect);
		return $this->get_merged_attribute($this->getAttribute("textAttributes"), $passed_ScriptObect->getAttribute("textAttributes"), $offset);
	}

	//funtion get_merged_all_attributes($passed_ScriptObect, $string_inbetween)
}

function is_slugline_type($type)
{
	return ($type == "Slugline" || $type == "Shot" || $type == "Act");
}

function is_action_type($type)
{
	return ($type == "Action" || $type == "Text" || $type == "Transition");
	
}