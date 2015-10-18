<?php
	require_once 'load.php';
	$details = $user->updateUserDetails();
	$meta = new BasicResponse();
	$meta->success = true;
	$meta->message = "User details retrieved.";
	
	die(json_encode(array("meta"=>$meta, "details"=>$details), JSON_NUMERIC_CHECK));