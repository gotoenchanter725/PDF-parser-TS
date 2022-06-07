<?php

$get_config_override = array();

require("LoadRequirements.php");

// Actually do the work
$filename = FALSE;

for ($i=1; $i < $argc; ++$i) {
	$split = explode("=", $argv[$i]);
	if (count($split) == 2) {
        $argName = $split[0];
        $argValue = $split[1];

        if($argName == "pdftohtml"){
            $get_config_override[$argName] = $argValue;
        } else if($argName == "outputFilename"){
            $OUTPUT_FILE_NAME = $argValue;
        } else {
            write_log("Unknown cmd-line option \"{$argName}\"");
            print "Error: Unknown command-line option \"{$argName}\"\n";
            exit(1);
        }
	} elseif ($argv[$i] == '--celtx') {
		$CONVERT_TO_CELTX = TRUE;
	} elseif ($argv[$i] == '--fountain') {
		$CONVERT_TO_FOUNTAIN = TRUE;
	} elseif ($argv[$i] == '--json') {
        $CONVERT_TO_JSON = TRUE;
    } else if ($argv[$i] == '-X1707') {
		$DUMP_BLOCKS = TRUE;
	} else if ($argv[$i][0] == '-') {
		write_log("Unknown cmd-line option \"{$argv[$i]}\"");
		print "Error: Unknown command-line option \"{$argv[$i]}\"\n";
		exit(1);
	} else {
		// it's presumably a filename
		$filename = $argv[$i];
		if ($i != $argc-1) {
			write_log("Extra args after filename");
			print "Error: Extra args after filename \"$filename\"\n";
			exit(1);
		}
	}
}

if ($filename === FALSE) {
	write_log("No filename on command line");
	print "Error: Command line requires a filename\n";
	exit(1);
} else {
	$parser = new Parser($filename);
	$parser->parse();

	if ($GLOBALS['DUMP_BLOCKS']) {
		print_r($parser->get_objects());
	}

	$analyzer = new Analyzer($parser->get_objects());
	$analyzer->analyze();

    // print everything at the end
    if(true){
        $dataForJSON = array();

        foreach ($parser->get_objects() as $num => $object) {
            $item = array(
                "type" => $object->get_type(),
                "text" => $object->get_content()
            );

            $dataForJSON[] = $item;
//            print($object->get_type() . "\t" . $object->get_content() . "\n");
        }

        $json_pretty = json_encode($dataForJSON, JSON_PRETTY_PRINT);
        header('Content-type:application/json;charset=utf-8');
    }

	// [guyg] Test some analyzer
	if (false) {
		$scene_analyzer = new DialogAverages();
		$analyzer->analyze_scenes($scene_analyzer);
		$scene_analyzer->display_analysis();
	}

	if (false) {
		//********* For checking the results
		
		$analyzer->print_structure();
		
		$scene_analyzer = new ActionVersusDialog();
		$analyzer->analyze_scenes($scene_analyzer);
		$scene_analyzer->display_analysis();
	}
}
