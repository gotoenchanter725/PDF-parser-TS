<?php

// This should be first, because it sets temp directories and stuff
require_once(dirname(__FILE__) . "/../UniversalUtilities.php");

// This should be second, because it defaults the log file
require_once(dirname(__FILE__) . "/../private_backend/Utilities.php");
require_once(dirname(__FILE__) . "/Utilities.php");

require(dirname(__FILE__) . "/Log.php");
require(dirname(__FILE__) . "/Objects.php");
require(dirname(__FILE__) . "/Parser.php");
require(dirname(__FILE__) . "/Analyzer.php");
require(dirname(__FILE__) . "/LooksLike.php");
require(dirname(__FILE__) . "/ParseText.php");
require(dirname(__FILE__) . "/ParseShakespeare.php");
require(dirname(__FILE__) . "/WriteOutput.php");
