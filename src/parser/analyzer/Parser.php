<?php

$CONVERT_TO_CELTX = FALSE;
$CONVERT_TO_FOUNTAIN = FALSE;
$DUMP_BLOCKS = FALSE;

$PARSE_TEXT_FILE = false;

function looks_like_fountain($file)
{
    $lines = file($file);
    $indented = 0;
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed != "" && $trimmed != $line)
            $indented++;
    }
    return ($indented < count($lines)/10);
}

class Parser {
	private $file;
	
	// This is the end result of the parsing
	private $objects;
	private $num_pages;
	private $is_stage_play;
	private $headerObjects;
	private $footerObjects;
	private $colors;
	
	function __construct($file)
	{
		$this->file = $file;
		$this->num_pages = 0;
		$this->is_stage_play = false;
		$this->headerObjects = [];
		$this->footerObjects = [];
		$this->$colors = new Colors_List(array());
	}

	function get_objects() { return $this->objects; }
	function get_num_pages() { return $this->num_pages; }
	function get_is_stage_play() { return $this->is_stage_play; }
	
	function print_structure()
	{
		foreach ($this->objects as $num => $object) {
			print($object->get_type() . "\t" . $object->get_content() . "\n");
		}
	}

	function parse()
	{
        $GLOBALS["PARSE_TEXT_FILE"] = false;
        
		// Edit this for debugging purposes
		$leave_behind_files = ($GLOBALS["debug_level"] > 0);
	
		// Make a copy of the file [why is this needed? --ajo]
		$original_file = clean_share_path(tempnam($GLOBALS["shared_files_dir"], "copy"));
		convenient_assumption($original_file !== FALSE);
		copy($this->file, $original_file);

		$pageSize = "Letter";
		$ext = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
		if ($ext == "pdf") {
    			$xml_file = clean_share_path(tempnam($GLOBALS["shared_files_dir"], "copy"));
    			$pdftohtml_program = get_config("pdftohtml");
				`$pdftohtml_program -xml -zoom 1 $original_file $xml_file`;
				$return_array = parse_xml_file($xml_file . ".xml", $this->num_pages, $this->is_stage_play, $pageSize);
    			$this->objects = $return_array[0];
				$this->headerObjects = $return_array[1];
				$this->footerObjects = $return_array[2];
				$this->colors = $return_array[3];
    			if (!$leave_behind_files) {
    				unlink($xml_file);
    				unlink($xml_file . ".xml");
				}
		} else if ($ext == "celtx") {
			$this->objects = parse_celtx_file($original_file, $this->num_pages, $this->is_stage_play);
		} else if ($ext == "fdx") {
			$this->objects = parse_fdx_file($original_file, $this->num_pages, $this->is_stage_play);
		} else if ($ext == "fountain" || $ext == "spmd" || $ext == "txt") {
			$this->objects = parse_fountain_file($original_file, $this->num_pages, $this->is_stage_play);
		} else if ($ext == "shakespeare") {
			$this->objects = parse_shakespeare_file($original_file, $this->num_pages, $this->is_stage_play);
		}
		if (!$leave_behind_files)
			unlink($original_file);
			
		// Fixup title page, because we don't support bold, italic, etc., in title pages.
		// Also, remove generic titles and authors from Final Draft
		foreach ($this->objects as &$object) {
		    $type = $object->get_type();
		    if ($type == "Title" || $type == "Author") {
		        $find = "";
                for ($i = 1; $i <= 4*2; $i++)
                    $find .= chr($i);
                $content = preg_replace("/[$find]/", "", $object->get_content());
                if ($type == "Title" && strcasecmp($content, "Script Title") == 0)
                    $content = "";
                if ($type == "Author" && strcasecmp($content, "Name of First Writer") == 0)
                    $content = "";
		        $object->set_content($content);
	        }
		}

		//********* For checking the results
		global $CONVERT_TO_CELTX, $CONVERT_TO_FOUNTAIN, $CONVERT_TO_JSON, $OUTPUT_FILE_NAME;
		if ($CONVERT_TO_FOUNTAIN) {
			write_fountain_file($this->objects, $OUTPUT_FILE_NAME ?: "output.fountain");
		}

        if ($CONVERT_TO_JSON) {
			$revisions = parse_revisions($this->objects, $this->colors);
			$lineNumbers = parse_lineNumbers($this->objects);
            write_json_file($this->objects, $OUTPUT_FILE_NAME ?: "output.json", $this->headerObjects, $this->footerObjects, $revisions, $lineNumbers, $pageSize);
        }


		if ($GLOBALS["debug_level"] > 0) {
			$this->print_structure();
		}
	}
}

