<?php

// This is the entry point for ParseShakespeare.php.
// Input: The name of a Shakespeare script file from http://shakespeare.mit.edu/
// Output: An array of type Object[], containing the parsed representation
// of the input file.
$shakespeare = false;
function parse_shakespeare_file($shakespeare_file, &$num_pages, &$is_stage_play)
{
	$GLOBALS["shakespeare"] = true;

	// A Shakespeare file is plain html text.
	if ($shakespeare_file === FALSE) {
		convenient_assumption(FALSE);
		return array();
	}
	$shakespeare_file_contents = file_get_contents($shakespeare_file);

	$html_parser = new DOMDocument();
	$html_parser->loadHTML($shakespeare_file_contents);

	// This is the Object[] array we're going to give back to the caller.
	$objects = array();

    function addBlankLines($num, $title_page, &$objects)
    {
        for ($i = 0; $i < $num; $i++)
    	    $objects[] = shakespeare_to_Object("Text", "", $title_page);
    }

	// See if we can get the title and author out of the <head> section.
	$screenplay_title = "";
	foreach ($html_parser->getElementsByTagName("head")->item(0)->childNodes as $child) {
		if ($child->nodeName == "title") {
			$content = preg_replace("/[[:blank:]\r\n]+/", " ", trim($child->nodeValue));
			$screenplay_title = substr($content, 0, strlen($content) - strlen(": Entire Play"));
		}
	}
	$screenplay_author = "William Shakespeare";
    addBlankLines(17, true, $objects);
	$objects[] = shakespeare_to_Object("Title", $screenplay_title, true);
    addBlankLines(3, true, $objects);
    $by = shakespeare_to_Object("Text", "By", true);
    $by->setAttribute("alignment", "center");
    $objects[] = $by;
    addBlankLines(2, true, $objects);
	$objects[] = shakespeare_to_Object("Author", $screenplay_author, true);
    addBlankLines(24, true, $objects);

	// Now it's time to parse the HTML script.
	$body = $html_parser->getElementsByTagName("body")->item(0);
	if ($body instanceof DOMNode) {
		foreach ($body->childNodes as $child) {
			if ($child->nodeName == "a") {
				$name = $child->attributes->getNamedItem("name");
				assert($name instanceof DOMNode);
				$content = preg_replace("/[[:blank:]\r\n]+/", " ", trim($child->nodeValue));
				if (is_prefix($name->nodeValue, "speech")) {
					if (!empty($objects) && end($objects)->get_type() == "Character") {
						// Consecutive characters seems to mean both characters
						// are speaking at once. Look in Hamlet, where MARCELLUS and BERNARDO speak
						$last = end($objects);
						$last->set_content($last->get_content() . " & $content");
					} else
						$objects[] = shakespeare_to_Object("Character", $content);
				} else {
					// This shouldn't happen - Dialog is within blockquote below
				}
			} else if ($child->nodeName == "blockquote") {
				foreach ($child->childNodes as $child2) {
					if ($child2->nodeName == "a") {
						$name = $child2->attributes->getNamedItem("name");
						assert($name instanceof DOMNode);
						$content = preg_replace("/[[:blank:]\r\n]+/", " ", trim($child2->nodeValue));
						if ($content == "EPILOGUE") {
							// Hack, bug in the script formatting
							$objects[] = shakespeare_to_Object("Slugline", $content);
						} else {
							if (substr($content, 0, 1) == "[") {
								// Pull out the first piece into a Paren
								$end_paren = strpos($content, "]");
								if ($end_paren !== false) {
									$paren_content = "(" . substr($content, 1, $end_paren - 1) . ")";
									$content = substr($content, $end_paren + 1);
									$objects[] = shakespeare_to_Object("Paren", $paren_content);
								}
							}
							if ($content != "")
								$objects[] = shakespeare_to_Object("Dialog", $content);
						}
					} else if ($child2->nodeName == "p") {
						$content = preg_replace("/[[:blank:]\r\n]+/", " ", trim($child2->nodeValue));
						if (is_prefix($content, "To ") || $content == "Aside") {
							// Make To Person/Aside a ()
							$objects[] = shakespeare_to_Object("Paren", "(" . $content . ")");
						} else
							$objects[] = shakespeare_to_Object("Action", $content);
					}
				}
			} else if ($child->nodeName == "h3") {
				$content = preg_replace("/[[:blank:]\r\n]+/", " ", trim($child->nodeValue));
				if (is_prefix($content, "ACT"))
					$objects[] = shakespeare_to_Object("Act", $content);
				else
					$objects[] = shakespeare_to_Object("Slugline", $content);
			}
		}
	}
	
	$is_stage_play = true;
	return $objects;
}

function shakespeare_to_Object($type, $content, $title_page = false)
{
	if ($title_page)
		$page_num = 1;
	else
		$page_num = 2;
		//nico note: array() is a placeholder, gotta figure out normal ScriptObjects and font sizes before I go to England
	return new ScriptObject($type, $content, $page_num, $page_num, array(), 16, array());
}