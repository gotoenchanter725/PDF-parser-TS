<?php

define("INCH", 72);
define("MAX_OFFSET_SAME_LINE", 4);
define("FONT_CONVERSION_RATE", 4/3);
$default_char_width = 7;

$autoHeaderFooterTextArray = array("Created using Celtx",
"Written with Arc Studio: www.arcstudiopro.com",
"(Printed with the demonstration version of Fade In)");
$sceneContinuedNumber = false;
$sceneContinuedTop = false;
$sceneContinuedBottom = false;

function customLtrim($text)
{
	return preg_replace('/^[\s\x00]+/u', '', $text);
}
function customRtrim($text)
{
	return preg_replace('/[\s\x00]+$/u', '', $text);
}

// This is the entry point for ParseText.php.
// Input: The name of a text file, e.g. the string "foo.txt".
// Output: An array of type Object[], containing the parsed representation
// of the input file.
function parse_xml_file($xml_file, &$num_pages, &$is_stage_play, &$pageSize)
{
    $GLOBALS["PARSE_TEXT_FILE"] = true;
	// mb_internal_encoding("UTF-8");

    
	// $xml = file_get_contents($xml_file);
	// convenient_assumption($xml !== FALSE);
	// mb_internal_encoding(mb_detect_encoding($xml));

	// $parser = new PlistXMLParser();
	// $parser->loadXML($xml);
	// print_r($parser->parse());

	// exit();

	// $xmlParser = xml_parser_create();
	// xml_parse_into_struct($xmlParser, $xml, $xmlValues);
	// xml_parser_free($xmlParser);

	$sxi = new SimpleXmlIterator($xml_file, null, true);

	function sxiToArray($sxi){
		$a = array();
		for($sxi->rewind(); $sxi->valid(); $sxi->next()) {
			$b = array();
			$b["tag"] = strtoupper($sxi->key());
			$sxiCurrent = $sxi->current();
			$dom = dom_import_simplexml($sxiCurrent);

			//$b["value"] = $dom->textContent;
			$b["value"] = strval($sxiCurrent);

			//print_r($b["value"]);
			//print_r("\n");
			//print_r($dom->childNodes);
			$b["attributes"] = array();
			foreach ($sxiCurrent->attributes() as $attrKey => $attrVal) {
				$b["attributes"][strtoupper($attrKey)] = strval($attrVal);

			}

			if ($b["tag"] == "TEXT")
			{
				// print_r($b["tag"]);
				// print_r(count($dom->childNodes));
				// print_r("\n");
				$b["value"] = $dom->textContent;
				$handledLength = 0;
				for ($i = 0; $i < count($dom->childNodes); $i++)
				{
					$child = $dom->childNodes[$i];
					if ($child->nodeName == "#text")
					{
						//print_r($dom->childNodes[$i]);
						$handledLength += $child->length;
					}
					else
					{
						$childLength = mb_strlen($child->textContent);
						$hold = array();
						$hold["start"] = $handledLength;
						$hold["end"] = $handledLength + $childLength - 1;
						$b['textAttributes'][$child->nodeName][] = $hold;
						$handledLength += $childLength;
					}
				}
			}

			if ($sxi->hasChildren()) {
				$b["children"] = sxiToArray($sxiCurrent);
			

				//print_r($b["children"]);
			}
			// if ($b["attributes"]["TOP"] == 274)
			// {
			// 	//print_r($b["value"]);
			// 	$thing = dom_import_simplexml($sxiCurrent);
			// 	//print_r($sxiCurrent);
			// 	//print_r(dom_import_simplexml($sxiCurrent)->getAttribute("b"));
			// 	foreach($dom->childNodes as $child)
			// 	{
			// 		//print_r($child);
			// 	}
			// 	//print_r($thing->childNodes[0]);

			// }
			$a[] = $b;
		}
		return $a;
	}

	$xmlObject = sxiToArray($sxi);
	
	$lineObjects = array();
	$headerObjects = array();
	$footerObjects = array();
	$dirty_internal_page_num = 0;

	$fonts = array();

	// Convert XML to lines. This should be smarter in the future, to do some pre-processort parsing, to handle
	// non-script-text content like headers/footers, scene numbers, asterisks, etc.

	function trimReturnHowMuchCut($value, $doLeftTrim, $doRightTrim)
	{
		$returnArray = array();
		$returnArray["leftTrimmed"] = 0;
		$returnArray["rightTrimmed"] = 0;
		$returnArray["value"] = $value;
		if ($doLeftTrim)
		{

			$currentLength = mb_strlen($returnArray["value"]);
			$returnArray["value"] = customLtrim($returnArray["value"]);
			$returnArray["leftTrimmed"] = $currentLength - mb_strlen($returnArray["value"]);
		}
		if ($doRightTrim)
		{
			$currentLength = mb_strlen($returnArray["value"]);
			$returnArray["value"] = customRtrim($returnArray["value"]);
			$returnArray["rightTrimmed"] = $currentLength - mb_strlen($returnArray["value"]);
		}

		return $returnArray;

	}

	function addValueToLineContent($obj, &$lineContent, $trimLeft, $trimRight, &$lineTextAttributes, &$newLineContent)
	{
		$val = trimReturnHowMuchCut($obj["value"], $trimLeft ? 1 : 0, $trimRight ? 1 : 0);
		if ($val["value"] != "") {
			$textLengthBefore = mb_strlen($lineContent);
			if (isset($obj["textAttributes"]))
			{
				foreach($obj["textAttributes"] as $attr => $entryList)
				{
					foreach($entryList as $entry)
					{
						if ($entry["end"] < $val["leftTrimmed"])
						{
							//do nothing, this was entirely cut off by the left trim
						}
						else if ($entry["start"] >= mb_strlen($val["value"]) + $val["leftTrimmed"])
						{
							//do nothing, this was entirely cut off by the right trim
						}
						else
						{
							$start_end_pair = array();
							$leftAccountForTrim = max($entry["start"] - $val["leftTrimmed"], 0);
							$start_end_pair['start'] = $leftAccountForTrim + $textLengthBefore;
							$rightAccountForTrim = $entry["end"] - $val["leftTrimmed"];
							$rightAccountForTrim = min($rightAccountForTrim, mb_strlen($val["value"]) - 1);
							$start_end_pair['end'] = $rightAccountForTrim + $textLengthBefore;
							$lineTextAttributes[$attr][] = $start_end_pair;
						}
					}
				}
			}
			$newLineContent .= $val["value"];
			$lineContent .= $val["value"];
		}

		//children for bold and italics are already handled with the textAttributes object
		//I'm keeping this here for safekeeping because I don't know if anything else
		//creates children, but I don't think this is needed anymore.
		if (isset($obj["children"]) && !isset($obj["textAttributes"])) {
			foreach ($obj["children"] as $childObject) {
				$dummy = array();
				addValueToLineContent($childObject, $lineContent, false, false, $dummy, $newLineContent);
			}
		}

		return $val;
	}

	function newLine($text, $x, $width, $given_page_num, $line_colors, $line_fontSize, $lineTextAttributes, $lineNumberObject)
	{
		return new LineObject($text, $x, $width, $given_page_num, $line_colors, $line_fontSize, $lineTextAttributes, $lineNumberObject);
	}

	function sort_by_left($a, $b)
	{
		return $a['attributes']['LEFT'] - $b['attributes']['LEFT'];
	}

	function lineObjectCompare($o1, $o2)
	{
		if (!empty($o1) && !empty($o2))
		{
			return ($o1["tag"] == $o2["tag"] && $o1["value"] == $o2["value"] && $o1["attributes"]["TOP"] == $o2["attributes"]["TOP"]
			&& $o1["attributes"]["LEFT"] == $o2["attributes"]["LEFT"] && $o1["attributes"]["WIDTH"] == $o2["attributes"]["WIDTH"]
			&& $o1["attributes"]["HEIGHT"] == $o2["attributes"]["HEIGHT"] && $o1["attributes"]["FONT"] == $o2["attributes"]["FONT"]);
		}
		else
		{
			return false;
		}
	}

	//handles cases where the lineObjects are something like {"   ", "  ", "Harry said hi", "  ", "     "}
	function calculateWhichKeysToTrim($lineObjects)
	{
		$return_array = array();
		//initialize them all to false (did it this way so I don't have to bother with issets)
		foreach ($lineObjects as $key => $obj)
		{
			$return_array[$key]["trimLeft"] = false;
			$return_array[$key]["trimRight"] = false;
		}
		//keep checking the left side until we run into actual content
		foreach ($lineObjects as $key => $obj)
		{
			$return_array[$key]["trimLeft"] = true;
			$text = $obj["value"];
			$text = customLtrim($text);
			if ($text != "")
			{
				break;
			}
		}
		//now go again but from the right side
		foreach(array_reverse($lineObjects, true) as $key => $obj)
		{
			$return_array[$key]["trimRight"] = true;
			$text = $obj["value"];
			$text = customRtrim($text);
			if ($text != "")
			{
				break;
			}
		}

		return $return_array;
	}

	function processPageXmlObject($pageObject, &$lineObjectsLocal, &$headerObjectsLocal, &$footerObjectsLocal, &$colorsLocal, &$dirty_internal_page_num, &$fonts, &$pageSize)
	{
		$linesInPage = array();
		$fontSpecFlag = 0;
		$fontObjectsLocal = array();
		
		$pageHeight = $pageObject["attributes"]["HEIGHT"];
		$pageWidth = $pageObject["attributes"]["WIDTH"];

		$pageSize = ($pageHeight > 800 ? "A4" : "Letter");

		foreach ($pageObject["children"] as $childObject) {
			switch ($childObject["tag"]) {
				case "TEXT":
					if ($childObject["attributes"]["LEFT"] > 500 && trim($childObject["value"]) == "*")
					{
						// Skip *s
						break;
					}

					$top = $childObject["attributes"]["TOP"];
					$left = $childObject["attributes"]["LEFT"];

					// Some individual characters or words could have a slightly different vertical position than rest of line
					// print_r("A" . $top . "\n");
					for ($offset = -MAX_OFFSET_SAME_LINE; $offset <= MAX_OFFSET_SAME_LINE; $offset++) {
						$near = intval($top) + $offset;
						// print_r("L" . $near . "\n");
						if (isset($linesInPage[$near])) {
							// Switch to share that line
							$top = $top + $offset;
							// print_r("B" . $top . "\n");
							break;
						}
					}
					if (!isset($linesInPage[$top])) {
						// print_r("C" . $top . "\n");
						$linesInPage[$top] = array();
					}

					while (isset($linesInPage[$top][$left])) {
						// Don't accidentally replace a potentially object that somehow says it stats
						// at the same place, instead add a madeup offset until it's unique
						$left++;
					}
					$linesInPage[$top][$left] = $childObject;
					break;
				case "FONTSPEC":
					$fontSpecFlag = 1;
					$fontObjectsLocal[] = $childObject;
					//convert font size from points to pixels
					// print_r($childObject);
					$points = $childObject['attributes']['SIZE'];
					$fontSizeInPx = floor($points * FONT_CONVERSION_RATE);
					if ($fontSizeInPx >= 13 && $fontSizeInPx < 16) {
						// This is probably a bug in pdftohtml or underlying PDFs.
						// Notably the creator "Microsoft Print To PDF" looks like
						// font size 12pt but the XML output says it's 11pt
						$fontSizeInPx = 16;
					}
					$childObject['attributes']['SIZE'] = $fontSizeInPx;
					// print_r($points);

					$fonts[$childObject['attributes']['ID']] = $childObject['attributes'];

					// print_r($fonts);

					break;
			}
		}

		//we need to create it once and keep updating it as we find more fontSpecs
		if (!isset($colorsLocal))
		{
			$colorsLocal = new Colors_List($fontObjectsLocal);
		}
		else if ($fontSpecFlag)
		{
			$colorsLocal->add_colors($fontObjectsLocal);
		}
		$lastTop = 0;
		$lastTopOffset = 72; // Default top padding
		$basicLineHeight = 12;
		$given_page_num = "";
		ksort($linesInPage);
		$header_objects = [];
		$footer_objects = [];
		$fonts_in_page = array();
		foreach ($linesInPage as $top => $lineObjects) {
			$lineContent = "";
			$line_colors = array();
			// these are for attributes that care about string position
			$lineTextAttributes = array();
			// if ($lineObjects[0]["attributes"]["TOP"] == 274)
			// {
				// print_r($top . "\n");
				// print_r($lineObjects);
				// }
			

			//parse the top for "CONTINUED:" and "CONTINUED: (2)"
			//and if you find it, set the global variable and delete it from the
			//lineObject array so it doesn't also get set as a header
			if ($top <= INCH/2 + 14)
			{
				foreach ($lineObjects as $indx => $obj) 
				{
					if(preg_match('/^CONTINUED:$/', trim($obj["value"])))
					{
						$GLOBALS["sceneContinuedTop"] = true;
						unset($lineObjects[$indx]);
					}
					else if (preg_match('/^CONTINUED: ?(?:\([\d]+[)])?$/', trim($obj["value"])))
					{
						$GLOBALS["sceneContinuedTop"] = true;
						$GLOBALS["sceneContinuedNumber"] = true;
						unset($lineObjects[$indx]);
					}
				}

			}
			//same as above but for footers and "(CONTINUED)"
			if ($top > ($pageHeight - INCH/2 - 29))
			{
				foreach ($lineObjects as $indx => $obj) 
				{
					if(preg_match('/^\(CONTINUED\)$/', trim($obj["value"])))
					{
						$GLOBALS["sceneContinuedBottom"] = true;
						unset($lineObjects[$indx]);
					}
				}
			}
			//Now parse for non CONTINUED based headers
			if ($top <= INCH/2 + 6) 
			{
				$prevObj = null;
				reset($lineObjects);
				//can't do foreach here because I want to add to the array as I parse through it
				while ($obj = current($lineObjects))
				{
					$hold_page_num_array = [];
					//technically this won't always work if anything else in the header matches this REGEX...
					$nonDupliacte = !lineObjectCompare($prevObj, $obj);
					$prevObj = $obj;
					if ($nonDupliacte)
					{
						$obj["value"] = trim($obj["value"]);
						//handle docx not separating out the page number from the rest of the header
						if (mb_strpos($obj["value"], "          ") !== false)
						{
							$explode_array = explode("          ", $obj["value"], 2);
							$obj["value"] = trim($explode_array[0]);
							$copy_of_obj = $obj;
							$copy_of_obj["value"] = trim($explode_array[1]);
							$lineObjects[] = $copy_of_obj;
						}
						if (!in_array($obj["value"], $GLOBALS["autoHeaderFooterTextArray"]))
						{
							if (preg_match('/^\d+[a-zA-Z\. ]?[a-zA-Z\. ]?[a-zA-Z\. ]?[a-zA-Z\. ]?$/', $obj["value"], $hold_page_num_array))
							{
								$given_page_num = rtrim($hold_page_num_array[0], ". ");
							}
							else
							{
								$header_objects[] = $obj;
							}
						}
					}
					next($lineObjects);
				}
			}
			// Hard-coding a number, since Celtx PDFs end pages with a line that starts at 740
			// and Final Draft PDFs start their headers at 743, there isn't much breathing room...
			// FD's makes the most sense, since that's a 1/2 inch margin below the footer line
			else if ($top > ($pageHeight - INCH/2 - 14))
			{
				foreach ($lineObjects as $obj) {
					if (!in_array(trim($obj["value"]), $GLOBALS["autoHeaderFooterTextArray"]))
					{
						$footer_objects[] = $obj;
					}
				}
			}
			else
			{
				if (false) {
					// Make sure a previous blank line doesn't collid with this line due to a font that's obscenely large and meaningless
					print_r("last" . $lastTopOffset . "\n");
					print_r("top" . $top . "\n");
					if ($lastTopOffset > $top) {
						$lastIndex = count($lineObjectsLocal) - 1;
						if ($lastIndex >= 0) {
							if ($lineObjectsLocal[$lastIndex]->text == "") {
								$lineObjectsLocal[$lastIndex]->line_fontSize = floor(($top - $lastTop)*FONT_CONVERSION_RATE);
								print_r("Changed font to " . $lineObjectsLocal[$lastIndex]->line_fontSize . "\n");
							}
						}
					}
				}

				// NOTE: + MAX_OFFSET_SAME_LINE since some PDFs have tighter lines between blocks, or a special character/offset line
				// print_r($top . " $addSpacingLinesTop\n");
				for ($addSpacingLinesTop = $lastTopOffset; $addSpacingLinesTop <= $top - $basicLineHeight + MAX_OFFSET_SAME_LINE; $addSpacingLinesTop += $basicLineHeight) {
					// Add blank lines to move top line down
					$lineObjectsLocal[] = newLine("", 0, 0, $given_page_num, array(), 16, array(), array());
					// print_r("New\n");
					// print($addSpacingLinesTop . "\n");
					// print($top . "\n");
				}

				$left = 1000000;
				$right = 0;
				$line_fontSize = 0;
				$lineNumberObject = array();

				ksort($lineObjects);

				$lastObjRight = 0;
				$keyTrimArray = calculateWhichKeysToTrim($lineObjects);
				foreach ($lineObjects as $key => $obj) {
					// Make trim() include nbs
					$text = rtrim($obj["value"], " \n\r\t\v\x00" . chr(0xC2).chr(0xA0));
					$objLeft = $obj["attributes"]["LEFT"];
					$objRight = $objLeft + $obj["attributes"]["WIDTH"];
					//handle lineNumber
					if ($text != "" && $objLeft < 65 && mb_strlen($text) < 7)
					{
						$lineNumberObject["number"] = $text;
						$lineNumberObject["left"] = 1;
						
					}
					else if ($text != "" && $objLeft > 520 && mb_strlen($text) < 7)
					{
						$lineNumberObject["number"] = $text;
						$lineNumberObject["right"] = 1;
					}
					else
					{
						$objFontID = $obj["attributes"]["FONT"];
						$objColorID = $colorsLocal->get_color_ID($objFontID);

						$textLengthBefore = mb_strlen($lineContent);
						//keep track of the textAttributes (bold, italic)

						if ($text != "" || $line_fontSize == 0)
							$line_fontSize = max($lineContent != "" ? $line_fontSize : 0, $text != "" ? $fonts[$objFontID]['SIZE'] : min(floor($basicLineHeight * FONT_CONVERSION_RATE), $fonts[$objFontID]['SIZE']));
						
						// print_r("\"$lineContent\" $text $left $top\n");
						if ($text != "" && $lineContent != "") {
							// Add spacing to account for gap
							if ($lastObjRight != 0) {
								$numSpacesToAdd = floor(($objLeft - $lastObjRight) / $GLOBALS["default_char_width"]);
								if ($numSpacesToAdd > 0) {
									// print_r("Adding $numSpacesToAdd\n");
									$lineContent .= str_repeat(" ", $numSpacesToAdd);
								}
							}
						}

						$priorLineContent = $lineContent;

						// TODO: using first/last key is wrong since there could be Scene Numbers or other objects
						// Also, I don't think we want to be trimming the right side at all, since it costs us
						// double-spaces when at end of line that will be merged with another line below it.
						// Probably remove the trimming in general, so this isn't necessary, except maybe left-trimming 
						// first block (which we can tell by it being the first time we reach here)
						// Would also need to change white_out, which puts in " " that it expects to be removed

						$newLineContent = "";
						$spacesRemoved = addValueToLineContent($obj, $lineContent, $keyTrimArray[$key]["trimLeft"], $keyTrimArray[$key]["trimRight"], $lineTextAttributes, $newLineContent);

						$objLeft += $spacesRemoved["leftTrimmed"] * $GLOBALS["default_char_width"];
						$objRight -= $spacesRemoved["rightTrimmed"] * $GLOBALS["default_char_width"];

						if (!$colorsLocal->special_case($objColorID))
						{
							//if the color isn't black/a special case
							$textLengthAfter = mb_strlen($lineContent);
							$start_end_pair = array();
							$start_end_pair['start'] = $textLengthBefore;
							$start_end_pair['end'] = $textLengthAfter;
							$line_colors[$objColorID][] = $start_end_pair;
						}

						// NOTE: Don't let the last top ever get smaller, to headers don't trick us into thinking we
						// need padding lines before the first line on a page (by moving $lastTopOffset to a smaller number)
						$lastTopOffset = max($lastTopOffset, $top + ($text != "" ? $obj["attributes"]["HEIGHT"] : min($basicLineHeight, $obj["attributes"]["HEIGHT"])));

						// print_r($lastTopOffset . "\n");
						$left = ($priorLineContent != "" ? ($newLineContent != "" ? min($left, $objLeft) : $left) : $objLeft);
						$right = ($priorLineContent != "" ? ($newLineContent != "" ? max($right, $objRight) : $right) : $objRight);

						if (isset($fonts_in_page[$objColorID]))
						{
							//counting number of times this color shows up on the page.
							//not doing anything with it yet but might as well.
							$fonts_in_page[$objColorID]++;
						}
						else
						{
							$fonts_in_page[$objColorID] = 1;
						}
						$lastObjRight = $objRight;
					}

				}
				$lineObjectsLocal[] = newLine($lineContent, $left, $right - $left, $given_page_num, $line_colors, $line_fontSize, $lineTextAttributes, $lineNumberObject);
				$lastTop = $top;
			}
		}
		usort($header_objects, 'sort_by_left');
		if (count($header_objects) > 0)
		{
			$possible_name = $header_objects[0]['value'];
			$list_of_IDS = array_keys($fonts_in_page);
			$colorsLocal->update_IDs_with_name($list_of_IDS, $possible_name, $dirty_internal_page_num);
			$colorsLocal->set_header_name_by_page_num($dirty_internal_page_num, $possible_name);
		}
		// print_r($lineObjectsLocal);
		$dirty_internal_page_num++;
		$lineObjectsLocal[] = newLine("\f", 0, 0, $given_page_num, array(), 16, array(), array());
		$headerObjectsLocal[] = $header_objects;
		$footerObjectsLocal[] = $footer_objects;
		
	}
	//print_r($xmlObject);
	foreach ($xmlObject as $topLevel) {
		switch ($topLevel["tag"]) {
			case "PAGE":
				processPageXmlObject($topLevel, $lineObjects, $headerObjects, $footerObjects, $colors, $dirty_internal_page_num, $fonts, $pageSize);
				break;
		}
	}



	// print_r($lines);

	// Does the text file contain literal form feeds (ASCII 0x0C)?
	// If so, assume that they indicate page breaks.
	// If not, then for now we'll treat the whole thing as one big
	// page. We'll have to implement all kinds of ad-hoc methods
	// for detecting page breaks in different scripts.
	$contains_literal_formfeeds = FALSE;
	foreach ($lineObjects as $num => $lineObject) {
		$i = mb_strpos($lineObject->text, "\f");
		if ($i !== FALSE) {
			// [guyg] Only count the \f if it isn't at the end of the document.
			// At end, it's still a one-pager
			if ($num != count($lineObjects) - 1 || trim(mb_substr($lineObject->text, $i + 1)) != "")
				$contains_literal_formfeeds = TRUE;
			break;
		}
	}
	
	$o = new Text_Parser($colors);
	
	if ($contains_literal_formfeeds) {
		$lines_this_page = array();
		$first_time = TRUE;
		foreach ($lineObjects as $lineObject) {
			//print_r($lineObject);
			// NOTE: We always put \f on its own line now that we parse via XML
			if ($lineObject->text == "\f") {

				$o->parse_page($lines_this_page, /*look_for_title_page=*/$first_time, $lineObject->given_page_num);
				$first_time = FALSE;
				
				$lines_this_page = array();
			} elseif (true || $line != "") {
				$lines_this_page[] = $lineObject;
			}
		}
		if (count($lines_this_page)) {
			// Parse final page. Note that a single-page test input won't be a title page.
			$o->parse_page($lines_this_page, /*look_for_title_page=*/FALSE, $lineObject->given_page_num);
		}
	} else {
		$o->parse_page($lines, /*look_for_title_page=*/FALSE, $lineObject->given_page_num);
	}
	$num_pages = $o->num_pages;
	$return_array = [];
	$return_array[0] = $o->objects;
	$return_array[1] = $headerObjects;
	$return_array[2] = $footerObjects;
	$return_array[3] = $o->colors;
	return $return_array;
}

