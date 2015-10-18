<?php
require_once '../load.php';
session_start();
if(isset($_SESSION['userId']) && $_SESSION != "") {

} else {
	$meta = new BasicResponse();
	$meta->success = false;
	$meta->message = "You need to be logged in.";
	die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
}