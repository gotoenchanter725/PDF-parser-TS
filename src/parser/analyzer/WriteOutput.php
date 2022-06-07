<?php
//define("INCH", 72);
function matched_as_slug($content)
{
	static $slugline_beginnings = array("INT", "EXT", "EST", "INT/EXT", "INT./EXT", "I/E");
	foreach ($slugline_beginnings as $prefix)
		if (is_prefix($content, "$prefix ", false) || is_prefix($content, "$prefix.", false))
			return true;
	return false;
}

function matched_as_transition($content)
{
	return is_suffix($content, " TO:");
}

function write_fountain_file($objects, $filename, $for_fdx = false)
{
	$body_stuff = "";
	
	$project_title = "";
	$project_author = "";
	foreach ($objects as $o) {
		if ($o->get_page_num() > 1)
			break;

		// Take just the first title/author
		if ($project_title == "" && $o->get_type() == "Title")
			$project_title = $o->get_content();
		elseif ($project_author == "" && $o->get_type() == "Author")
			$project_author = $o->get_content();
	}
	if (trim($project_title) != "")
		$body_stuff .= "Title: $project_title\n";
	if (trim($project_author) != "") {
		$body_stuff .= "Credit: By\n"; // TODO: Allow editing this, and replace with what the user says
		$body_stuff .= "Author: $project_author\n";
	}

	if ($body_stuff != "")
		$body_stuff .= "\n\n";
		
	static $format_arr = array("**", "*", "_", ""); // No strike-through in Fountain...
	foreach ($objects as $num => $o) {
		// Skip title page. Could try to add other pieces...
		if ($o->get_page_num() == 1)
			continue;
		if ($o->get_type() == "Page Header")
			continue;
		
		$type = $o->get_type();
		$content = $o->get_content();
		
        $content = str_replace("\\", "\\\\", $content);
        $content = str_replace("*", "\*", $content);
        $content = str_replace("_", "\_", $content);

        // Be careful with spaces - these have to be tight on actual text.
        do {
            $old_content = $content;
    	    foreach ($format_arr as $num => $style) {
    	        $left = chr($num*2 + 1);
    	        $right = chr($num*2 + 2);
	        
	            $content = str_replace("$left ", " $left", $content);
	            $content = str_replace(" $right", "$right ", $content);

                // Fix close-open and open-close bugs
    	        $content = str_replace("$left$right", "", $content);
    	        $content = str_replace("$right$left", "", $content);
	        
            }
        } while($content != $old_content);

 	    foreach ($format_arr as $num => $style) {
	        $left = chr($num*2 + 1);
	        $right = chr($num*2 + 2);

	        $content = str_replace($left, $style, $content);
	        $content = str_replace($right, $style, $content);
        }         
		
		$upper_content = mb_strtoupper($content, "UTF-8");
		
		switch ($type) {
			case "Text":
    		    if ($for_fdx) {
    		        // Special | symbol means Shot in my hacked Screenplain
    				$body_stuff .= "\n|$content";
    				break;
    		    }
    		    // Fall through, Fountain doesn't have a Text object...
			case "Action":
				if (matched_as_transition($content))
					$content .= " ";
				if (matched_as_slug($content))
					$content = " $content";
				$body_stuff .= "\n$content";
				break;
			case "Character":
				$body_stuff .= "\n" . ($for_fdx ? trim($upper_content) : $upper_content);
				if ($o->get_is_dual_line())
					$body_stuff .= "^";
				break;
			case "Dialog":
			case "Paren":
				if (trim($content) == "")
					$content = "  "; // Otherwise Fountain will think it's a separating line
				$body_stuff .= $content;
				break;
			case "Shot":
			    if ($for_fdx) {
			        // Special & symbol means Shot in my hacked Screenplain
    				$body_stuff .= "\n&$upper_content";
    				break;
			    }
			    // Fall through, Fountain doesn't have a Shot object...
			case "Act":
    		    if ($for_fdx) {
    		        // Special @ symbol means Act in my hacked Screenplain
    				$body_stuff .= "\n@$upper_content";
    				break;
    		    }
    		    // Fall through, Fountain doesn't have an Act object...
			case "Slugline":
				if ((matched_as_transition($upper_content) || !matched_as_slug($upper_content)) && trim($upper_content) != "")
					$upper_content = ".$upper_content";
				$body_stuff .= "\n$upper_content";
				break;
			case "Transition":
				if (matched_as_slug($upper_content) || !matched_as_transition($upper_content))
					$upper_content = ">$upper_content";
				$body_stuff .= "\n$upper_content";
				break;
			default:
				write_log("Fountain writing ERROR: unknown object type: $type");
				$body_stuff .= $content;
				break;
		}
		$body_stuff .= "\n";
	}

    try {
        file_put_contents($filename, $body_stuff);
    } catch(Exception $err){
        echo 'Caught exception: ',  $err->getMessage(), "\n";
    }
}