function white_out(&$str, $contd) {
	$whiteout = str_repeat(" ", mb_strlen($contd));
	$str = str_ireplace($contd, $whiteout, $str);
	// Look for cont'd with other apostrophe types
	$str = str_ireplace(str_ireplace("'", "’", $contd), $whiteout, $str);
	$str = str_ireplace(str_ireplace("'", "‘", $contd), $whiteout, $str);
}

function getAlignment($x, $width, $defaultLeftMargin, $pageWidth)
{
	$lineEnd = $x + $width;
	$lineDistanceFromRight = ($pageWidth - INCH) - $lineEnd;
	$lineMidpoint = $x + $width/2;
	$pageMidpoint = ($pageWidth - $defaultLeftMargin - INCH)/2 + $defaultLeftMargin;
	if ($x > $defaultLeftMargin + INCH/2 && abs($lineMidpoint - $pageMidpoint) < INCH/2) {
		return "center";
	} else {
		// Flexible about right-alignment
		if ($x > $defaultLeftMargin + max($lineDistanceFromRight, INCH) && $lineDistanceFromRight < INCH * 2) {
			// print_r("$x - $lineDistanceFromRight\n");
			return "right";
		}
	}

	return "left";
}

function leo_array_diff($a, $b) {
	$map = array();
	foreach($a as $val) $map[$val] = 1;
	foreach($b as $val) unset($map[$val]);
	return array_keys($map);
}


class Colors_List
{
	private $translation_array;
	private $color_ID_array;
	private $comparison_array_of_IDS;
	private $header_name_by_page_num;

	function __construct($fontObjects) {
		$this->color_ID_array = array();
		$this->translation_array = array();
		$this->comparison_array_of_IDS = array();
		$this->header_name_by_page_num = array();

		for ($FOIndex = 0; $FOIndex < count($fontObjects); $FOIndex++)
		{	
			$found_flag = 0;
			$font_object_color = $fontObjects[$FOIndex]['attributes']['COLOR'];
			$fontObectID = $fontObjects[$FOIndex]['attributes']['ID'];
			for ($CIDAIndex = 0; $CIDAIndex < count($this->color_ID_array); $CIDAIndex++)
			{
				if ($font_object_color == $this->color_ID_array[$CIDAIndex]['attributes']['COLOR'])
				{
					$this->translation_array[$fontObectID] = $CIDAIndex;
					$found_flag = 1;
					break 1;
				}		
			}
			if (!$found_flag)
			{
				$this->color_ID_array[] = $fontObjects[$FOIndex];
				$newestIndex = count($this->color_ID_array) - 1;
				$this->translation_array[$fontObectID] = $newestIndex;
				$this->color_ID_array[$newestIndex]['attributes']['NAME'] = "";
				$this->color_ID_array[$newestIndex]['attributes']['POSSIBLE_NAMES'] = array();
				$this->color_ID_array[$newestIndex]['attributes']['IMPOSSIBLE_NAMES'] = array();
				$this->color_ID_array[$newestIndex]['attributes']['KEY_NAMES'] = array();
				$this->comparison_array_of_IDS[$newestIndex] = $newestIndex;
				$color_color = $this->color_ID_array[$newestIndex]['attributes']['COLOR'];
				if ($color_color == "#000000")
				{
					$this->color_ID_array[$newestIndex]['attributes']['SPECIAL_NAME'] = "Normal Text";
				}
			}
		}
	}

