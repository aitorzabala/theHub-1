<?php
if (! class_exists ( User )) {
	class User {
		function register() {
			global $thdb;
			
			// Check to make sure the form submission is coming from our script
			// The full URL of our registration page
			$current = 'http://' . $_SERVER ['HTTP_HOST'] . $_SERVER ['REQUEST_URI'];
			
			// The full URL of the page the form was submitted from
			$referrer = $_SERVER ['HTTP_REFERER'];
			
			/*
			 * Check to see if the $_POST array has date (i.e. our form was submitted) and if so,
			 * process the form data.
			 */
			if (! empty ( $_POST )) {
				
				/*
				 * Here we actually run the check to see if the form was submitted from our
				 * site. Since our registration from submits to itself, this is pretty easy. If
				 * the form submission didn't come from the register.php page on our server,
				 * we don't allow the data through. Good for webapps. Need to see what I can do mobile apps.
				 */
				// if ($referrer == $current) {
				
				require_once 'db.php';
				
				global $conn;
				// Set up the variables we'll need to pass to our insert method
				// This is the name of the table we want to insert data into
				$table = 'users';
				
				// These are the fields in that table that we want to insert data into
				$fields = array (
						'userId',
						'firstName',
						'lastName',
						'email',
						'password',
						'type',
						'registeredOn' 
				);
				
				// These are the values from our registration form... cleaned using our clean method
				$values = $thdb->clean ( $_POST );
				
				// Now, we're breaking apart our $_POST array, so we can store our password securely
				$userId = '';
				$firstName = $_POST ['firstName'];
				$lastName = $_POST ['lastName'];
				$email = $_POST ['email'];
				$password = $_POST ['password'];
				$type = $_POST ['type'];
				$registeredOn = $_POST ['registeredOn'];
				
				$response = $this->doesUserExist ( $email );
				
				if ($response ['exists']) {
					$meta = new BasicResponse ();
					$meta->success = false;
					$meta->message = "This email already exists.";
					die ( json_encode ( array (
							"meta" => $meta 
					), JSON_NUMERIC_CHECK ) );
				}
				
				// We create a NONCE using the action, username, timestamp, and the NONCE SALT
				$nonce = md5 ( 'registration-' . $email . $registeredOn . NONCE_SALT );
				
				// We hash our password
				$password = $thdb->hash_password ( $password, $nonce );
				
				$query = $conn->prepare ( "INSERT INTO " . $table . " (userId, firstName, lastName, email, password, type, registeredOn) VALUES (:id, :fname, :lname, 
						:email, :pwd, :type, :regon)" );
				
				$query->bindParam ( ":id", $userId );
				$query->bindParam ( ":fname", $firstName );
				$query->bindParam ( ":lname", $lastName );
				$query->bindParam ( ":email", $email );
				$query->bindParam ( ":pwd", $password );
				$query->bindParam ( ":type", $type );
				$query->bindParam ( ":regon", $registeredOn );
				
				$success = $query->execute ();
				
				if ($success) {
					// change to json if not changed
					$meta = new BasicResponse ();
					$meta->success = true;
					$meta->message = "Registration successful";
				
					
					$userId = $conn->lastInsertId ();
					$image = new ImageResponse ();
					$image->imageName = null;
					$image->imageUrl = null;
					$details = new UserResponse ();
					$details->userId = $userId;
					$details->firstName = $firstName;
					$details->lastName = $lastName;
					$details->email = $email;
					$details->type = $type;
					$details->image = $image;
					die ( json_encode ( array (
							"meta" => $meta,
							"details" => $details 
					), JSON_NUMERIC_CHECK ) );
				} else {
					$meta = new BasicResponse ();
					$meta->success = true;
					$meta->message = "Error in registartion. Please try again later.";
					die ( json_encode ( array (
							"meta" => $meta 
					), JSON_NUMERIC_CHECK ) );
				}
				
				// }
				// else {
				
				// // change to json if not changed
				// $meta = new BasicResponse();
				// $meta->success = false;
				// $meta->message = 'Your form submission did not come from the correct page. Please check with the site administrator.';
				// die(json_encode(array("meta"=>$meta)));
				// die('Your form submission did not come from the correct page. Please check with the site administrator. Referrer = ' . $referrer
				// . 'current = ' . $current);
				
				// }
			}
		}
		function login() {
			global $thdb;
			
			global $conn;
			
			if (! empty ( $_POST )) {
				
				// Clean our form data
				$values = $thdb->clean ( $_POST );
				
				// Email and password submitted by the user
				$sub_email = $values ['email'];
				$sub_password = $values ['password'];
				
				// The name of the table being used
				$table = 'users LEFT OUTER JOIN images USING(userId)';
				
				$response = $this->doesUserExist ( $sub_email );
				
				if (! $response ['exists']) {
					// Change this to json if not chamged
					$meta = new BasicResponse ();
					$meta->success = false;
					$meta->message = "Sorry. This email id does not exist.";
					die ( json_encode ( array (
							"meta" => $meta 
					), JSON_NUMERIC_CHECK ) );
				}
				
				$stmt = $response ['stmt'];
				
				$result = $stmt->fetch ( PDO::FETCH_ASSOC );
				
				// Get the registration date of the user
				$sto_registeredOn = $result ['registeredOn'];
				
				// The hashed password of the user
				$sto_password = $result ['password'];
				
				// Recreate our NONCE used at registration
				$nonce = md5 ( 'registration-' . $sub_email . $sto_registeredOn . NONCE_SALT );
				
				// Rehash the submitted password to see if it matches the stored hash
				$sub_password = $thdb->hash_password ( $sub_password, $nonce );
				
				// Check to see if the submitted password matches the stored password
				if ($sub_password == $sto_password) {
					
					// If there's a match, we rehash password to store in a cookie
					$authnonce = md5 ( 'cookie-' . $sub_email . $sto_registeredOn . AUTH_SALT );
					$authID = $thdb->hash_password ( $sub_password, $authnonce );
					
					// Set our authorization cookie
					setcookie ( 'theHub[user]', $sub_email, 0, '', '', '', true );
					setcookie ( 'theHub[authID]', $authID, 0, '', '', '', true );
					
					$sto_userId = $result ['userId'];
					$sto_firstName = $result ['firstName'];
					$sto_lastName = $result ['lastName'];
					$sto_email = $result ['email'];
					$sto_type = $result ['type'];
					
					$meta = new BasicResponse ();
					$meta->success = true;
					$meta->message = "You have logged in successfully.";
					$meta->errMessage = $errMsg;
					$image = new ImageResponse ();
					$image->imageName = $result ['imageName'];
					$image->imageUrl = $result ['imageUrl'];
					
					$details = new UserResponse ();
					$details->userId = $sto_userId;
					$details->firstName = $sto_firstName;
					$details->lastName = $sto_lastName;
					$details->email = $sto_email;
					$details->type = $sto_type;
					$details->image = $image;
					$details->star1 = $result ['star1'];
					$details->star2 = $result ['star2'];
					$details->star3 = $result ['star3'];
					$details->star4 = $result ['star4'];
					$details->star5 = $result ['star5'];
					$details->rating = $result ['rating'];
					$details->qualification = $result ['qualification'];
					$details->phone = "a" . $result ['phone'];
					
					$langs = $this->getUserLanguages ( $sto_userId );
					if ($langs != "error") {
						$details->languages = $langs;
					}
					
					$courses = $this->getUserCourses ( $sto_userId );
					if ($courses != "error") {
						$details->courses = $courses;
					}
					die ( json_encode ( array (
							"meta" => $meta,
							"details" => $details 
					), JSON_NUMERIC_CHECK ) );
				} else {
					
					$meta = new BasicResponse ();
					$meta->success = false;
					$meta->message = "Email/Password do not match. Please try again";
					die ( json_encode ( array (
							"meta" => $meta 
					), JSON_NUMERIC_CHECK ) );
				}
			}
		}
		function logout() {
			// Expire our auth coookie to log the user out
			$idout = setcookie ( 'theHub[authID]', '', - 3600, '', '', '', true );
			$userout = setcookie ( 'theHub[user]', '', - 3600, '', '', '', true );
			
			if ($idout == true && $userout == true) {
				$meta = new BasicResponse ();
				$meta->success = true;
				$meta->message = 'Logged out successfully';
				die ( json_encode ( array (
						"meta" => $meta 
				), JSON_NUMERIC_CHECK ) );
			} else {
				$meta = new BasicResponse ();
				$meta->success = false;
				$meta->message = 'Unable to logout. Please try again';
				die ( json_encode ( array (
						"meta" => $meta 
				) ) );
			}
		}
		function checkLogin() {
			global $thdb;
			
			// Grab our authorization cookie array
			$cookie = $_COOKIE ['theHub'];
			
			// Set our user and authID variables
			$user = $cookie ['user'];
			$authID = $cookie ['authID'];
			
			/*
			 * If the cookie values are empty, we redirect to login right away;
			 * otherwise, we run the login check.
			 */
			if (! empty ( $cookie )) {
				
				// Query the database for the selected user
				$table = 'users';
				$sql = "SELECT * FROM $table WHERE email = '" . $user . "'";
				$results = $thdb->select ( $sql );
				
				// Kill the script if the submitted username doesn't exit
				if (! $results) {
					die ( 'Sorry, that username does not exist!' );
				}
				
				// Fetch our results into an associative array
				$results = mysql_fetch_assoc ( $results );
				
				// The registration date of the stored matching user
				$sto_reg = $results ['registeredOn'];
				
				// The hashed password of the stored matching user
				$sto_pass = $results ['password'];
				
				// Rehash password to see if it matches the value stored in the cookie
				$authnonce = md5 ( 'cookie-' . $user . $sto_reg . AUTH_SALT );
				$sto_pass = $thdb->hash_password ( $sto_pass, $authnonce );
				
				if ($sto_pass == $authID) {
					$meta = new BasicResponse ();
					$meta->success = true;
					$meta->message = 'logged in.';
					die ( json_encode ( array (
							"meta" => $meta 
					), JSON_NUMERIC_CHECK ) );
				} else {
					$meta = new BasicResponse ();
					$meta->success = false;
					$meta->message = 'Not logged in.';
					die ( json_encode ( array (
							"meta" => $meta 
					), JSON_NUMERIC_CHECK ) );
				}
			} else {
				$meta = new BasicResponse ();
				$meta->success = false;
				$meta->message = 'Not logged in.';
				die ( json_encode ( array (
						"meta" => $meta 
				), JSON_NUMERIC_CHECK ) );
			}
		}
		function doesUserExist($sub_email) {
			global $conn;
			
			$table = "users LEFT OUTER JOIN images USING(userId)";
			/*
			 * Run our query to get all data from the users table where the user
			 * login matches the submitted login.
			 */
			$stmt = $conn->prepare ( "SELECT * FROM $table WHERE email = " . $conn->quote ( $sub_email ) );
			$stmt->execute ();
			
			$rows = $stmt->rowCount ();
			
			// Kill the script if the submitted username doesn't exit
			if ($rows == 0) {
				
				return array (
						"exists" => false,
						"stmt" => $stmt 
				);
			} else {
				
				return array (
						"exists" => true,
						"stmt" => $stmt 
				);
			}
		}
		function getUserDetails($id) {
			global $conn;
			$table = 'users LEFT OUTER JOIN images USING(userId)';
				$sql = "SELECT * FROM $table WHERE userId = ?";
				$stmt = $conn->prepare ( $sql );
				$stmt->execute ( array (
						$id 
				) );
				
				$result = $stmt->fetch ( PDO::FETCH_ASSOC );
				
				$image = new ImageResponse ();
				$image->imageName = $result ['imageName'];
				$image->imageUrl = $result ['imageUrl'];
				
				$sto_userId = $result ['userId'];
				$sto_firstName = $result ['firstName'];
				$sto_lastName = $result ['lastName'];
				$sto_email = $result ['email'];
				$sto_type = $result ['type'];
				
				$details = new UserResponse();
				$details->userId = $sto_userId;
				$details->firstName = $sto_firstName;
				$details->lastName = $sto_lastName;
				$details->email = $sto_email;
				$details->type = $sto_type;
				$details->image = $image;
				$details->star1 = $result['star1'];
				$details->star2 = $result['star2'];
				$details->star3 = $result['star3'];
				$details->star4 = $result['star4'];
				$details->star5 = $result['star5'];
				$details->rating = $result['rating'];
				$details->qualification = $result['qualification'];
				$details->phone = "a".$result['phone'];
				
				$langs = $this->getUserLanguages($sto_userId);
				if ($langs != "error") {
					$details->languages = $langs;
				}
					
				$courses = $this->getUserCourses($sto_userId);
				if ($courses != "error") {
					$details->courses = $courses;
				}
				return $details;
			
		}
		
		function getUserDetailsByCourse($course, $type) {
			global $conn;
			$table = 'users LEFT OUTER JOIN useruos USING (userId)';
				$sql = "SELECT users.userId FROM $table WHERE useruos.uoscode = ? AND users.type = ? ";
				
				if ($course == "") {
					return (array("error"=>true, "details"=>"Enter Course Code"));
				}
				
				if ($type == "") {
					return (array("error"=>true, "details"=>"Enter user type"));
				}
				try {
					$stmt = $conn->prepare($sql);
					$stmt->execute(array($course, $type));
					$users = array();
					$total = 0;
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$total++;
						$users[] = $this->getUserDetails($row['userId']);
					}
					return (array("error"=>false, "details"=>$users, "totalCount"=>$total));
				} catch (Exception $e) {
					echo $e->getMessage();
					return (array("error"=>true, "details"=>"Error retrieving user details from database."));
				}
				
		}
		
		function updateUserDetails() {
			global $conn;
			$table = "users";
				if (!empty($_POST)) {
					$id = $_POST['userId'];
					$phone = $_POST['phone'];
					$qualification = $_POST['qualification'];
					if (empty($id)) {
						$meta = new BasicResponse();
						$meta->success = false;
						$meta->message = "UserId cannot be empty ";
						die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
					}
					$sql = "UPDATE $table SET ";
					$params = array();
					$input = array();
					if(isset($phone)){
						$params[] = "phone = ?";
						$input[] = $phone;
					}
					if(isset($qualification)){
						$params[] = "qualification = ?";
						$input[] = $qualification;
					}
					$sql .= implode(", ", $params);
					$sql .= " WHERE userId = ?";
					$input[] = $id;
					try {
						if (!empty($params)) {
							$stmt = $conn->prepare($sql);
							$stmt->execute($input);
						}
						$this->deleteLanguages();
						$this->setLanguages();
						$this->deleteCourses();
						$this->setCourses();
						$details = $this->getUserDetails($id);
						return $details;
					} catch (Exception $e) {
						$meta = new BasicResponse();
						$meta->success = false;
						$meta->message = "You need to be logged in.";
						$meta->errMessage = $e->getMessage();
						die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
					}		
					
				}
			
		}
		
		function uploadImage() {
					
			global $thdb;
			
			global $conn;
			
			$maxFileSize = 10000000;
			
			$file = $_FILES['image']['tmp_name'];
			
			$imageTmpName = addslashes($_FILES['image']['tmp_name']);
			$imageName = $_FILES['image']['name'];
			if ($imageName == '') {
				return 1;
			}
			$imageData = getimagesize($_FILES['image']['tmp_name']);
			$imageFileSize = $_FILES['image']['size'];
			
			if ($imageData == FALSE ||
					!($imageData[2] == IMAGETYPE_GIF || $imageData[2] == IMAGETYPE_JPEG
							|| $imageData[2] == IMAGETYPE_PNG)) {
				return 2;
			}
			
			if ($imageFileSize > $maxFileSize) {
				return 3;
			}
			
			$userid = $_POST['userId'];
			$tmp = explode(".", $imageName);
			$ext = $tmp[count($tmp) - 1];
			$newName = $userid."as".round(microtime(true))."";
			$newNameExt = $newName.".".$ext;
			
			move_uploaded_file($imageTmpName, "images/$newNameExt");
			$table = 'images';
			try {
				$query = $conn->prepare("INSERT INTO " .$table." (imageName,imageUrl,userId) VALUES (?,?,?)");
				
				$success = $query->execute(array($newName,"theHub/images/".$newNameExt, $userid));
			} catch (PDOException $e) {
				echo $e->getMessage();
			}
			
			if($success) {
				try {
					
					$stmt = $conn->prepare("SELECT * FROM users LEFT OUTER JOIN $table USING (userId) WHERE userId = ?");
					$stmt->execute(array($userid));
					
					$result = $stmt->fetch(PDO::FETCH_ASSOC);
					
					$meta = new BasicResponse();
					$meta->success = true;
					$meta->message = "Image uploaded";
					
					
					$image = new ImageResponse();
					$image->imageName = $result['imageName'];
					$image->imageUrl = $result['imageUrl'];
					
					$details = new UserResponse();
					$details->userId = $result['userId'];
					$details->firstName = $result['firstName'];
					$details->lastName = $result['lastName'];
					$details->email = $result['email'];
					$details->type = $result['type'];
					$details->image = $image;
					$details->star1 = $result['star1'];
					$details->star2 = $result['star2'];
					$details->star3 = $result['star3'];
					$details->star4 = $result['star4'];
					$details->star5 = $result['star5'];
					$details->rating = $result['rating'];
					$details->qualification = $result['qualification'];
					$details->phone = "a".$result['phone'];
						
					$langs = $this->getUserLanguages($sto_userId);
					if ($langs != "error") {
						$details->languages = $langs;
					}
						
					$courses = $this->getUserCourses($sto_userId);
					if ($courses != "error") {
						$details->courses = $courses;
					}
					die(json_encode(array("meta"=>$meta, "details"=>$details), JSON_NUMERIC_CHECK));
					
				} catch (Exception $e) {
					
					echo $e->getMessage();
					
				}
				
				
				
			} else {
				$meta = new BasicResponse();
				$meta->success = false;
				$meta->message = "There was an error uploading the image. Please try again";
				
				die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
			}
						
		
		}
	
		function loadLanguages(){
				global $conn;
				$sql = 'SELECT * FROM languages';
				try {
					$stmt = $conn->prepare($sql);
					$stmt->execute();
					$langs = array();
					$langsEng = array();
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$langs[] = array(
								"shortName" => $row['2letter'],
								"englishName" => $row['english_name']
						);
						$langsEng[] = $row['english_name'];
					}
					$meta = new BasicResponse();
					$meta->success = true;
					$meta->message = "Languages retrieved.";
					
					die(json_encode(array("meta"=>$meta, "details"=>array("languages"=>$langs, "englishNames"=>$langsEng)), JSON_NUMERIC_CHECK));
				} catch (Exception $e) {
					$meta = new BasicResponse();
					$meta->success = false;
					$meta->message = "Error loading languages";
					$meta->errMessage = $e->getMessage();
					die(json_encode(array("meta"=>$meta)));
				}
				
		}
			
		function setLanguages() {
				global $thdb;
				global $conn;
				$table = "userlanguages";
				if (!empty($_POST['langs']) && !empty($_POST['userId'])) {
					$values = $_POST;
					$userId = $values['userId'];
					$langs = $values['langs'];
					$params = array();
					$input = array();
					foreach ($langs as $row) {
						$params[] = "(?, ?)";
						$input[] = $userId;
						$input[] = $row;
					}
					try {
						$sql = "INSERT INTO $table (userId, 2letter) VALUES ".implode(", ", $params);
						$stmt = $conn->prepare($sql);
						$stmt->execute($input);
						return true;		
					} catch (Exception $e) {
						$meta = new BasicResponse();
						$meta->success = false;
						$meta->message = "Error updating languages. Please try again";
						$meta->errMessage=$e->getMessage();
						die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
					}
				} 
		
		}
		
		function deleteLanguages() {
				global $thdb;
				global $conn;
				$table = "userlanguages";
				if (!empty($_POST['delLangs']) && !empty($_POST['userId'])) {
					$values = $_POST;
					$userId = $values['userId'];
					$delLangs = $values['delLangs'];
					$params = array();
					$input = array();
					foreach ($delLangs as $row) {
						$params[] = "(?, ?)";
						$input[] = $userId;
						$input[] = $row;
					}
					try {
						$sql = "DELETE FROM $table WHERE (userId, 2letter) IN (".implode(", ", $params).")";
						$stmt = $conn->prepare($sql);
						$stmt->execute($input);
						return true;
					} catch (Exception $e) {
						$meta = new BasicResponse();
						$meta->success = false;
						$meta->message = "Error updating languages. Please try again";
						$meta->errMessage=$e->getMessage();
						die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
					}
				}
			
		}
		
		function getUserLanguages($id) {
				global $conn;
				$table = "userlanguages INNER JOIN languages USING (2letter)";
				try {
					$sql = "SELECT 2letter, english_name FROM $table WHERE userId = ?";
					$stmt = $conn->prepare($sql);
					$stmt->execute(array($id));
				
					$langs = array();
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$langs[] = array(
								"shortName" => $row['2letter'],
								"englishName" => $row['english_name']
						);
					}
					return $langs;
				
				} catch (Exception $e) {
					return "error";
				}
			
		}
		
		function loadCourses() {
				global $conn;
				$table = "unitofstudy";
				$sql  = "SELECT * FROM $table";
				try {
					$stmt = $conn->prepare($sql);
					$stmt->execute();
				
					$courses = array();
					$courseCodes =array();
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$courses[] = array(
								"courseCode" => $row['uoscode'],
								"courseName" => $row['uosname']
						);
						$courseCodes[] = $row['uoscode'];
					}
					$meta = new BasicResponse();
					$meta->success = true;
					$meta->message = "Courses retrieved.";
				
					die(json_encode(array("meta"=>$meta, "details"=>array("courses"=>$courses, "courseCodes"=> $courseCodes)), JSON_NUMERIC_CHECK));
				} catch (Exception $e) {
					$meta = new BasicResponse();
					$meta->success = false;
					$meta->message = "Error loading courses.";
					$meta->errMessage = $e->getMessage();
					die(json_encode(array("meta"=>$meta)));
				} 
			
			
		}
		
		function setCourses() {
				global $conn;
				$table = "useruos";
				if (!empty($_POST['courses']) && !empty($_POST['userId'])) {
					$values = $_POST;
					$userId = $values['userId'];
					$courses = $values['courses'];
					$params = array();
					$input = array();
					foreach ($courses as $row) {
						$params[] = "(?, ?)";
						$input[] = $userId;
						$input[] = $row;
					}
					try {
						$sql = "INSERT INTO $table (userId, uoscode) VALUES ".implode(", ", $params);
						$stmt = $conn->prepare($sql);
						$stmt->execute($input);
						$meta = new BasicResponse();
						return true;
					} catch (Exception $e) {
						$meta = new BasicResponse();
						$meta->success = false;
						$meta->message = "Error adding courses";
						$meta->errMessage = $e->getMessage();
						die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
					}
				}
			
		}

		function deleteCourses() {
				global $conn;
				$table = "useruos";
				if (!empty($_POST['delCourses']) && !empty($_POST['userId'])) {
					$values = $_POST;
					$userId = $values['userId'];
					$delCourses = $values['delCourses'];
					$params = array();
					$input = array();
					foreach ($delCourses as $row) {
						$params[] = "(?, ?)";
						$input[] = $userId;
						$input[] = $row;
					}
					try {
						$sql = "DELETE FROM $table WHERE (userId, uoscode) IN (".implode(", ", $params).")";
						$stmt = $conn->prepare($sql);
						$stmt->execute($input);
						$meta = new BasicResponse();
						return true;
					} catch (Exception $e) {
						$meta = new BasicResponse();
						$meta->success = false;
						$meta->message = "Error adding courses";
						$meta->errMessage = $e->getMessage();
						die(json_encode(array("meta"=>$meta), JSON_NUMERIC_CHECK));
					}
				}
			
			
		
		}
		
		function getUserCourses($id) {
				global $conn;
				$table = "useruos INNER JOIN unitofstudy USING (uoscode)";
				try {
					$sql = "SELECT uoscode, uosname FROM $table WHERE userId = ?";
					$stmt = $conn->prepare($sql);
					$stmt->execute(array($id));
						
					$langs = array();
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						$langs[] = array(
								"courseCode" => $row['uoscode'],
								"courseName" => $row['uosname']
						);
					}
					return $langs;
						
				} catch (Exception $e) {
					return "error";
				}
			
		}
		
		
		
	}	
}

//Instantiate the User class
$user = new User();

?>