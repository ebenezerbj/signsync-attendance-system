<?php
// Mock file_get_contents for testing
if (!function_exists("file_get_contents_original")) {
    function file_get_contents_original($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
        return call_user_func_array("file_get_contents", func_get_args());
    }
}

function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
    if ($filename === "php://input") {
        global $mockInput;
        return $mockInput ?: "";
    }
    return file_get_contents_original($filename, $use_include_path, $context, $offset, $length);
}

// Include the actual API
include "wearos_api.php";
?>