	function add_colors($fontObjects) {
		$prevNumColors = count($this->color_ID_array);
		for ($FOIndex = 0; $FOIndex < count($fontObjects); $FOIndex++)
		{	
			$found_flag = 0;
			$font_object_color = $fontObjects[$FOIndex]['attributes']['COLOR'];
			$fontObectID = $fontObjects[$FOIndex]['attributes']['ID'];
			for ($CIDAIndex = 0; $CIDAIndex < count($this->color_ID_array); $CIDAIndex++)
			{
				if ($font_object_color == $this->color_ID_array[$CIDAIndex]['attributes']['COLOR'])
				{
					
					$this->translation_array[$fontObectID] = $CIDAIndex;
					$found_flag = 1;
					break 1;
				}		
			}
			if (!$found_flag)
			{
				$this->color_ID_array[] = $fontObjects[$FOIndex];
				$newestIndex = count($this->color_ID_array) - 1;
				$this->translation_array[$fontObectID] = $newestIndex;
				$this->color_ID_array[$newestIndex]['attributes']['NAME'] = "";
				$this->color_ID_array[$newestIndex]['attributes']['POSSIBLE_NAMES'] = array();
				$this->color_ID_array[$newestIndex]['attributes']['IMPOSSIBLE_NAMES'] = array();
				$this->color_ID_array[$newestIndex]['attributes']['KEY_NAMES'] = array();
				$this->comparison_array_of_IDS[$newestIndex] = $newestIndex;
				$color_color = $this->color_ID_array[$newestIndex]['attributes']['COLOR'];
				if ($color_color == "#000000")
				{
					$this->color_ID_array[$newestIndex]['attributes']['SPECIAL_NAME'] = "Normal Text";
				}
			}
		}
	}

	function get_color_ID($fontID)
	{
		if (isset($this->translation_array[$fontID]))
		{
			return $this->translation_array[$fontID];
		}
		else
		{
			return 0;
		}
	}