// [ajo] This function really doesn't belong here, but I don't really
// know where to put it. It's apparently used by the backend somehow.
// We might need similar functions num_text_pages, num_html_pages,
// num_celtx_pages, and num_fdx_pages. Or ideally I think we'd just
// implement num_pages(Object[]) and forget about how many pages the
// original input file had.
function num_pdf_pages($pdf)
{
	$text_file = clean_share_path(tempnam($GLOBALS["shared_files_dir"], "copy"));
	convenient_assumption($text_file !== FALSE);
	`pdftotext -layout $pdf $text_file`;
	$result = count_formfeeds($text_file);
	unlink($text_file);
	return $result;
}

// Input: The name of a text file.
// Output: The number of '\f' (ASCII formfeed) characters contained in that file.
function count_formfeeds($text_file)
{
	$lines = file($text_file);
	if ($lines === FALSE) {
		convenient_assumption(FALSE);
		return 1;
	}
	assert(is_array($lines));
	$count = 1;
	foreach ($lines as $line) {
		$count += substr_count($line, "\f");
	}
	return $count;
}
function handle_ID_found(&$colors_on_page, $ID_array, $page_num)
{
	foreach($ID_array as $ID)
	{
		if (isset($colors_on_page[$page_num][$ID]))
		{
			$colors_on_page[$page_num][$ID]++;
		}
		else
		{
			$colors_on_page[$page_num][$ID] = 1;
		}
	}
}
function has_color_been_found(&$handled_colors, $color_ID)
{
	if (isset($handled_colors[$color_ID]))
	{
		$handled_colors[$color_ID]++;
		return 1;
	}
	else
	{
		$handled_colors[$color_ID] = 1;
		return 0;
	}
}
function update_mystery_revision(&$revisions, $key, $color_ID, $colorHex)
{
	$revisions[$key]["COLOR"] = $colorHex;
	$revisions[$key]["COLORS_LIST_ID"] = $color_ID;
}

function parse_lineNumbers(&$objects)
{
	$lineTypeCountObj = array();
	foreach ($objects as $obj)
	{
		$type = $obj->get_type_json();
		if (!isset($lineTypeCountObj[$type]))
		{
			$lineTypeCountObj[$type] = array("count" => 0, "left" => 0, "right" => 0);
		}
		$lineTypeCountObj[$type]["count"]++;
		if ($obj->getAttribute("numberObject")["left"])
		{
			$lineTypeCountObj[$type]["left"]++;
			$obj->deleteNumberObjectLeft();
		}
		if ($obj->getAttribute("numberObject")["right"])
		{
			$lineTypeCountObj[$type]["right"]++;
			$obj->deleteNumberObjectRight();
		}
	}
	$return_array = array();
	foreach($lineTypeCountObj as $lineType => $finalCount)
	{
		$flag = 0;
		$leftRightArray = array();
		$halfCount = floor($finalCount["count"] / 2);
		if ($halfCount < $finalCount["left"])
		{
			$flag = 1;
			$leftRightArray["left"] = 1;
		}
		if ($halfCount < $finalCount["right"])
		{
			$flag = 1;
			$leftRightArray["right"] = 1;
		}
		if ($flag)
		{
			$return_array[$lineType] = $leftRightArray;
		}
	}
	return $return_array;
}

