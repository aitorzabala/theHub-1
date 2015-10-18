<?php
	require_once 'load.php';
	if (!empty($_GET['id'])) {
		$details = $user->getUserDetails($_GET['id']);
		if ($details == "error") {
			$meta = new BasicResponse();
			$meta->success = false;
			$meta->message = "Error in retrieving details. Please try again.";
		
			die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
		} elseif (empty($details)) {
			$meta = new BasicResponse();
			$meta->success = true;
			$meta->message = "No users found";
		
			die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
		} else {
			$meta = new BasicResponse();
			$meta->success = true;
			$meta->message = "User details retrieved.";
		
			die(json_encode(array("meta"=>$meta, "details"=>$details), JSON_NUMERIC_CHECK));
		}
	}