	function set_color_name($ID, $name)
	{
		if (isset($this->color_ID_array[$ID]))
		{
			$this->color_ID_array[$ID]['attributes']['NAME'] = $name;
		}
	}
	function get_color_name($ID)
	{
		if (isset($this->color_ID_array[$ID]['attributes']['NAME']))
		{
			return $this->color_ID_array[$ID]['attributes']['NAME'];
		}
		else
		{
			print_r("name is not set for that ID, not even just an empty string, not set");
			return "Color";
		}
	}
	function get_color_hex_code($ID)
	{
		if (isset($this->color_ID_array[$ID]['attributes']['COLOR']))
		{
			return $this->color_ID_array[$ID]['attributes']['COLOR'];
		}
		else
		{
			print_r("hex code (\"COLOR\") is not set for that ID, not even just an empty string, not set");
			return "Hex Code";
		}
	}
	function handle_possible_name($ID, $possible_name)
	{
		if (isset($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]))
		{
			$this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]++;
		}
		else if (isset($this->color_ID_array[$ID]))
		{
			$this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name] = 1;
		} 
		else
		{
			print_r("ID not available to set a possible name.");
		}
	}
	function handle_key_name($ID, $possible_name, $dirty_internal_page_num)
	{
		if (isset($this->color_ID_array[$ID]['attributes']['KEY_NAMES'][$possible_name]))
		{
			$this->color_ID_array[$ID]['attributes']['KEY_NAMES'][$possible_name]++;
		}
		else if (isset($this->color_ID_array[$ID]))
		{
			$this->color_ID_array[$ID]['attributes']['KEY_NAMES'][$dirty_internal_page_num] = $possible_name;
		} 
		else
		{
			print_r("ID not available to set a key name.");
		}
	}
	function get_key_pairs()
	{
		$return_array = array();

		foreach($this->color_ID_array as $ID => $object)
		{
			foreach(array_keys($object['attributes']['KEY_NAMES']) as $page_num)
			{
				$return_array[$page_num] = $object;
				$return_array[$page_num]['attributes']['NAME'] = $object['attributes']['KEY_NAMES'][$page_num];
				$return_array[$page_num]['attributes']['COLORS_LIST_ID'] = $ID;
			} 
		}

		return $return_array;
	}
	function remove_possible_name($ID, $possible_name)
	{
		if (isset($this->color_ID_array[$ID]))
		{
			if (isset($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]))
			{
				unset($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]);
			}
			if (isset($this->color_ID_array[$ID]['attributes']['IMPOSSIBLE_NAMES'][$possible_name]))
			{
				$this->color_ID_array[$ID]['attributes']['IMPOSSIBLE_NAMES'][$possible_name]++;
			}
			else
			{
				$this->color_ID_array[$ID]['attributes']['IMPOSSIBLE_NAMES'][$possible_name] = 1;
			}
		}
		else
		{
			print_r("ID doesn't exist to remove the possible name from.");
		}
	}
	// function get_possible_name_likelihood($ID, $possible_name)
	// {
	// 	if (isset($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]))
	// 	{
	// 		return $this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name];
	// 	}
	// 	else
	// 	{
	// 		print_r("Possible name doesn't exist to get likelihood, returning -100.")
	// 		return -100;
	// 	}
	// }
	// function change_possible_name_likelihood($ID, $possible_name, $value)
	// {
	// 	if (isset($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]))
	// 	{
	// 		($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'][$possible_name]) = $value;
	// 	}
	// 	else
	// 	{
	// 		print_r("Possible name doesn't exist so you can't set it.")
	// 	}
	// }
	function get_possible_names_array($ID)
	{
		if (isset($this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES']))
		{
			return $this->color_ID_array[$ID]['attributes']['POSSIBLE_NAMES'];
		}
		else
		{
			print_r("ID doesn't exist, so you can't get the possible names array");
			return array();
		}
	}
	function special_case($ID)
	{
		return isset($this->color_ID_array[$ID]['attributes']['SPECIAL_NAME']);
	}
	function update_IDs_with_name($list_of_IDS, $possible_name, $dirty_internal_page_num)
	{
		//check all the IDS that aren't in the list of IDS
		if (count($list_of_IDS) < 3)
		{
			for ($c = 0; $c < count($list_of_IDS); $c++)
			{
				$working_ID = $list_of_IDS[$c];
				if (!$this->special_case($working_ID))
				{
					$this->handle_key_name($working_ID, $possible_name, $dirty_internal_page_num);
				}
			}
		}
		for ($a = 0; $a < count($list_of_IDS); $a++)
		{
			$working_ID = $list_of_IDS[$a];
			if (!$this->special_case($working_ID))
			{
				// if (isset($this->color_ID_array[$working_ID]['attributes']['IMPOSSIBLE_NAMES'][$possible_name]))
				// {
				// 	//increment the counter to see how many times its been disproven, kind of for fun
				// 	$this->color_ID_array[$working_ID]['attributes']['IMPOSSIBLE_NAMES'][$possible_name]++;
				// }
				// else
				// {
				$this->handle_possible_name($working_ID, $possible_name);
				// }
			}
		}
		// $IDSs_without = leo_array_diff($this->comparison_array_of_IDS, $list_of_IDS);
		// //print_r($IDSs_without);
		// for ($b = 0; $b < count($IDSs_without); $b++)
		// {
		// 	// print_r("IDS Without:");
		// 	// print_r($IDSs_without[$b]);
		// 	// print_r("\n");
		// 	//print_r("1\n");
		// 	$working_without_ID = $IDSs_without[$b];
		// 	if (!$this->special_case($working_without_ID))
		// 	{
		// 		print_r($working_without_ID);
		// 		$this->remove_possible_name($working_without_ID, $possible_name);
		// 	}
		// }
	}
	function print_colors()
	{
		print_r($this->color_ID_array);
	}
	function set_header_name_by_page_num($dirty_internal_page_num, $possible_name)
	{
		$this->header_name_by_page_num[$dirty_internal_page_num] = $possible_name;
	}
	public function get_header_name_by_page_num($page_num)
	{
		if (isset($this->header_name_by_page_num[$page_num]))
		{
			return $this->header_name_by_page_num[$page_num];
		}
		else
		{
			print_r("Header name does not exist for that page number.");
			return false;
		}
	}
}

class Text_Parser
{
	public $objects;
	public $num_pages;
	public $given_page_num;
	public $colors;

	function __construct($colors) {
		$this->objects = array();
		$this->num_pages = 0;
		$this->given_page_num = "";
		$this->colors = $colors;
	}

	// Text_Parser.parse_page() accepts a page of text (as an array of strings),
	// and returns the Objects making up that page.
	function parse_page($lineObjects, $look_for_fly_page, $given_page_num)
	{
		static $last_page_block_kind = "Blank";
		$this->num_pages++;
		$this->given_page_num = $given_page_num;
		
		// Strip tabs, carriage returns, and (CONT'D) markers, if any.
		foreach ($lineObjects as $lx => $lineObject) {
			// Make trim() include nbs
			$text = rtrim($lineObject->text, " \n\r\t\v\x00" . chr(0xC2).chr(0xA0));

			// print_r($text);

			while (TRUE) {
				$i = mb_strpos($text, "\t");
				if ($i === FALSE) break;
				$prefix = mb_substr($text, 0, $i);
				$tabstop = mb_substr("        ", mb_strlen($prefix) % 8, 10);
				assert($tabstop !== FALSE);
				$text = $prefix . $tabstop . mb_substr($text, $i+1);
			}
			// $line = str_replace("‘", "'", $line);  // normalize magic quotes, if desired
			// $line = str_replace("’", "'", $line);
			// $line = str_replace('“', '"', $line);
			// $line = str_replace('”', '"', $line);
			// $line = str_replace("—", "-", $line);
			// $line = str_replace("…", "...", $line);  // changes mb_strlen(); I doubt we ever want to do this
			white_out($text, "CONTINUED:");
			white_out($text, "(CONTINUED)");
			white_out($text, "(CONT)");
			white_out($text, "(CONT.)");
			white_out($text, "(CONTD)");
			white_out($text, "(CONT'D)");
			white_out($text, "(CONT’D)");  // not needed if we normalize magic quotes first
			white_out($text, "(MORE)");
			white_out($text, "[CONTINUED]");
			white_out($text, "[CONT]");
			white_out($text, "[CONT.]");
			white_out($text, "[CONTD]");
			white_out($text, "[CONT'D]");
			white_out($text, "[CONT’D]");  // not needed if we normalize magic quotes first
			white_out($text, "[MORE]");
			$lineObject->text = preg_replace('/^[\s\x00]+|[\s\x00]+$/u', '', $text);

			// print_r($lineObject);

		}
		
		// Now split the page up into "block pieces".
		$block_pieces = array();
		$pageText = "";
		$numLinesWithText = 0;

		foreach ($lineObjects as $lx => $lineObject) {
			$i = 0;
			$text = $lineObject->text;
			$addedBlock = false;
			while ($i != mb_strlen($text) && mb_substr($text, $i, 1) == ' ') ++$i;
			while ($i != mb_strlen($text)) {
				/* [guyg] We were merging the following case:
				 *              PAUL (O.C.)
				 *   SAMUEL!    SAMUEL!     // pdftotext added too many spaces
				 * We were merging the second SAMUEL! with PAUL (O.C.)
				 * I increased the spaces from 3 to 9. It's too important
				 * to not split up horizontally. I saw it up to 9 spaces in Go by John August.
				 * This will mostly ruin our ability to detect DualDialog in PDFs.
				 * We'll probably have to detect that the previous line of
				 * dual dialog is the character names, then split aggressively.
				 */
				$spaces = "            ";
				$end = mb_strpos($text, $spaces, $i);
				if ($end === FALSE)
					$end = mb_strlen($text);
				$preceding = mb_substr($text, 0, $i);
				$str = mb_substr($text, $i, $end-$i);
				$pageText .= $str . "\n";

				// print_r($lineObject);
				$trimX = mb_strlen($preceding) * $GLOBALS["default_char_width"];

				// print_r($preceding . "\n");

				$block_pieces[] = new NGText_BlockPiece($str, $lineObject->x + $trimX, $lx, $lineObject->width - $trimX, $lineObject->line_colors, $lineObject->line_fontSize, $lineObject->lineTextAttributes, $lineObject->lineNumberObject);
				$numLinesWithText++;
				$addedBlock = true;

				$i = $end;
				if ($i < mb_strlen($text)) {
					$i += mb_strlen($spaces);
					if ($i > mb_strlen($text)) {
						$i = mb_strlen($text);
					}
				}

				// print_r($text . "\n");
				// print_r("A$i" . mb_substr($text, $i, 1) . (mb_substr($text, $i, 1) == ' ') . "\n");
				while ($i != mb_strlen($text) && mb_substr($text, $i, 1) == ' ') ++$i;
				// print_r($i . "\n");
			}

			if (!$addedBlock) {
				$block_pieces[] = new NGText_BlockPiece("", $lineObject->x + $i * 11, $lx, 0, $lineObject->line_colors, $lineObject->line_fontSize, $lineObject->lineTextAttributes, $lineObject->lineNumberObject);
			}
		}
		
		// If this is the first page of the script, and it contains fewer than
		// 30 block-pieces in total, then it's probably not real content yet.
		// Treat this title page specially.
		if ($look_for_fly_page &&
				empty($this->objects) && ($numLinesWithText < 30 && mb_strlen($pageText) < 300)) {
			//echo "detected title page";
			$objects = array();

			// print_r($block_pieces);

			foreach ($block_pieces as $piece) {
				$text = trim($piece->text);
				if ($piece->fontSize == 0)
					continue;
				$o = new ScriptObject("Text", $text, $this->num_pages, $this->given_page_num, $piece->colors, $piece->fontSize, $piece->textAttributes, $piece->numberObject);

				// Determine if the piece has any special attributes (e.g. alignment)
				$alignment = getAlignment($piece->x, $piece->width, 1*INCH, 8.5*INCH);
				if ($alignment != "left")
					$o->setAttribute("alignment", $alignment);
				
				$objects[] = $o;
			}
			$this->objects = classify_fly_page_objects($objects);

			return;
		} elseif (empty($this->objects)) {
			//echo "didn't detect title page";
			// Add a fake page, where we'll stick in a fake title page
			$this->num_pages++;
		}

		// Now combine vertically-adjacent block pieces into bigger blocks.
		// Assume that we'll have fewer than 10 "active" blocks on any given
		// line; I'm sure that's a good assumption. In practice we should have
		// only one most of the time, and never more than three.
		// [guyg] I say 20... I don't feel comfortable with any limit, honestly
		$blocks = array();
		$last_place_a_blockpiece_was_combined = -1;
		foreach ($block_pieces as $block_piece) {
			global $SERIES_TIMER;
			if (looks_like_slugline($block_piece->text)) {
				$SERIES_TIMER = 0;
				if (looks_like_series_or_montage_slugline($block_piece->text))
					$SERIES_TIMER = 20;
			}
			$done = FALSE;
			for ($ax = max(0, count($blocks)-20); !$done && $ax != count($blocks); ++$ax) {
				$intervening_blockpiece = ($ax != $last_place_a_blockpiece_was_combined);
				if ($blocks[$ax]->should_combine($block_piece, $intervening_blockpiece)) {

					$blocks[$ax]->combine($block_piece);
					$last_place_a_blockpiece_was_combined = $ax;
					$done = TRUE;
				}
			}
			if ($done === FALSE) {
				$blocks[] = new NGText_Block($block_piece);
				$last_place_a_blockpiece_was_combined = count($blocks)-1;
			}
		}
		
		maybe_merge_across_gutters($blocks);
		maybe_merge_across_intersentence_spaces($blocks);

		if ($GLOBALS['DUMP_BLOCKS']) {
			print "Before classifying based on content:\n";
			print_r($blocks);
			print_r($likely_indent_for);
		}

		// Now for each block, figure out what kind of block it probably is.
		foreach ($blocks as $bx => $block) {
			$prev_kind = ($bx > 0) ? $blocks[$bx-1]->kind : $last_page_block_kind;
			if ($prev_kind == "Page Number")
				$prev_kind = ($bx > 1) ? $blocks[$bx-2]->kind : $last_page_block_kind;
			$block->classify_based_on_content($prev_kind, $bx);
		}
		
		// Now figure out the likely indentation level for each type of block.
		$likely_indent_for = nextgen_compute_indents($blocks);
		if ($likely_indent_for === FALSE)
			$likely_indent_for = prevgen_compute_indents($blocks);
			
		// [guyg] Be careful - if Dialog might be left-aligned then give
		// it precedence, because we're probably on a page with no Action/Sluglines
		// Only do this when there's some Character, so we don't do it on
		// Action-only pages
		if (isset($likely_indent_for["Character"]) && isset($likely_indent_for["Dialog"]) && $likely_indent_for["Dialog"] === 0) {
			if (isset($likely_indent_for["Action"]) && $likely_indent_for["Action"] === 0)
				unset($likely_indent_for["Action"]);
			if (isset($likely_indent_for["Slugline"]) && $likely_indent_for["Slugline"] === 0)
				unset($likely_indent_for["Slugline"]);
		}
		
		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After classifying based on content:\n";
			print_r($blocks);
			print_r($likely_indent_for);
		}

		// Reclassify block kinds based not only on content but also on indentation level.
		// Basically, if we're unsure about the kind, and the block is on the right
		// indentation level for a common kind, then we'll assume it's that kind.
		foreach ($blocks as $ax => $block) {
			$maybes = array();

			$common_kinds = array("Character", "Dialog", "Slugline", "Action", "Transition");
			foreach ($common_kinds as $k => $v) {
				if (!isset($likely_indent_for[$v])) continue;
				$diff_range = 3; // [guyg] Indents aren't always exact :-(
				if (abs($block->x - $likely_indent_for[$v]) <= $diff_range)
					$maybes[] = $v;
			}
			$block->maybes = $maybes;
			if ($block->classification_is_unsure) {
				$block->reclassify_using_indent($maybes);
			}
			if ($block->classification_is_unsure &&
					$block->is_uppercase_single_line()) {
				// [guyg] Arthur's new heuristic is this:
				// if ($block->x >= 40 || $block->x + $block->width >= 55) {
				if ($block->x > 600 || ($block->x > 400 && $block->x + $block->width > 700)) {
					// Some transitions aren't on our whitelist, but if it's
					// uppercase and right-aligned, it's got to be a transition.
					$block->kind = "Transition";
					// [guyg] Arthur's new code doesn't have this
					$block->classification_is_unsure = TRUE;
				}
			}
			if ($block->is_transition_follower() && $ax > 20 &&
				$blocks[$ax-1]->classification_is_unsure &&
				$blocks[$ax-1]->is_uppercase_single_line()) {
				// If it's uppercase and followed by a slugline/action,
				// it's probably a transition, too.
				$blocks[$ax-1]->kind = "Transition";
			}
		}

		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After reclassifying based on indent:\n";
			print_r($blocks);
		}

		// If we've identified the indent levels for "Dialog" and "Character",
		// but not for "Slugline" and "Action", then let's reclassify all
		// "Unknown" with the most common indentation as sluglines and actions
		// depending on its capitalization.
		$common_kinds = array("Character", "Dialog", "Slugline", "Action", "Unknown");
		foreach ($common_kinds as $v) {
			$likely_indent_for[$v] = get_likely_indent_for($blocks, $v);
			assert($likely_indent_for[$v] === FALSE || $likely_indent_for[$v] >= 0);
		}
		if ($likely_indent_for["Action"] === FALSE &&
			$likely_indent_for["Slugline"] === FALSE &&
			$likely_indent_for["Character"] !== FALSE &&
			$likely_indent_for["Dialog"] !== FALSE &&
			$likely_indent_for["Unknown"] !== FALSE) {
			// That "Unknown" is probably actions and sluglines. Reclassify it.
			foreach ($blocks as $block) {
				if ($block->classification_is_unsure && $block->x == $likely_indent_for["Unknown"]) {
					$block->kind = (is_uppercase($block->lines[0]) ? "Slugline" : "Action");
					$block->classification_is_unsure = TRUE;
				}
			}
		}

		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After reclassifying Unknown as Action/Slugline:\n";
			print_r($blocks);
		}

		// In "1492" we have a block of "scrolling epilogue text", indented to the
		// same level as ordinary dialog. For each putative dialog block on this page,
		// scan backward; if we hit a slugline before we hit a character name, then
		// this is definitely not dialog, and we should reclassify it as either "Action"
		// (if the indent is appropriate) or "Unknown".
		//   "Spark of Hope" has a block of "sign text" indented as dialog, preceded
		// by an Action.
		foreach ($blocks as $ax => $block) {
			if ($block->kind != "Dialog")
				continue;
			
			$saw_action = FALSE;
			for ($px = $ax-1; $px >= 0; --$px) {
				$prev_kind = $blocks[$px]->kind;
				if ($prev_kind == "Character" || $prev_kind == "Paren")
					break;
				if ($prev_kind == "Action" && isset($blocks[$px-1]) &&
						in_array($blocks[$px-1]->kind, array("Character", "Dialog", "Paren")) &&
						isset($blocks[$ax+1]) && $blocks[$ax+1]->kind == "Character") {
					// "The Short Straw" uses Actions embedded in the middle of Dialog, which is
					// definitely malformed, but I'd like to handle it if we can. If this *definitely*
					// looks like such a case, then let this block stay as Dialog.
					$block->classification_is_unsure = FALSE;
					break;
				}
				if ($prev_kind == "Slugline" || $prev_kind == "Transition" || $prev_kind == "Action") {
					$blocks[$ax]->kind = (($block->x == $likely_indent_for["Action"]) ? "Action" : "Unknown");
					break;
				}
			}
		}
		
		// Identify "Scene Number" blocks by their proximity to sluglines.
		foreach ($blocks as $ax => $block) {
			$prev = isset($blocks[$ax-1]) ? $blocks[$ax-1] : FALSE;
			$next = isset($blocks[$ax+1]) ? $blocks[$ax+1] : FALSE;
			if ($block->height == 1 && looks_like_scene_number($block->lines[0]) &&
					($prev !== FALSE && $prev->y == $block->y && $prev->kind == "Slugline" ||
					 $next !== FALSE && $next->y == $block->y && $next->kind == "Slugline")) {
				$blocks[$ax]->kind = "Scene Number";
				$blocks[$ax]->classification_is_unsure = FALSE;
			}
		}

		// [guyg] Arthur's code looks for date here, to identify Page Headers,
		// but it's too late because those already messed up classification.
		
		// Remove any "Page Number" blocks; we don't need to track those.
		foreach ($blocks as $ax => $block) {
			if (!isset($blocks[$ax]))
				continue;
			$prev = isset($blocks[$ax-1]) ? $blocks[$ax-1] : FALSE;
			$next = isset($blocks[$ax+1]) ? $blocks[$ax+1] : FALSE;
			if ($block->kind == "Scene Number") {
				// Remove scene numbers from sluglines.
				unset($blocks[$ax]);
			} else if ($block->kind == "Page Number") {
				// Remove page numbers.
				unset($blocks[$ax]);
				// Remove decorations preceding/following page numbers;
				// some plain-text scripts use these to indicate page breaks.
				if ($prev !== FALSE && $prev->kind == "Horizontal Rule")
					unset($blocks[$ax-1]);
				if ($next !== FALSE && $next->kind == "Horizontal Rule")
					unset($blocks[$ax+1]);
			} else if ($block->kind == "Changebar") {
				// Remove vertical changebars.
				unset($blocks[$ax]);
			}
			
		}
		$blocks = array_values($blocks);  // compact the array again
			
		// [guyg] If we end on a page that has only one Dialog, it's the first block,
		// and no Action/Slugline, then it's likely that the Dialog is really Action/Slugline
		// that got misclassified because the previous page ended with Dialog.
		$first_dialog_misclassified = false;
		foreach ($blocks as $num => $block) {
			if ($num == 0) {
				if ($block->kind == "Dialog") {
					$first_dialog_misclassified = true;
					continue;
				} else {
					$first_dialog_misclassified = false;
					break; // Only if it starts with Dialog
				}
			} else if ($block->kind == "Dialog" || $block->kind == "Action" || $block->kind == "Slugline") {
				$first_dialog_misclassified = false;
				break;
			}
		}
		if ($first_dialog_misclassified) {
			$block = $blocks[0];
			$is_slugline = ($block->height == 1 && looks_like_slugline($block->lines[0]));
			$block->kind = ($is_slugline ? "Slugline" : "Action");
		}


		// [guyg] If we have a Character but no Dialog/Paren before the next Character,
		// that probably means we screwed up. Should likely be Action/Slugline
		$previous_character = NULL;
		foreach ($blocks as $block) {
			if ($block->kind == "Character") {
				if ($previous_character !== NULL) {
					// Character just sitting there... should probably be Action/Slugline
					$is_slugline = ($previous_character->height == 1 && looks_like_slugline($previous_character->lines[0]));
					$previous_character->kind = ($is_slugline ? "Slugline" : "Action");
				}
//				print_r($block);
				$previous_character = $block;
			} else if ($block->kind == "Dialog" || $block->kind == "Paren")
				// Once we hit a Dialog/Paren, that justifies the last Character block
				$previous_character = NULL;
		}
		// [guyg] ****FIXME!!! Why is this necessary? Will break when the Character ends a page, which it occasionally does
		if ($previous_character !== NULL) {
			// Character just sitting there... should probably be Action/Slugline
			// This is technically too aggressive is in the case where a Character
			// ends a page, and then Dialog starts the next, but that's a stupid case anyway.
			$is_slugline = ($previous_character->height == 1 && looks_like_slugline($previous_character->lines[0]));
			$previous_character->kind = ($is_slugline ? "Slugline" : "Action");
		}

		if ($GLOBALS['DUMP_BLOCKS']) {
			print "Before reclassifying dual dialogue:\n";
			print_r($blocks);
		}

		// Try to figure out Dual Dialog blocks. These have been misclassified, but hopefully haven't
		// messed the rest of the page up. Try to dig them out and fix!!!
		$num_blocks = count($blocks);
		for ($bx = 0; $bx < $num_blocks; $bx++) {
			$character1 = $blocks[$bx];

			// Only bother checking when there are at least 3 blocks, 2 for the Characters plus 1 (or more)
			// for Dialog. Could be 1, smushed together because not enough space between them
			if ($bx + 2 >= $num_blocks)
				break;
				
			$dialog_2_spliced = array();
				
			$character2 = $blocks[$bx + 1];
			if ($character1->y == $character2->y && $character1->height == 1 && $character2->height == 1 && looks_like_character($character1->lines[0]) && looks_like_character($character2->lines[0])) {
			    
				$dialog_index_list_2 = array();
				
				$prev1 = $character1;
				$prev2 = $character2;
				
				for ($bx_search = $bx + 2; $bx_search < $num_blocks; $bx_search++) {
					$block = $blocks[$bx_search];

					// print_r(($blocks));
					
					$in_char1_range = ($block->x < $character1->x + 5*$GLOBALS["default_char_width"]);
					if ($block->isBlank()) {
						break;
					} elseif ($block->y == $prev1->y + $prev1->height && $in_char1_range) {
						$is_dialog_1 = true;
					} elseif ($block->y == $prev2->y + $prev2->height && !$in_char1_range) {
						$is_dialog_1 = false;
					} else
						break;

                    // NOTE: Dialog is converted to Paren later if needed.
					if ($is_dialog_1) {
						for ($i = 0; $i < $block->height; $i++) {
							$next = ($bx_search + 1 < $num_blocks ? $blocks[$bx_search + 1] : NULL);
							if ($block->y + $i == $prev2->y + $prev2->height) {
								if ($next == NULL || $next->y != $block->y + $i) {
									// The next block is not on the same line, so it can't be a dialog2 block. See if we can split this one.
									$line = $block->lines[$i];
									$last_gap_str = NULL;
									for ($gap_size = 3; $gap_size < 10; $gap_size++) {
										$gap_str = str_repeat(" ", $gap_size);
										$split_pos = mb_strpos($line, $gap_str);
										if ($split_pos === FALSE)
											break;
										$last_gap_str = $gap_str;
									}
									
									$split_pos = FALSE;
									if ($last_gap_str !== NULL) {
										$pos = 0;
										do {
											$pos = mb_strpos($line, $last_gap_str, $pos + mb_strlen($last_gap_str));
											if ($pos !== FALSE && $block->x + $pos < $character2->x)
												$split_pos = $pos;
										} while ($pos !== FALSE);
									}
										
									// print_r("$line $split_pos \n");

									if ($split_pos !== FALSE) {
										$second_line = trim(mb_substr($line, $split_pos + 1));
										$second_x = $block->x + (mb_strlen($line) - mb_strlen($second_line)) * $GLOBALS["default_char_width"];

										$block->lines[$i] = trim(mb_substr($line, 0, $split_pos));

										$second_line_width = mb_strlen($second_line) * $GLOBALS["default_char_width"];

										if (abs($second_x - $prev2->x) > 2 * $GLOBALS["default_char_width"]) {
											//nico note: the array()s are a placeholder, gotta figure out regular line revisions and bold/italics and line numbers before I do dual dialogue
											$prev2 = new NGText_Block(new NGText_BlockPiece($second_line, $second_x, $block->y + $i, $second_line_width, array(), $block->fontSize, array(), $block->numberObject));
											$prev2->kind = $prev2->isEntirelyParens() ? "Paren" : "Dialog";
											$dialog_2_spliced[] = $prev2;
										} else
										{
											// print_r("Combine $second_line \n");
											//nico note: the array()s are a placeholder, gotta figure out regular line revisions and bold/italics and line numbers before I do dual dialogue
											$prev2->combine(new NGText_BlockPiece($second_line, $second_x, $block->y + $i, $second_line_width, array(), $block->fontSize, array(), $block->numberObject));
										}
									}
								} else {
									// The next block goes right here, on the right side of the dual blocks
									if (abs($next->x - $prev2->x) > 2 * $GLOBALS["default_char_width"]) {
										$prev2 = $next;
										$prev2->kind = $prev2->isEntirelyParens() ? "Paren" : "Dialog";
										$dialog_2_spliced[] = $prev2;
									} else {
										foreach ($next->lines as $num => $line)
										{
											//nico note: the array()s are a placeholder, gotta figure out regular line revisions and bold/italics and line numbers before I do dual dialogue
											$prev2->combine(new NGText_BlockPiece($line, $next->x, $next->y + $num, mb_strlen($line) * $GLOBALS["default_char_width"], array(), $next->fontSize, array(), $block->numberObject));
										}
									}
									array_splice($blocks, $bx_search + 1, 1); // Remove $next from blocks. It'll get added back later
									$num_blocks = count($blocks); // Removed 1, recompute

									// Don't need to subtract from index, since we haven't reached $next yet, and never will
								}
							}
						}
						$prev1 = $block;
						$prev1->kind = $prev1->isEntirelyParens() ? "Paren" : "Dialog";
						$last_dialog_1 = $bx_search;
					} else {
						if ($prev1 == $character1)
							break; // No left side of the dual dialog block, since it would be first. Abort.
						$prev2 = $block;
						$prev2->kind = $prev2->isEntirelyParens() ? "Paren" : "Dialog";
						$dialog_2_spliced[] = $prev2;

						array_splice($blocks, $bx_search, 1); // Remove it from blocks. It'll get added back later
						$num_blocks = count($blocks); // Removed 1, recompute
						$bx_search--; // Since we removed one, when it increments it'll now go to the next block
					}
				}
				
				if (empty($dialog_2_spliced)) {
					write_log("Error: Splitting dual missing a side of dual dialog");
					continue;
				}
				
				$character1->kind = $character2->kind = "Character";
				$character1->has_dual_line = true;
				$character2->is_dual_line = true;
				
				// Pull $character2 out of the list - we'll add it back in the splice
				array_splice($blocks, $bx + 1, 1);
				array_splice($dialog_2_spliced, 0, 0, array($character2));
				
				// We already removed all dialog_2_spliced objects, now put them at the end of dialog1 objects
				array_splice($blocks, $last_dialog_1, 0, $dialog_2_spliced);
				
				//print_r($blocks);

				$bx = $last_dialog_1 + count($dialog_2_spliced) - 1; // It's going to get incremented in the loop

        		$num_blocks = count($blocks); // Added 1, recompute

				// Make sure the next line after is NOT considered Dialog, since it wouldn't been part of dialog_2_spliced if it was
				if ($bx + 1 < $num_blocks) {
				    if ($blocks[$bx + 1]->kind == "Dialog")
				        $blocks[$bx + 1]->kind = "Action";
				}
    			
			}
			
		}
		
		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After reclassifying dual dialogue:\n";
			print_r($blocks);
		}

		foreach ($blocks as $blockIndex => $block) {
			$kind = $block->kind;
			if ($kind == "Unknown") {
				$kind = "Action";
				if ($blockIndex > 0) {
					$prevBlock = $blocks[$blockIndex - 1];
					if (!$prevBlock->isBlank()) {
						// If this line follows a non-blank line, that means it's tight with it so 
						// use Text to avoid adding any top margin space
						switch ($prevBlock->kind) {
							case "Character":
							case "Dialog":
							case "Paren":
								$kind = "Dialog";
								break;
							default:
								$kind = "Text";
								break;
						}
					}
				}

				$block->kind = $kind;
			}
		}

		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After converting Unknown:\n";
			print_r($blocks);
		}

		foreach ($blocks as $blockIndex => $block) {
			$kind = $block->kind;

			if ($kind == "Action") {
				if ($blockIndex > 0) {
					if (!$blocks[$blockIndex - 1]->isBlank()) {
						// Action-like line that is tight to previous line is probabably Text
						$block->kind = "Text";
					} else if ($blockIndex >= 2 && $blocks[$blockIndex - 2]->isBlank() && $block->is_uppercase_single_line()) {
						// Action-like all-caps line with 2 blank lines before it is probably a Shot
						$block->kind = "Shot";
					}
				}
			}
		}
		
		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After changing Action to Text or Shot based on vertical spacing:\n";
			print_r($blocks);
		}


		$linesBeforeType = array("Action" => 1, "Character" => 1, "Transition" => 1, "Slugline" => 2, "Shot" => 2);
		$seenRealLineThisPage = false;
		$num_blocks = count($blocks);
		for ($i = 0; $i < $num_blocks; $i++) {
			$block = $blocks[$i];

			if ($block->isBlank()) {
				// Check if the blank line is necessary as a spacer
				$nextBlockKind = "";
				for ($j = $i + 1; $j < $num_blocks; $j++) {
					$nextBlock = $blocks[$j];
					if (!$nextBlock->isBlank()) {
						$nextBlockKind = $nextBlock->kind;
						break;
					}

				}

				$numBlanks = ($j - $i);
				$marginNumBlanks = $nextBlockKind == "" ? $numBlanks : ((isset($linesBeforeType[$nextBlockKind]) ? $linesBeforeType[$nextBlockKind] : 0));
				if ($numBlanks == $marginNumBlanks) {
					// print("Exact match margins\n");
				} else {
					// print($nextBlockKind);
					// print("Blanks mismatch: $numBlanks != $marginNumBlanks\n");
					// print_r($nextBlock);
				}
				$numBlanksToRemove = min($numBlanks, $marginNumBlanks);

				if (!$seenRealLineThisPage && $marginNumBlanks > 0) {
					// This is the first line, so if we remove all blanks it would lose its margin completely at the top
					// of the page.
					if ($numBlanksToRemove == $numBlanks) {
						// print_r($nextBlock);
						// It would go down to a margin of 0 if we let every blank be removed
						$numBlanksToRemove--;
					}
				}

				// Remove the number of blanks that are covered by the line type's margins (i.e. remove blanks that are automatic spacing)
				for ($k = $i; $k < $i + $numBlanksToRemove; $k++) {
					unset($blocks[$k]);
				}
			
				
				$i = $j - 1;
			} else {
				$seenRealLineThisPage = true;
			}

		}
		$blocks = array_values($blocks);  // compact the array again


		if ($GLOBALS['DUMP_BLOCKS']) {
			print "After removing unnecessary blank lines:\n";
			print_r($blocks);
		}


		// No longer attempting to split Action blocks apart, since with the XML parser
		// we should know exactly when there are lines between two blocks or not
		/*
		// [guyg] For Action, split lines if they seem like they should
		// have a line between them, but pdftotext condensed them
		$num_blocks = count($blocks);
		for ($i = 0; $i < $num_blocks; $i++) {
			$block = $blocks[$i];
			if ($block->kind == "Action" || $block->kind == "Text" || $block->kind == "Unknown") {
				// Redo all Action lines, classifying depending on length
				// and if the last line ends a sentence.
				$x = $block->x;
				$y = $block->y;
				$new_blocks = array();
				$last_line = NULL;
				foreach ($block->lines as $line) {
					$text_block = new NGText_BlockPiece($line, $x, $y);

					if ($last_line === NULL || !continue_action($last_line, $line)) {
						$new_block = new NGText_Block($text_block);
						$new_block->kind = $block->kind;
						$new_blocks[] = $new_block;
					} else {
						$new_block->combine($text_block);
					}
					$last_line = $line;
					$y++;
				}
				
				// Strip $block, and add in $new_blocks to replace it
				array_splice($blocks, $i, 1, $new_blocks);
				$change = count($new_blocks) - 1;
				$num_blocks += $change;
				$i += $change;
				

			}
		}
		
		if ($GLOBALS['DUMP_BLOCKS']) {
			print "Before turning blocks into objects:\n";
			print_r($blocks);
		}
		*/

		$full_width_line_types = array("Action", "Slugline", "Text", "Shot");

		// Now turn the blocks into Objects.
		$objects = array();
		$counter = 0;
		foreach ($blocks as $blockIndex => $block) {
			if ($counter == 0)
			{
				//print_r($block);
				$counter = 1;
			}
			$kind = $block->kind;
			if ($kind == "Horizontal Rule") $kind = "Text";
			if ($kind == "Contact Info") {
				foreach ($block->lines as $x => $line) {
					$objects[] = new ScriptObject("Text", reduce_spaces($line), $this->num_pages, $this->given_page_num, $block->colors[$x], $block->fontSize, $block->textAttributes[$x], $block->numberObject);
				}
			} else {
				$blockValues = $block->get_text_and_colors();
				$o = new ScriptObject($kind, reduce_spaces($blockValues["text"]), $this->num_pages, $this->given_page_num, $blockValues["colors"], $block->fontSize, $blockValues["textAttributes"], $block->numberObject);
				$o->set_num_lines($block->height);
				$o->set_is_dual_line($block->is_dual_line);
				$o->set_has_dual_line($block->has_dual_line);

				// Determine if the block has any special attributes (e.g. alignment)
				if (in_array($kind, $full_width_line_types)) {
					$alignment = getAlignment($block->x, $block->width, 1.5*INCH, 8.5*INCH);
					if ($alignment != "left")
						$o->setAttribute("alignment", $alignment);
				} elseif ($kind == "Transition") {
					$alignment = getAlignment($block->x, $block->width, 1.5*INCH, 8.5*INCH);
					if ($alignment != "right")
						$o->setAttribute("alignment", $alignment);
				}

				$objects[] = $o;
				maybe_break_up_last_dialog($objects);
			}
			
			if (false) {
				// NOTE: This isn't worth it in general, on other scripts it messes things up to take the
				// first block as dialogue so aggressively

				// [guyg] If we end with Dialog, pretend it's a Character
				// for the next page, so we'll be thinking it might be Dialog
				// that spilled over to the next page (if that's what it looks like).
				// Not really necessary, but could be helpful if there's NO other
				// dialog on the next page, as happened in Spark of Hope (33 -> 34)
				if ($kind == "Dialog")
					$last_page_block_kind = "Character";
				else
					$last_page_block_kind = $kind;
			}
		}
		
		// If this page begins with dialog and the last page ends with dialog,
		// assume that we ought to merge the two objects.
		$N = count($this->objects);
		if ($N > 0 && !empty($objects) && $this->objects[$N-1]->get_type() == "Dialog" && $objects[0]->get_type() == "Dialog") {
			$new_fontSize = max($this->objects[$N-1]->getFontSize(), $objects[0]->getFontSize());
			$this->objects[$N-1] = new ScriptObject("Dialog", $this->objects[$N-1]->get_content() . " " . $objects[0]->get_content(), $this->num_pages, $this->given_page_num, $this->objects[$N-1]->get_merged_colors($objects[0], " "), $new_fontSize, $this->objects[$N-1]->get_merged_textAttributes($objects[0], " "), $this->objects[$N-1]->getNumberObject());
			array_shift($objects);
		}

		$this->objects = array_merge($this->objects, $objects);
	}
}

