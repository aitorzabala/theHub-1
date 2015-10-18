<?php
require_once 'load.php';
if (!empty($_GET['id'])) {
	$courses = $user->getUserCourses($_GET['id']);
	if ($courses == "error") {
		$meta = new BasicResponse();
		$meta->success = false;
		$meta->message = "Error in retrieving courses. Please try again.";
		
		die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
	} elseif (empty($courses)) {
		$meta = new BasicResponse();
		$meta->success = true;
		$meta->message = "No courses found";
		
		die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
	} else {
		$meta = new BasicResponse();
		$meta->success = true;
		$meta->message = "Courses retrieved.";
		
		die(json_encode(array("meta"=>$meta, "details"=>array("courses"=>$courses)), JSON_NUMERIC_CHECK));
	}
}