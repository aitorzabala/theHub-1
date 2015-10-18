<?php
require_once 'load.php';
if (!empty($_GET['id'])) {
	$langs = $user->getUserLanguages($_GET['id']);
	if ($langs == "error") {
		$meta = new BasicResponse();
		$meta->success = false;
		$meta->message = "Error in retrieving languages. Please try again.";
		
		die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
	} elseif (empty($langs)) {
		$meta = new BasicResponse();
		$meta->success = true;
		$meta->message = "No languages found";
		
		die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
	} else {
		$meta = new BasicResponse();
		$meta->success = true;
		$meta->message = "Languages retrieved.";
		
		die(json_encode(array("meta"=>$meta, "details"=>array("languages"=>$langs)), JSON_NUMERIC_CHECK));
	}
}