function classify_fly_page_objects(&$objects)
{
	// print_r($objects);

	// We handle these three common cases so far.
	//
	// (A)                       (B)
	//        Fight Club                Fight Club
	//     Chuck Palahniuk          by Chuck Palahniuk
	//
	// (C)
	//        Fight Club
	//        written by
	//     Chuck Palahniuk
	
	assert(is_array($objects));
	
	// [guyg] Loop through list of objects, looking for "by"
	$foundTitle = false;
	$foundAuthor = false;
	$nextIsAuthor = false;
	$possibleAuthorIndex = -1;
	for ($i = 0; $i < count($objects); $i++) {
		$text = $objects[$i]->get_content();
		if ($text == "") {
			continue;
		}

		if ($nextIsAuthor) {
			$objects[$i]->set_type("Author");
			$foundAuthor = true;
			$nextIsAuthor = false;			
		}
		
		if (!$foundTitle) {
			$objects[$i]->set_type("Title");
			$foundTitle = true;
			continue;
		}

		if ($possibleAuthorIndex == -1) {
			// First line that's not Title might be Author, if we don't find a definitive Author
			$possibleAuthorIndex = $i;
		}

		if (!$foundAuthor) {
			if (preg_match("/(^| )[Bb][Yy]$/", $text)) {
				$nextIsAuthor = true;
			} else if (preg_match("/(^| )[Bb][Yy] /", $objects[$i]->get_content())) {
				$by_pos = stripos($objects[$i]->get_content(), "by ");
				assert($by_pos !== FALSE);
				$original_length = strlen($objects[$i]->get_content());
				$author_name = substr($objects[$i]->get_content(), $by_pos+3);
				$colors = $objects[$i]->get_colors();
				$textAttributes = $objects[$i]->getTextAttributes();
				//split out the colors and attributes for the "by" half
				$split_colors = split_attribute($colors, 0, $by_pos+2);
				$split_textAttributes = split_attribute($textAttributes, 0, $by_pos+2);
				//make a new object of just the by half and put it where the whole object used to be
				$objects[$i] = new ScriptObject($objects[$i]->get_type(), substr($objects[$i]->get_content(), 0, $by_pos+2), $objects[$i]->get_page_num(), $objects[$i]->get_given_page_num(), $split_colors, $objects[$i]->getFontSize(), $split_textAttributes, $objects[$i]->getNumberObject());
				//split out the colors and attributes for the "author" half 
				$split_colors = split_attribute($colors, $by_pos+3, $original_length);
				$split_textAttributes = split_attribute($textAttributes, $by_pos+3, $original_length);
				//shove a new object in there right after the "by" object with just the author info
				array_splice($objects, $i + 1, 0, array(new ScriptObject("Author", $author_name, $objects[$i]->get_page_num(), $objects[$i]->get_given_page_num(), $split_colors, $objects[$i]->getFontSize(), $split_textAttributes, array())));
				$foundAuthor = true;
			}
		}
	}
	if (!$foundAuthor && $possibleAuthorIndex != -1) {
		// Just assume that the second line is the author's name.
		$objects[$possibleAuthorIndex]->set_type("Author");
	}
	return $objects;
}

