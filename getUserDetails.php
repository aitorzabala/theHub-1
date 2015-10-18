<?php
	require_once 'load.php';
	if (!empty($_GET)) {
		$course = $_GET['coursecode'];
		$type = $_GET['type'];
		$return = $user->getUserDetailsByCourse($course, $type);
		if ($return['error']) {
			$meta = new BasicResponse();
			$meta->success = false;
			$meta->message = $return['details'];
			die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
		} else {
			$meta = new BasicResponse();
			$meta->success = true;
			$meta->message = "User details retrieved";
			die(json_encode(array("meta"=>$meta, "details"=>array("totalCount"=>$return['totalCount'],
					"users"=>$return['details'])), JSON_NUMERIC_CHECK));
		}
	}