function parse_revisions(&$objects, &$colors_passed)
{
    $last_page_num = -1;
    $colors_on_page = array();
    $page_num = 0;
	$revisions = array();
	$objs_on_page = array();
	$first_key_flag = 0;
	$previous_key = 0;
	//figure out which objects are on each page. Can't trust the existing pageNum in the object,
	//it starts at 1 or 2 depending on the existance of a title page.
    foreach (array_keys($objects) as $key) {
        if (!$first_key_flag)
        {
			$objs_on_page[$page_num]['first'] = $key;
			$objs_on_page[$page_num]['last'] = end(array_keys($objects));
			$colors_on_page[$page_num] = array();
            $last_page_num = $objects[$key]->get_page_num();
            handle_ID_found($colors_on_page, $objects[$key]->get_color_IDs(), $page_num);
			$first_key_flag = 1;
        }
        else if ($objects[$key]->get_page_num() != $last_page_num)
        {  
			$objs_on_page[$page_num]['last'] = $previous_key;
        	$page_num++;
			$objs_on_page[$page_num]['first'] = $key;
			$last_page_num = $objects[$key]->get_page_num();
			$colors_on_page[$page_num] = array();
        	handle_ID_found($colors_on_page, $objects[$key]->get_color_IDs(), $page_num);
        }
        else
        {
            handle_ID_found($colors_on_page, $objects[$key]->get_color_IDs(), $page_num);
        }
		$previous_key = $key;
    }
	$objs_on_page[$page_num]['last'] = end(array_keys($objects));


	$key_color_name_pairs_by_page = $colors_passed->get_key_pairs();
	$revisions_on_page = array();
	//the index of this list is the weight, the value is the revision ID. 0 is the heaviest and will
	//become the most newly created revision when it gets to WD.
	$weights = new SplDoublyLinkedList;

	//first, go handle the pages that only have one color. I refer to these as "key pages" (and subsequently, key colors, key names, key pairs, key pages)
	//because they're the revisions we're most sure of.
	foreach(array_keys($key_color_name_pairs_by_page) as $p_num)
	{
		$attributes = $key_color_name_pairs_by_page[$p_num]['attributes'];
		$found = 0;
		foreach(array_keys($revisions) as $r_key)
		{
			//if the color and name from a page with only one color (and name) match an existing revision
			if ($attributes["COLOR"] == $revisions[$r_key]["COLOR"] && $attributes["NAME"] == $revisions[$r_key]["NAME"]){
				$found = 1;
			}

		}
		//oh we don't have a revision for this key pair, make one
		if (!$found)
		{
			$revisions[] = array("NAME" => $attributes['NAME'], "COLOR" => $attributes["COLOR"], "COLORS_LIST_ID" => $attributes["COLORS_LIST_ID"], "REV_ID" => count($revisions));
			$most_recently_created = end($revisions)["REV_ID"];
			$weights->push(array("revision" => $most_recently_created, "isKey" => 1, "pageFoundOn" => $p_num));
			$revisions_on_page[$p_num][] = $revisions[$most_recently_created];
		}
	}
	//might be useful to keep track of what revisions are more trustworthy in the future to improve the algoritm
	//$key_revisions = $revisions;

	//Time for a second pass, making our best guesses based off our key colors.
	foreach($colors_on_page as $p_num => $colors)
	{
		unset($highest_weight_on_page);
		unset($second_highest_weight_on_page);
		$found_existing_rev = 0;
		$handled_colors = array();

		//first see if we can match the header color
		foreach($colors as $color_ID => $num_occurances)
		{
			$header_name = $colors_passed->get_header_name_by_page_num($p_num);
			$weights->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
			for ($weights->rewind(); $weights->valid(); $weights->next()) {
				$current_revision = $revisions[$weights->current()["revision"]];
				if ($current_revision["NAME"] == $header_name && $current_revision["COLORS_LIST_ID"] == $color_ID)
				{	
					//we have a color and name match to a key color, remember the weight
					$highest_weight_on_page = $weights->key();
					if ($weights->current()["pageFoundOn"] != $p_num)
					{
						$revisions_on_page[$p_num][] = $current_revision;
					}
					$found_existing_rev = 1;
					has_color_been_found($handled_colors, $current_revision["COLORS_LIST_ID"]);

					//we can stop looking now
					break 2;
				}
			}

		}
		//okay, so we don't already have a revision with this name. Let's make one.
		if (!$found_existing_rev && count($colors) > 0)
		{
			$revisions[] = array("NAME" => $header_name, "COLOR" => "UNKNOWN", "COLORS_LIST_ID" => -1, "REV_ID" => count($revisions));
			$most_recently_created = end($revisions)["REV_ID"];
			$weights->push(array("revision" => $most_recently_created, "isKey" => 0, "pageFoundOn" => $p_num));
			$revisions_on_page[$p_num][] = $revisions[$most_recently_created];
			$highest_weight_on_page = count($weights) - 1;
			$mystery_revision_key = $most_recently_created;
		}
		//cool we matched it, now handle the other colors we have revisions for on the page in order of weight
		foreach($colors as $color_ID => $num_occurances)
		{
			for ($weights->rewind(); $weights->valid(); $weights->next()) 
			{
				$current_revision = $revisions[$weights->current()["revision"]];
				if ($current_revision["COLORS_LIST_ID"] == $color_ID)
				{
					
					if (!has_color_been_found($handled_colors, $color_ID))
					{	
						$revisions_on_page[$p_num][] = $current_revision;
						if (!isset($second_highest_weight_on_page))
						{
							$second_highest_weight_on_page = $weights->key();
						}
						else if ($weights->key() < $second_highest_weight_on_page)
						{
							$second_highest_weight_on_page = $weights->key();
						}
					}
				}
			}
		}
		//update the weight of the header's revision if we have something to compare it to
		if (isset($highest_weight_on_page) && isset($second_highest_weight_on_page))
		{	
			$holdRevision = $weights[$highest_weight_on_page];
			$weights->offsetUnset($highest_weight_on_page);
			$weights->add($second_highest_weight_on_page, $holdRevision);
		}
		//now handle the colors we don't know anything about. In this pass it's just going to create a new revision for each color and assign it the name UNKNOWN.
		//If we learn the color's name later, update it.
		foreach($colors as $color_ID => $num_occurances)
		{
			if (!has_color_been_found($handled_colors, $color_ID))
			{
				$colorHex = $colors_passed->get_color_hex_code($color_ID);
				if (isset($mystery_revision_key))
				{
					update_mystery_revision($revisions, $mystery_revision_key, $color_ID, $colorHex);
					unset($mystery_revision_key);

				}
				else
				{
					$revisions[] = array("NAME" => "UNKNOWN", "COLOR" => $colorHex, "COLORS_LIST_ID" => $color_ID, "REV_ID" => count($revisions));
					$most_recently_created = end($revisions)["REV_ID"];
					$weights->push(array("revision" => $most_recently_created, "isKey" => 0, "pageFoundOn" => $p_num));
					$revisions_on_page[$p_num][] = $revisions[$most_recently_created];
				}
			}
		}
	}

	//transfer weights to $revisions object and reverse the order of the weights. We reverse it because the heaviest needs to be created last
	//so when we loop over the object in WD and start at index 0 we want that to be the lowest weight.
	$revisions_by_weight = array();
	for ($weights->rewind(); $weights->valid(); $weights->next())
	{
		$current = $weights->current();
		$reversed_weight = count($revisions) - $weights->key() - 1;

		$revisions[$current['revision']]['WEIGHT'] = $reversed_weight;
		$revisions_by_weight[$reversed_weight] = $revisions[$current['revision']];
	}

	//now we start merging all the data we have.
	//pageNumTable converts scriptObject index to what internally consistant page number it's on.
	$pageNumTable = array();
	foreach ($objs_on_page as $i => $p_num)
	{
		for ($j = $p_num['first']; $j <= $p_num['last']; $j++)
		{
			$pageNumTable[$j] = $i;
		}
	}

	//pageToColorIDToRevision converts page number and color to what revision that color means. Right now colors
	//are treated as being unique but in reality you can have more than one name for a color.
	//The only real way to know there's more than one name for a color is with a weight discrepency.
	//CHANGE 3/8/2022: Now we assign it to the revision's weight as that will be the order of the revisions we send over to WD.
	//We have to cross reference $revisions and the entry from $revisions_on_page to get the weight because
	//weight isn't known until the very end.
	$pageToColorIDToRevision = array();
	foreach ($revisions_on_page as $p_num => $list_of_revisions)
	{
		foreach($list_of_revisions as $rev)
		{
			//revisions_on_page doesn't actually get its COLORS_LIST_ID value updated when we figure it out
			//because that array is unsorted. The REV_ID on revisions_on_page is always correct though, that never has a placeholder.
			$updated_COLORS_LIST_ID = $revisions[$rev['REV_ID']]['COLORS_LIST_ID'];
			$pageToColorIDToRevision[$p_num][$updated_COLORS_LIST_ID] = $revisions[$rev['REV_ID']]['WEIGHT'];
		}
	}

	//now for each scriptObject, convert the positional data sorted by colorID to one sorted by revisionID.
	//This is why we needed to keep track of what color means what on every page. Once again this doesn't make much sense
	//because colors are treated as unique right now but in the future we can handle multiple names for one color.
	foreach($objects as $o_num => $o)
	{
		foreach($o->colors as $color_ID => $color)
		{
			foreach($color as $instance)
			{
				if ($instance["start"] == $instance["end"])
					continue;
	
				$objPageNum = $pageNumTable[$o_num];
				$weight_num = $pageToColorIDToRevision[$objPageNum][$color_ID];
				$o->addRevision($weight_num, $instance);
			}
		}
	}

	//trim some details we don't need anymore and rename to 

	foreach($revisions_by_weight as &$revs)
	{
		unset($revs['COLORS_LIST_ID']);
		unset($revs['WEIGHT']);
		unset($revs['REV_ID']);
		$revs['name'] = $revs['NAME'];
		$revs['color'] = $revs['COLOR'];
		unset($revs['NAME']);
		unset($revs['COLOR']);
		if ($revs['name'] == "")
		{
			$revs['name'] = "UNKNOWN";
		}

	}
	ksort($revisions_by_weight);
	return $revisions_by_weight;
}
//now we have revisions, what colors mean which revision on each page, and what lineobjects are on what page