function get_likely_indent_for($blocks, $kind)
{
	assert(is_array($blocks));
	$counts = array();
	foreach ($blocks as $block) {
		if ($block->kind == $kind) {
			if (!isset($counts[$block->x]))
				$counts[$block->x] = 0;
			// [guyg] Arthur added value for height - I disagree. All the same.
			$counts[$block->x]++;
		}
	}
	if (!empty($counts)) {
		$max_keys = array_keys($counts, max($counts));
		if ($kind == "Character" || $kind == "Dialog") {
			// [guyg] For Dialog and Character, we want the biggest key,
			// in case of a tie. Dialog could appear to be 0 or something
			// else, and we want the something else!
			return end($max_keys);
		} else {
			// [guyg] For Action and Slugline, 0 is ideal
			return $max_keys[0];
		}
	}
	return FALSE;
}

$SERIES_TIMER = 0;
function get_series_indent($text)
{
	global $SERIES_TIMER;
	if ($SERIES_TIMER == 0)
		return 0;
	$matches = array();
	if (preg_match("/^(\(?[A-Z][.)]\)?[ ]+)/", $text, $matches)) {
		$SERIES_TIMER = 10;
		return mb_strlen($matches[1]);
	} elseif (preg_match("/^(\(?[A-Z][.)]\)?)$/", $text)) {
		// We have to match "B)" by itself, because of
		//   A)        First shot.
		//   B)        Second shot.
		// which comprises four block-pieces.
		$SERIES_TIMER = 10;
		return 999;
	} else {
		$SERIES_TIMER -= 1;
		return 0;
	}
}

class LineObject {
	public $x, $width, $text, $given_page_num, $line_colors, $line_fontSize, $lineTextAttributes, $lineNumberObject;
	function __construct($str, $x, $width, $given_page_num, $line_colors, $line_fontSize, $lineTextAttributes, $lineNumberObject) {
		assert($x >= 0);
		$this->x = $x;
		$this->width = $width;
		$this->text = $str;
		$this->given_page_num = $given_page_num;
		$this->line_colors = $line_colors;
		$this->line_fontSize = $line_fontSize;
		$this->lineTextAttributes = $lineTextAttributes;
		$this->lineNumberObject = $lineNumberObject;
		//print_r($lineNumberObject);
	}
}


class NGText_BlockPiece {
	public $x, $y, $width, $text, $colors, $fontSize, $textAttributes, $numberObject;
	function __construct($str, $x, $y, $width, $colors, $fontSize, $textAttributes, $numberObject) {
		assert($x >= 0);
		assert($y >= 0);
		$this->x = $x;
		$this->y = $y;
		$this->width = $width;
		$this->text = trim($str);
		$this->colors = $colors;
		$this->fontSize = $fontSize;
		$this->textAttributes = $textAttributes;
		$this->numberObject = $numberObject;
	}
}

class NGText_Block {
	public $x, $y;  // column (resp. row) of the block's top left corner
	public $lines;  // an array of strings
	public $width, $height;  // number of columns (resp. rows) the block occupies
	public $kind;  // a string ("Character", "Action", "The End", etc.)
	public $classification_is_unsure;  // TRUE or FALSE
	public $hanging_punctuation;  // TRUE or FALSE
	public $maybes;
	public $series_indent;  // 0, or for example mb_strlen("A) ")
	public $has_dual_line;
	public $is_dual_line;
	public $colors;
	public $fontSize;
	public $textAttributes;
	public $numberObject;

	function isBlank()
	{
		return $this->height == 1 && $this->lines[0] == "";
	}

	function firstChar()
	{
		return mb_substr($this->lines[0], 0, 1);
	}
	function lastChar()
	{
		return mb_substr($this->lines[count($this->lines) - 1], -1);
	}
	function isEntirelyParens()
	{
		return ($this->firstChar() == '(' || $this->firstChar() == '[') && ($this->lastChar() == ')' || $this->lastChar() == ']');
	}


	function __construct($block_piece) {
		$this->x = $block_piece->x;
		$this->y = $block_piece->y;
		$this->lines = array($block_piece->text);
		$this->width = $block_piece->width;
		$this->height = 1;
		$this->hanging_punctuation = FALSE;
		$this->maybes = array();
		$this->is_dual_line = false;
		$this->has_dual_line = false;
		$this->given_page_num = $block_piece->given_page_num;
		$this->colors = array($block_piece->colors);
		$this->fontSize = $block_piece->fontSize;
		$this->textAttributes = array($block_piece->textAttributes);
		$this->numberObject = $block_piece->numberObject;
	}