function write_json_file($objects, $filename, $headerObjects, $footerObjects, $revisions, $lineNumbers, $pageSize) {
    $project_title = "";
    $project_author = "";

    $dataForJSON = array(
        "lines" => array(),
        "titlePage" => array(
            "lines" => array(),
            "pageSettings" => array(
                "pageSize" => $pageSize
            )
        ),
        "pageSettings" => array(
            "pageSize" => $pageSize
        )
    );

    if ($GLOBALS["sceneContinuedNumber"])
    {
        $dataForJSON["pageSettings"]["sceneContinuedNumber"] = true;
    }
    if ($GLOBALS["sceneContinuedTop"])
    {
        $dataForJSON["pageSettings"]["sceneContinuedTop"] = true;
    }
    if ($GLOBALS["sceneContinuedBottom"])
    {
        $dataForJSON["pageSettings"]["sceneContinuedBottom"] = true;
    }


    $dataForJSON["revisions"] = $revisions;
    $dataForJSON["lineNumbers"] = $lineNumbers;
    // function sort_by_left($a, $b)
    // {
    //     return $a['attributes']['LEFT'] - $b['attributes']['LEFT'];
    // }
    // function calculate_alignment($left)
    // {
    //     if ($left < 100)
    //     {
    //         return "left";
    //     }
    //     else if ($left < 320)
    //     {
    //         return "center";
    //     }
    //     else
    //     {
    //         return "right";
    //     }
    // }
    function grab_header_or_footer($working_header)
    {
        //nico note, might not need to do since we do it earlier now, gonna leave for now
        usort($working_header, 'sort_by_left');
        $return_obj = [];
        $return_obj['align'] = getAlignment($working_header[0]['attributes']['LEFT'], $working_header[0]['attributes']['WIDTH'], 1.5*INCH, 8.5*INCH);
        $how_many_header_chunks = count($working_header);
        $last_left = 0;
        $last_width = 0;
        $header_string = "";
        $num_spaces = 0;
        for ($x = 0; $x < $how_many_header_chunks; $x++)
        {
            if ($x != 0)
            {
                $num_spaces = intdiv((($working_header[$x]['attributes']['LEFT'] - $last_left) - $last_width), $GLOBALS["default_char_width"]);
            }
            $last_left = $working_header[$x]['attributes']['LEFT'];
            $last_width = $working_header[$x]['attributes']['WIDTH'];
            for ($y = 0; $y < $num_spaces; $y++)
            {
                $header_string .= " ";
            }
            $header_string .= $working_header[$x]['value'];
        }
        //print_r($working_header);
        //print_r($header_string);
        //$header_string = str_replace(' ', '&nbsp;', $header_string);
        $return_obj['text'] = $header_string;
        if ($return_obj['text'] == "")
        {
            return false;
        }
        else
        {
            return $return_obj;
        }
    }
    function write_hf_data_if_it_exists($value, &$dataForJSON, $isTitlePage, $location)
    {
        if ($value)
        {
            if ($isTitlePage)
            {
                $dataForJSON['titlePage']['pageSettings']['headersAndFooters'][$location] = $value;
            }
            else
            {
                $dataForJSON['pageSettings']['headersAndFooters'][$location] = $value;
            }
        }
    }
    $last_page_num = -1;
    $is_there_title_page = 0;
    $currentColumn = 0;
    foreach ($objects as $o) {
        //if it's the first line on a page, send the pageNum
        if ($o->get_page_num() != $last_page_num)
        {   
            //if we don't have a given page num, use the generated page number
            $item = array(
                "type" => $o->get_type_json(),
                "text" => $o->get_content(),
                "givenPageNum" => $o->get_given_page_num()
            );
            $last_page_num = $o->get_page_num();
        }
        else
        {
            $item = array(
                "type" => $o->get_type_json(),
                "text" => $o->get_content()
            );
        }
        $attributes = $o->getAllAttributesForJSON();
        if (count($attributes) > 0)
        {
            $item['attributes'] = $attributes;
        }

        switch ($o->get_type()) {
            case "Dialog":
            case "Paren":
                break;
            case "Character":
                $currentColumn = 0;
                if ($o->get_has_dual_line()) {
                    $currentColumn = 1;
                }
                if ($o->get_is_dual_line()) {
                    $currentColumn = 2;
                }
                break;
            default:
                $currentColumn = 0;
                break;
        }

        if ($currentColumn != 0) {
            $item["column"] = $currentColumn;
        }


        if ($o->get_page_num() > 1) {
            $dataForJSON['lines'][] = $item;
            continue;
        }

        /*
        if ($project_title == "" && $o->get_type_json() == "Title"){
            $project_title = $o->get_content();
            $dataForJSON["titlePage"]["lines"][] = array(
                "type" => "Title",
                "text" => $project_title,
                "pageNum" => 0
            );
        } else if($project_author == "" && $o->get_type_json() == "Author") {
            $project_author = $o->get_content();
            $dataForJSON["titlePage"]["lines"][] = array(
                "type" => "Author",
                "text" => $project_author,3w``
                "pageNum" => 0
            );
        } else {
            $dataForJSON["titlePage"]["lines"][] = array(
                "type" => "Text",
                "text" => $o->get_content(),
                "pageNum" => 0
            );
        }
        */

        $dataForJSON["titlePage"]["lines"][] = $item;
        $is_there_title_page = 1;
    }
    $num_headers_and_footers = sizeof($headerObjects);
    $array_start = 0;

    if ($is_there_title_page)
    {   
        write_hf_data_if_it_exists(grab_header_or_footer($headerObjects[$array_start]), $dataForJSON, true, 'page1_header');
        write_hf_data_if_it_exists(grab_header_or_footer($footerObjects[$array_start]), $dataForJSON, true, 'page1_footer');
        $num_headers_and_footers--;
        $array_start++;
    }
    if ($num_headers_and_footers > 0)
    {
        write_hf_data_if_it_exists(grab_header_or_footer($headerObjects[$array_start]), $dataForJSON, false, 'page1_header');
        write_hf_data_if_it_exists(grab_header_or_footer($footerObjects[$array_start]), $dataForJSON, false, 'page1_footer');
    }
    if ($num_headers_and_footers > 1)
    {
        write_hf_data_if_it_exists(grab_header_or_footer($headerObjects[$array_start + 1]), $dataForJSON, false, 'header');
        write_hf_data_if_it_exists(grab_header_or_footer($footerObjects[$array_start + 1]), $dataForJSON, false, 'footer');
    }

    try {
        // print_r($dataForJSON);
        file_put_contents($filename, json_encode($dataForJSON, JSON_INVALID_UTF8_IGNORE));
    } catch(Exception $err){
        echo 'Caught exception: ',  $err->getMessage(), "\n";
    }
}