	function combine($block_piece) {
		if ($block_piece->y == $this->y + $this->height) {
			if ($this->series_indent > 0) {
				// okay
			} elseif ($block_piece->x < $this->x) {
				$this->width += ($this->x - $block_piece->x);
				$this->hanging_punctuation = TRUE;
			} else if ($block_piece->x > $this->x) {
				$this->hanging_punctuation = TRUE;
			}
			$this->lines[] = $block_piece->text;
			$this->colors[] = $block_piece->colors;
			$right_edge = $block_piece->x + $block_piece->width;
			$this->width = max($this->width, $right_edge - $this->x);
			$this->height += 1;
			$this->fontSize = max($this->fontSize, $block_piece->fontSize);
			$this->textAttributes[] = $block_piece->textAttributes;
			if (!isset($this->numberObject["number"]) && isset($block_piece->numberObject["number"]))
			{
				$this->numberObject["number"] = $block_piece->numberObject["number"];
			}
			if (isset($this->numberObject["left"]) || isset($block_piece->numberObject["left"]))
			{
				$this->numberObject["left"] = 1;
			}
			if (isset($this->numberObject["right"]) || isset($block_piece->numberObject["right"]))
			{
				$this->numberObject["right"] = 1;
			}
		} else if ($block_piece->y == $this->y + $this->height-1) {
			// SHOULD NO LONGER MERGE HORIZONTALLY ADJACENT BLOCKS HERE!
			assert(FALSE);

			/*
			$right_edge = $this->x + mb_strlen($this->lines[$this->height-1]);
			assert($block_piece->x > $right_edge);  // guaranteed
			assert($block_piece->x <= $right_edge + 3);  // assured by should_combine()
			$this->lines[$this->height-1] .= str_repeat(" ", $block_piece->x - $right_edge);
			$this->lines[$this->height-1] .= $block_piece->text;
			$this->width = max($this->width, mb_strlen($this->lines[$this->height-1]));
			*/
		} else {
			assert(FALSE);
		}
	}
	function should_combine($block_piece) {
		if ($block_piece->fontSize != $this->fontSize) {
			return FALSE;
		}
		// Geometric constraints.
		// write_log($block_piece->text . " - " . $block_piece->x);
		if ($block_piece->y == $this->y + $this->height && $block_piece->text != "" && !$this->isBlank()) {
/*			if ($block_piece->x == $this->x) {
				// this is always okay
			} else if ($block_piece->x == $this->x - 1) {
				//       I haven't seen this yet, but
				//       it could conceivably happen
				//      (like this, for example).
				if ($this->hanging_punctuation) return FALSE;  // prevent "sliding" left multiple times
				$block_piece_should_hang = mb_strstr("('\"", mb_substr($block_piece->text, 0, 1));
				if (!$block_piece_should_hang) return FALSE;
			} else */
			if (get_series_indent($this->lines[0]) > 0 && $block_piece->x == $this->x && get_series_indent($block_piece->text) > 0) {
				// Do not merge these two blocks:
				//   A)  Some action.
				//   B)  Some more action.
				return FALSE;
			} elseif (get_series_indent($this->lines[0]) > 0 && $block_piece->x == $this->x + get_series_indent($this->lines[0])) {
				// Do merge these two blocks:
				//   A)  Some action. Followed
				//       by some more action.
				// But not these:
				//   A)  Some action. Followed
				//   B)  By some more action.
				if ($intervening_blockpiece) return FALSE;
			} else {
				// [guyg] Allow 2 characters either direction
				// pdftotext can't be relied on to get spacing exact :-(
				$chars2 = 2 * $GLOBALS["default_char_width"];
				if ($block_piece->x >= $this->x - $chars2 && $block_piece->x <= $this->x + $chars2) {
					//		(fumbles in a pocket
					//		 under his robe)
					// We'll be exceptionally lenient for now, and tighten it back up later if needed.
				} else {
					return FALSE;
				}
			}
			if (($block_piece->text[0] == '(' || $block_piece->text[0] == '[')) {
				// [ajo] Don't merge parens into characters:
				//        KAT
				//        (hopeful)
				//    These three lines should be three different blocks.
				if ($this->is_uppercase_single_line())
					return FALSE;

				// Don't merge if they look like distinct dialogue vs () lines
				// If the ( either doesn't close or closes at end of line, 
				// it should remain its own block from the previous line
				$parenPos1 = mb_strpos($block_piece->text, ")");
				$parenPos2 = mb_strpos($block_piece->text, "]");
				if (($parenPos1 === FALSE || $parenPos1 === mb_strlen($block_piece->text) - 1) &&
					($parenPos2 === FALSE || $parenPos2 === mb_strlen($block_piece->text) - 1)) {
					return FALSE;
				}
			}
			
			if ($this->isEntirelyParens()) {
				// Don't merge if the curent line starts and ends a ()
				return FALSE;
			}
			// [guyg] Consecutive all-caps lines maybe should be combined, unless the next one is likely its own slugline
			if ($block_piece->x == $this->x && $this->is_uppercase_single_line() && $this->width > 50 &&
				is_uppercase($block_piece->text) && !looks_like_slugline($block_piece->text))
				return TRUE;
		} else if ($block_piece->y == $this->y + $this->height-1) {
			// No longer merge horizontally - that should've already happened in the initial
			// XML parsing if we wanted to merge those
			return FALSE;

			/*
			assert($block_piece->x > $this->x + mb_strlen($this->lines[$this->height-1]));

			// [guyg] This used to be 3, but I changed it to 6 because
			// maybe_merge_across_intersentence_spaces() happens too late
			// and we may have already merged the right-hand block down
			// with a Character below it. e.g.
			//             A
			// Block 1.    Block 2.
			//             B
			// Block 2 was getting merged down with B!
			// I tried 5 like maybe_merge_across_intersentence_spaces(),
			// but that wasn't enough for a random script (TEMPORAL)
			if ($block_piece->x <= $this->x + mb_strlen($this->lines[$this->height-1]) + 6) {
				// Do a horizontal merge of these two blocks, unless one looks
				// like a scene number (e.g. "1A").
				if ($this->height == 1 && looks_like_scene_number($this->lines[0])) return FALSE;
				if (looks_like_scene_number($block_piece->text)) return FALSE;
				return TRUE;
			} elseif ($this->height == 1 && get_series_indent($this->lines[0]) == 999) {
				// Special case to correctly merge this example:
				//    A)      First shot.
				//    B)      Second shot.
				// TODO FIXME BUG HACK!
				return TRUE;
			} else {
				return FALSE;
			}
			*/
		} else {
			return FALSE;
		}
		if (looks_like_slugline_or_transition($this->lines[0])) return FALSE;
		if (looks_like_slugline_or_transition($block_piece->text)) return FALSE;
		// Content constraints: Some lines shouldn't be merged.
		if ($this->is_uppercase_single_line() &&
				preg_match("/^[A-Z].*[a-z]/", $block_piece->text))
			return FALSE;
		if ($this->height == 1 && looks_like_character($this->lines[0]) &&
				looks_like_parenthetical($block_piece->text))
			return FALSE;
		return TRUE;
	}
	function get_text_and_colors() {
		$result = $this->lines[0];
		$result_colors = $this->colors[0];
		$resultTextAttributes = $this->textAttributes[0];
		for ($lx = 1; $lx < count($this->lines); ++$lx) {
			if (preg_match("/[A-Za-z0-9]-$/", $result)) {
				// Assume this is a (BAD STYLE) hyphenation, and don't insert the space.
			} else {
				// Insert only one space, regardless of whether the last line ended
				// with sentence-ending punctuation. Let someone else be in charge of
				// re-spacing the input, if they feel the need. TODO FIXME BUG HACK
				$result .= " ";
			}
			$previous_length = mb_strlen($result);
			foreach(array_keys($this->colors[$lx]) as $key)
			{
				foreach($this->colors[$lx][$key] as $entry)
				{
					$hold["start"] = $entry["start"] + $previous_length;
					$hold["end"] = $entry["end"] + $previous_length;
					$result_colors[$key][] = $hold;

				}
			}
			foreach(array_keys($this->textAttributes[$lx]) as $key2)
			{
				foreach($this->textAttributes[$lx][$key2] as $entry2)
				{
					$hold2["start"] = $entry2["start"] + $previous_length;
					$hold2["end"] = $entry2["end"] + $previous_length;
					$resultTextAttributes[$key2][] = $hold2;
				}
			}

			$result .= $this->lines[$lx];

		}
		$return_array["text"] = $result;
		$return_array["colors"] = $result_colors;
		$return_array["textAttributes"] = $resultTextAttributes;
		return $return_array;
	}
	function get_fontSize()
	{
		return $this->fontSize;
	}
	function is_uppercase_single_line() {
		return $this->height==1 && is_uppercase($this->lines[0]);
	}
	function definitely_not_character() {
		if ($this->height != 1 && strpos($this->get_text_and_colors()["text"], '(') === FALSE && strpos($this->get_text_and_colors()["text"], '[') === FALSE) return TRUE;
		if ($this->lines[0][0] == '-' || $this->lines[0][0] == '.') return TRUE;
		return FALSE;
	}
	function definitely_not_transition() {
		if (!$this->is_uppercase_single_line()) return TRUE;
		return FALSE;
	}
	function is_transition_follower() {
		if ($this->kind == "Slugline") return TRUE;
		if ($this->kind != "Action") return FALSE;
		// Return TRUE if this is an action with the first word capitalized:
		//   NEWSPRINT BLURS past... stops on a page of OBITUARIES.
		$t = strstr($this->get_text_and_colors()["text"], ' ', /*before_needle=*/TRUE);
		return is_uppercase($t);
		
	}
	function classify_based_on_content($prev_kind, $block_num) {

		if ($this->isBlank()) {
			$this->kind = "Text";
			return;
		}


		$probably_dialog = ($prev_kind == "Character" || $prev_kind == "Paren");

		// [guyg] Wide blocks probably aren't Dialog. Non-conclusive.
		// Notably, this wouldn't be right to reject stageplays
		if (false) {
			$probably_dialog = ($probably_dialog && $this->width <= 50 * $GLOBALS["default_char_width"]);
		}
		
		// print_r($this);
		$this->classification_is_unsure = FALSE;
		// [guyg] I moved this here. Hack, but better than before.
		if ($block_num < 3 && count($this->lines) == 1 && contains_date($this->lines[0]) && mb_strlen($this->lines[0]) < 28) {
			$this->kind = "Page Header";
		} else if (looks_like_changebar($this->lines)) {
			$this->kind = "Changebar";
		} else if ($this->height == 1 && looks_like_slugline($this->lines[0])) {
			$this->kind = "Slugline";

			// [guyg] Remove scene numbers from sluglines if they haven't been separated yet!
			$old_line = $this->lines[0];
			$split_slug = explode(" ", $this->lines[0]);
			if (looks_like_scene_number($split_slug[0])) {
				array_shift($split_slug);
				$this->lines[0] = trim(implode(" ", $split_slug));
				
				// Move the x coordinate in as many spaces as we saved.
				$this->x += (mb_strlen($old_line) - mb_strlen($this->lines[0])) * $GLOBALS["default_char_width"];
			}

			// Also, check if we need to remove a scene number from the end.
			// This could be wrong... maybe people write EXT. HOUSE 2
			// Also, in a test there's a reference to "FROME SCENE 22"
			if (false && looks_like_scene_number(end($split_slug))) {
				array_pop($split_slug);
				$this->lines[0] = trim(implode(" ", $split_slug));
			}

		} else if ($this->height == 1 && looks_like_transition($this->lines[0])) {
			$this->kind = "Transition";
		} else if ($this->height == 1 && looks_like_the_end($this->lines[0])) {
			$this->kind = "Action"; // [guyg] Treat The End as a simple action
		} else if ($this->height == 1 && looks_like_hrule($this->lines[0])) {
			$this->kind = "Horizontal Rule";
		} else if (false && $this->height == 1 && looks_like_page_number($this->lines[0])) {
			$this->kind = "Page Number";
		} else if ($this->height > 1 && looks_like_contact_info($this->lines)) {
			$this->kind = "Contact Info";
		} else if (($bx == 0 || $prev_kind == "Text") && $this->height == 1 && looks_like_character($this->lines[0])) {
			if (contains_vo_os_notation($this->lines[0])) {
				$this->kind = "Character";
			} else {
				// If it's far to the left, it's *probably* not a character.
				// [guyg] Characters are not assume to be capitalized. Sluglines are.
				$this->kind = ($this->x < 8 && is_uppercase($this->lines[0])) ? "Slugline" : "Character";
				$this->classification_is_unsure = TRUE;
			}
		} else if (looks_like_parenthetical($this->get_text_and_colors()["text"])) {
			$this->kind = "Paren";
		} else if ($probably_dialog) {
			$this->kind = "Dialog";
			$this->classification_is_unsure = TRUE;
		} else if (preg_match("/[^A-Za-z](I|me|my|you|your|yours)[^A-Za-z]/i", $this->get_text_and_colors()["text"])) {
			$this->kind = "Dialog";
			$this->classification_is_unsure = TRUE;
		} else if (preg_match("/([Ww]e see|enters|faces|looks|smiles|stares|walks|As (she|he|they))/", $this->get_text_and_colors()["text"])) {
			$this->kind = "Action";
			$this->classification_is_unsure = TRUE;
		} else {
			$this->kind = "Unknown";
			$this->classification_is_unsure = TRUE;
		}
	}
	function reclassify_using_indent($maybe_kinds) {
		assert($this->classification_is_unsure == TRUE);
		if ($this->definitely_not_character()) {
			$maybe_kinds = array_values(array_filter($maybe_kinds, function($v) { return $v != "Character"; }));
		}
		if ($this->definitely_not_transition()) {
			$maybe_kinds = array_values(array_filter($maybe_kinds, function($v) { return $v != "Transition"; }));
		}
		if (in_array($this->kind, $maybe_kinds)) {
			// Our earlier guess has now been confirmed.
			$this->classification_is_unsure = FALSE;
			return;
		} else if (count($maybe_kinds) == 1 && $this->kind == "Unknown") {
			// There were no clues earlier, and the indentation leaves only
			// one possibility. Let's go with it.
			$this->kind = $maybe_kinds[0];
			return;
		}
		// We have a cheat for dialog already, in classify_based_on_content().
		// If it follows a character name, it's dialog. If that heuristic
		// triggered on this block, then we would have bailed out early because
		// the "earlier guess has now been confirmed" (above). Therefore, we
		// can assume that whatever this is, it's not dialog.
		if (in_array("Dialog", $maybe_kinds)) {
			// [guyg] Not quite true... if it "looks like a character" then
			// we'd short-circuit the Dialog code there and assume it's a Character
			// or even a Slugline! Can wind up as Action as well, if there
			// was a page break before the last character, and a Page Number
			// object before the Dialog
			if (count($maybe_kinds) == 1) {
				$this->kind = "Dialog";
				return;
			}
			$maybe_kinds = array_values(array_filter($maybe_kinds,
				function ($v) { return ($v != "Dialog"); }));
		}
		if (in_array("Action", $maybe_kinds) || in_array("Slugline", $maybe_kinds)) {

			$kind = "Action";
			if ($blockIndex > 0) {
				$prevBlock = $blocks[$blockIndex - 1];
				if (!$prevBlock->isBlank()) {
					// If this line follows a non-blank line, that means it's tight with it so 
					// use Text to avoid adding any top margin space
					$kind = "Text";
				}
			}

			// Don't consider lines possibly as Slugline anymore if we didn't already think it was a Slugline based on content
			$this->kind = $kind;

			/*
			// Depend on capitalization to distinguish these two types.
			$this->kind = preg_match("/[a-z]/", $this->get_text_and_colors()["text"]) ? "Action" : "Slugline";
			// [guyg] Used to have this condition, but Arthur removed it
			// if ($this->height > 1 && $this->kind == "Action")
			*/
			$this->classification_is_unsure = FALSE;
			return;
		}

		if (count($maybe_kinds) == 1 && ($this->kind == "Unknown" || $this->classification_is_unsure)) {
			// The indentation leaves only one possibility (well, except
			// for Dialog, but we're assuming it's not dialog). Go with it.
			$this->kind = $maybe_kinds[0];
			return;
		}

		// Otherwise, the indentation didn't tell us much.
	}

}


// pdftotext likes to space out blocks horizontally whenever a column
// of spaces happens to line up. E.g., we'll get this:
//	 	Save your   film for Grammie. The guy
//		she saved   last night will want a
//		photo for   his Bible's cover. I bet
//		he thinks   she's its main character.
// We could fix this by allowing three spaces within a block-piece,
// and using four spaces as the separator; but that's bad because
// plenty of reasonable input could use three spaces between blocks
// (page numbers, dual dialog, scene numbers, changebars). So
// instead, we'll look for blocks this might have happened to, and
// merge them horizontally right here.
function maybe_merge_across_gutters(&$blocks)
{
	// Now that we're using XML, hopefully the merging isn't necessary
	return;

	/*
	assert(is_array($blocks));
	foreach ($blocks as $ax => $block) {
		if (!isset($blocks[$ax])) continue;
		if (!isset($blocks[$ax+1])) continue;
		$nextblock = $blocks[$ax+1];
		$block_maxy = $block->y + $block->height;
		$nextblock_maxy = $nextblock->y + $nextblock->height;
		if ($block->height < 2 || $nextblock->height < 2) continue;
		if ($block->y == $nextblock->y-1 && looks_like_character($block->lines[0])) {
			// it's okay, but we'll have to split out that first line
			if ($block->x < $nextblock->x) continue;
			if ($block_maxy != $nextblock_maxy && $block_maxy != $nextblock_maxy-1) continue;
			$split_out_character_name = TRUE;
			$left_hand_block = $nextblock;
			$right_hand_block = $block;
		} else if ($nextblock->y == $block->y) {
			// it's perfectly okay
			assert($block->x < $nextblock->x);
			if ($nextblock_maxy != $block_maxy && $nextblock_maxy != $block_maxy-1) continue;
			$split_out_character_name = FALSE;
			$left_hand_block = $block;
			$right_hand_block = $nextblock;
		} else {
			continue;  // these blocks don't seem mergeable
		}
		// Okay, we have two side-by-side blocks that could have been meant as one.
		// But finally check that the "gutter" isn't ragged.
		$linelength = mb_strlen($left_hand_block->lines[0]);
		$all_lines_same_length = TRUE;
		foreach ($left_hand_block->lines as $i => $line) {
			if ($i == count($right_hand_block->lines)) continue;
			if (mb_strlen($line) != $linelength)
				$all_lines_same_length = FALSE;
		}
		if (!$all_lines_same_length) continue;
		// Okay, let's merge them!
		if ($split_out_character_name) {
			// "block" is the right-hand side of the gutter; "nextblock" is the left-hand side
			foreach ($left_hand_block->lines as $lx => $line) {
				if ($lx+1 < $right_hand_block->height) {
					$left_hand_block->lines[$lx] .= " ";
					$left_hand_block->lines[$lx] .= $right_hand_block->lines[$lx+1];
				}
			}
			$left_hand_block->width += 1 + $right_hand_block->width;
			// "block" now contains only the character name.
			$block->lines = array($block->lines[0]);
			$block->width = mb_strlen($block->lines[0]);
			$block->height = 1;
		} else {
			// "block" is the left-hand side of the gutter; "nextblock" is the right-hand side
			foreach ($left_hand_block->lines as $lx => $line) {
				if ($lx < $right_hand_block->height) {
					$left_hand_block->lines[$lx] .= " ";
					$left_hand_block->lines[$lx] .= $right_hand_block->lines[$lx];
				}
			}
			$left_hand_block->width += 1 + $right_hand_block->width;
			// Delete nextblock from the array.
			unset($blocks[$ax+1]);
		}
	}
	$blocks = array_values($blocks);  // compact the array again

	*/
}

// pdftotext also likes to expand 2-space intersentence spaces into
// 4- or 5-space gaps, for some reason I don't fully understand.
// If we see a one-line block which is 4 or 5 spaces away from the
// right edge of another block, and that right edge consists of
// sentence-ending punctuation, then merge the blocks together.
function maybe_merge_across_intersentence_spaces(&$blocks)
{
	assert(is_array($blocks));
	foreach ($blocks as $ax => $block) {
		if (!isset($blocks[$ax])) continue;
		if (!isset($blocks[$ax+1])) continue;
		$nextblock = $blocks[$ax+1];
		// "nextblock" will be the one-line block.
		if ($nextblock->height != 1) continue;
		if ($nextblock->x <= $block->x) continue;
		$y_offset = $nextblock->y - $block->y;
		assert($y_offset >= 0);
		if ($y_offset >= $block->height) continue;
		$offending_line = $block->lines[$y_offset];
		assert($offending_line !== FALSE);
		$right_edge = $block->x + mb_strlen($offending_line) * $GLOBALS["default_char_width"];
		if ($nextblock->x > $right_edge + 5) continue;  // the gap is too wide
		if (!preg_match("/[.:?!]$/", $offending_line)) continue;
		if (!preg_match("/^[A-Z]/", $nextblock->lines[0])) continue;
		// Okay, let's merge them!
		$offending_line .= "  ";
		$offending_line .= $nextblock->lines[0];
		$block->lines[$y_offset] = $offending_line;
		$new_width = ($nextblock->x + $nextblock->width) - $block->x;
		$block->width = max($block->width, $new_width);
		unset($blocks[$ax+1]);
	}
	$blocks = array_values($blocks);  // compact the array again
}

function get_last_char($str, $length = 1)
{
	return mb_substr($str, -$length);
}
function get_next_to_last_char($str)
{
	return mb_substr($str, -2, 1);
}
function ends_sentence($str)
{
	$last = get_last_char($str);
	if ($last == '"' || $last == "'") {
		$str = mb_substr($str, 0, mb_strlen($str) - 1);
		$last = get_last_char($str);
	}
	
	if ($last == "." && get_next_to_last_char($str) == ".")
		// Don't count ... as the end of a sentence
		return false;
	
	if (in_array($last, $GLOBALS["sentence_ends"])) {
		// Avoid thinking "Mr.", etc. ends a sentence
		if ($last == ".") {
			$titles = array("Mrs.", "Mr.", "Ms.", "Prof.", "Dr.", "Gen.",
							"Rep.", "Sen.", "St.", "Sr.", "Jr.", "Ph.D.",
							"M.D.", "B.A.", "M.A.", "D.D.S.");
			foreach ($titles as $title) {
				if (is_suffix($str, $title, false)) {
					// If we end with these characters, make sure they're
					// not part of a larger word.
					$previous = mb_substr($str, -(mb_strlen($title) + 1), 1);
					// Doesn't handle multibyte, if it shows up there
					if (!ctype_alnum($previous))
						return false;
				}
			}
		}

		return true;
	}
	return false;
}

function continue_action($str1, $str2)
{
	// If we end with ., !, or ? then it's quite possibly over, too...
	// This is a guess and hard to tell
	if (ends_sentence($str1)) {
		return false;
	}

	// If the last characters are "--" assume it's not connected
	// to the next line. Maybe " -" as well, with restrictions?
	$last2 = get_last_char($str1, 2);
	if ($last2 == "--")
		return false;

	// If the line ended early enough that the next word would fit, then
	// that means there's an intentional new line.
	/*
	// Not really... need to subtract a lot from the max_line_length (at least 10) if so, because spacing doesn't work right
	print("$next_first_word - " . ($this->last_line_length + strlen($next_first_word)) . " " . $GLOBALS["max_line_length"] . "\n");
	if (($this->last_line_length + strlen($next_first_word)) < $GLOBALS["max_line_length"])
		return false;

	Okay, I want to try something, or it's silly... maybe 50 characters?
	*/

	$last_line_length = mb_strlen($str1);
	if (($last_line_length + mb_strlen(first_word($str2))) < 50)
		return false;
	return true;
}
function split_attribute($attributes, $left_index_incl, $right_index_excl)
{
	$split_attribute = array();
	foreach($attributes as $ID => $single_attribute)
	{
		foreach($single_attribute as $entry)
		{
			if ($entry["end"] >= $left_index_incl && $entry["start"] < $right_index_excl)
			{
				$new_entry["start"] = max($entry["start"], $left_index_incl) - $left_index_incl;
				$new_entry["end"] = min($entry["end"], $right_index_excl) - $left_index_incl;
				$split_attribute[$ID][] = $new_entry;
			}
		}
	}
	return $split_attribute;
}


// We've deferred finding parentheticals (e.g., "(cheerfully)"
// until after merging adjacent lines of Dialog, so now we must
// go through the string and pull out anything that looks like
// a parenthetical. Fortunately, normal dialog never contains
// parentheses.
// Input: $objects, an array of type Object[], passed by reference.
// Output: None. $objects is modified in place.
function maybe_break_up_last_dialog(&$objects)
{
	assert(is_array($objects));
	if (empty($objects)) return;
	$last_object = end($objects);
	$last_type = $last_object->get_type();
	
	if ($last_type == "Dialog") {
		$last_object = array_pop($objects);
		$text = $last_object->get_content();
		$colors = $last_object->get_colors();
		$textAttributes = $last_object->getAttribute("textAttributes");
		$pos = 0;
		$start_block = 0;
		if (FALSE) {
			while (TRUE) {
				// Find the next (, and then try to find its closing )
				$left_paren = strpos($text, "(", $pos);
				$left_paren_hard = strpos($text, "[", $pos);
				$open_paren = "(";
				$close_paren = ")";
				if ($left_paren === FALSE || ($left_paren_hard !== FALSE && $left_paren_hard < $left_paren)) {
					$open_paren = "[";
					$close_paren = "]";
					$left_paren = $left_paren_hard;
				}
				if ($left_paren === FALSE) break;
				
				// Find the matching ), not counting embedded ()
				$left_count = 0;
				$search_pos = $left_paren + 1;				
				while (true) {
					$next_left_paren = strpos($text, $open_paren, $search_pos);
					$next_right_paren = strpos($text, $close_paren, $search_pos);
					if ($next_right_paren === FALSE) {
						$pos = $left_paren + 1; // Move on to the next position to seek (
						break; // Doesn't get closed
					}
					if ($next_left_paren === FALSE || $next_right_paren < $next_left_paren) {
						// This right paren is the next stop
						if ($left_count == 0) {
							// Found the closing )!
							if ($left_paren > $start_block) {
								// Everything before the ( is dialog
								//$split_content = trim(substr($text, $start_block, $left_paren - $start_block));
								$split_content = substr($text, $start_block, $left_paren - $start_block);
								if ($split_content != "")
								{
									$split_colors = split_attribute($colors, $start_block, $left_paren);
									$split_textAttributes = split_attribute($textAttributes, $start_block, $left_paren);
									$objects[] = new ScriptObject("Dialog", $split_content, $last_object->get_page_num(), $last_object->get_given_page_num(), $split_colors, $last_object->getFontSize(), $split_textAttributes, $last_object->getNumberObject());
								}
							}
							// Everything within the () is parentheses
							//$split_content = substr($text, $left_paren, $next_right_paren - $left_paren + 1);
							$split_content = substr($text, $left_paren, $next_right_paren - $left_paren + 1);
							if ($split_content != "")
							{
								$split_colors = split_attribute($colors, $left_paren, $next_right_paren + 1);
								$split_textAttributes = split_attribute($textAttributes, $left_paren, $next_right_paren + 1);
								$objects[] = new ScriptObject("Paren", $split_content, $last_object->get_page_num(), $last_object->get_given_page_num(), $split_colors, $last_object->getFontSize(), $split_textAttributes, $last_object->getNumberObject());
							}
							
							$pos = $next_right_paren + 1; // Ignore inner ()
							$start_block = $pos; // Start next block after these ()
							break; // Move on to the next ( after this
						} else {
							$search_pos = $next_right_paren + 1;
							$left_count--;
						}
					} else {
						$search_pos = $next_left_paren + 1;
						$left_count++;
					}
				}
			}
		}
		//$remaining_content = trim(substr($text, $start_block));
		$remaining_content = substr($text, $start_block);
		if ($remaining_content != "") {
			$split_colors = split_attribute($colors, $start_block, mb_strlen($text));
			$split_textAttributes = split_attribute($textAttributes, $start_block, mb_strlen($text));
			$objects[] = new ScriptObject("Dialog", $remaining_content, $last_object->get_page_num(), $last_object->get_given_page_num(), $split_colors, $last_object->getFontSize(), $split_textAttributes, $last_object->getNumberObject());
		}

	}
}



function prevgen_compute_indents($blocks)
{
	$likely_indent_for = array();
	$common_kinds = array("Character", "Dialog", "Slugline", "Action");
	foreach ($common_kinds as $v) {
		$L = get_likely_indent_for($blocks, $v);
		if ($L !== FALSE) {
			assert($L >= 0);
			$likely_indent_for[$v] = $L;
		}
	}
	// [guyg] If we didn't think we found any Action/Slugline,
	// and Dialog doesn't appear to be at 0, let Action/Slugline try at 0
	if (!isset($likely_indent_for["Action"]) &&
		!isset($likely_indent_for["Slugline"]) &&
		isset($likely_indent_for["Dialog"]) &&
		$likely_indent_for["Dialog"] > 0)
		$likely_indent_for["Action"] = 0;
		
	if (!isset($likely_indent_for["Action"]) && isset($likely_indent_for["Slugline"]))
		$likely_indent_for["Action"] = $likely_indent_for["Slugline"];
	if (!isset($likely_indent_for["Slugline"]) && isset($likely_indent_for["Action"]))
		$likely_indent_for["Slugline"] = $likely_indent_for["Action"];
	return $likely_indent_for;
}

function nextgen_compute_indents($blocks)
{
	// How many indentation levels do we have on this page?
	// Ideally, we'll have only four distinct levels:
	//   SLUGLINE
	//   Action action action.
	//            CHARACTER
	//        Dialog dialog dialog.
	//            (paren)
	//        Dialog dialog dialog.
	//                     TRANSITION:
	//
	// But in practice, Paren may be its own level, and
	// Transitions may come at lots of different levels.
	// Finally, we might have weirdly-indented Text
	// inside an Action (e.g. sign text).

	$blocks_with_this_indent = array();
	foreach ($blocks as $block) {
		$interesting_kinds = array("Character", "Paren", "Dialog", "Action", "Slugline", "Transition", "Text", "Unknown");
		if (!in_array($block->kind, $interesting_kinds)) continue;
		if (looks_like_scene_number($block->get_text_and_colors()["text"])) continue;
		if (!isset($blocks_with_this_indent[$block->x]))
			$blocks_with_this_indent[$block->x] = array();
		$blocks_with_this_indent[$block->x][] = $block;
	}
	// Ideally, one of these levels will be associated only with
	// Character and Paren blocks; the previous level will all make
	// sense as Dialog; and the level before that will make sense
	// as Sluglines and Actions.

	$likely_indent_for = array();
	
	$lkeys = array_keys($blocks_with_this_indent);
	sort($lkeys);
	if (count($lkeys) == 2) {
		// (0 == Slugline/Action, 1 = Transition)
		// (0 == Dialog, 1 = Character/Paren)
		$s0 = array_sum(array_map(function ($b) { return ($b->kind == "Slugline" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		$a0 = array_sum(array_map(function ($b) { return ($b->kind == "Action" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		$d0 = array_sum(array_map(function ($b) { return ($b->kind == "Dialog" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		$t1 = array_sum(array_map(function ($b) { return ($b->kind == "Transition" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		$c1 = array_sum(array_map(function ($b) { return ($b->kind == "Character" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		$p1 = array_sum(array_map(function ($b) { return ($b->kind == "Paren" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		if ($c1+$p1 == count($blocks_with_this_indent[$lkeys[1]])) {
			$likely_indent_for["Dialog"] = $lkeys[0];
			$likely_indent_for["Character"] = $lkeys[1];
			$likely_indent_for["Paren"] = $lkeys[1];
		} elseif ($s0 || $a0 || $t1) {
			$likely_indent_for["Slugline"] = $lkeys[0];
			$likely_indent_for["Action"] = $lkeys[0];
			$likely_indent_for["Transition"] = $lkeys[1];
		} else {
			return FALSE;
		}
	} elseif (count($lkeys) == 3) {
		// (0 = Slugline/Action, 1 = Dialog/Paren, 2 = Character)
		// (0 = Slugline/Action, 1 = Dialog, 2 = Character/Paren)
		// (0 = Dialog, 1 = Paren, 2 = Character)
		$s0 = array_sum(array_map(function ($b) { return ($b->kind == "Slugline" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[0]]));
		$c1 = array_sum(array_map(function ($b) { return ($b->kind == "Character" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[1]]));
		$c2 = array_sum(array_map(function ($b) { return ($b->kind == "Character" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[2]]));
		$p1 = array_sum(array_map(function ($b) { return ($b->kind == "Paren" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[1]]));
		$p2 = array_sum(array_map(function ($b) { return ($b->kind == "Paren" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[2]]));
		if ($c2 == 0 || $c1 > $c2) return FALSE;
		if ($p1 == count($blocks_with_this_indent[$lkeys[1]])) {
			$likely_indent_for["Dialog"] = $lkeys[0];
			$likely_indent_for["Paren"] = $lkeys[1];
			$likely_indent_for["Character"] = $lkeys[2];
		} else {
			$likely_indent_for["Slugline"] = $lkeys[0];
			$likely_indent_for["Action"] = $lkeys[0];
			$likely_indent_for["Dialog"] = $lkeys[1];
			$likely_indent_for["Character"] = $lkeys[2];
			$likely_indent_for["Paren"] = ($p1 >= $p2) ? $lkeys[1] : $lkeys[2];
		}
	} elseif (count($lkeys) == 4) {
		// (0 = Slugline/Action, 1 = Dialog/Paren, 2 = Character/Paren, 3 = Transition)
		// (0 = Slugline/Action, 1 = Dialog, 2 = Paren, 3 = Character)
		// (0 = Dialog, 1 = Paren, 2 = Paren, 3 = Character)
		$p1 = array_sum(array_map(function ($b) { return ($b->kind == "Paren" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[1]]));
		$p2 = array_sum(array_map(function ($b) { return ($b->kind == "Paren" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[2]]));
		$c2 = array_sum(array_map(function ($b) { return ($b->kind == "Character" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[2]]));
		$c3 = array_sum(array_map(function ($b) { return ($b->kind == "Character" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[3]]));
		$t3 = array_sum(array_map(function ($b) { return ($b->kind == "Transition" ? 1 : 0); }, $blocks_with_this_indent[$lkeys[3]]));
		if ($c3 != 0 && $p2 == count($blocks_with_this_indent[$lkeys[2]])) {
			if ($p1 == count($blocks_with_this_indent[$lkeys[1]])) {
				$likely_indent_for["Dialog"] = $lkeys[0];
				$likely_indent_for["Paren"] = ($p1 >= $p2) ? $lkeys[1] : $lkeys[2];
				$likely_indent_for["Character"] = $lkeys[3];
			} else {
				$likely_indent_for["Slugline"] = $lkeys[0];
				$likely_indent_for["Action"] = $lkeys[0];
				$likely_indent_for["Dialog"] = $lkeys[1];
				$likely_indent_for["Paren"] = $lkeys[2];
				$likely_indent_for["Character"] = $lkeys[3];
			}
		} elseif ($c2 && $t3) {
			$likely_indent_for["Slugline"] = $lkeys[0];
			$likely_indent_for["Action"] = $lkeys[0];
			$likely_indent_for["Dialog"] = $lkeys[1];
			$likely_indent_for["Paren"] = ($p1 >= $p2) ? $lkeys[1] : $lkeys[2];
			$likely_indent_for["Character"] = $lkeys[2];
			$likely_indent_for["Transition"] = $lkeys[3];
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
	return $likely_indent_for;
}