<?php
#Copyright (c) 2011-2013 Nick Whyte & Oscar Rainford - me@fourbytes.me

#Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
#The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
#THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.


session_start();
class nAuth {

	private $SQL;
	private $Config;

	##############################
	# Class Construct/Destruct   #
	##############################

	/*
	/ Connects to MySQL Server, runs when an nAuth object is created.
	/ Returns true if a successful MySQL connection is created. Otherwise returns false.
	*/
	public function __construct($SQLSettings) {
		// Set the global MySQL config settings.
		$this->SQL['CONFIG'] = $SQLSettings;

		// Begin connection process.
		$this->SQL['CONNECTION'] =  new mysqli($this->SQL['CONFIG']['SERVER'], $this->SQL['CONFIG']['USERNAME'], $this->SQL['CONFIG']['PASSWORD'], $this->SQL['CONFIG']['DATABASE']);
		if ($this->SQL['CONNECTION']) { //Catch any error that may occur during connection
			return true;
		} else {
			die("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
		}
	}

	/*
	/ Close the MySQL session.
	/
	*/
	public function __destruct() {
		//This function is used to unset the SQL array
		$this->SQL['CONNECTION']->close();
	}


	##############################
	# Login/Logout Methods       #
	##############################

	/*
	/ Set session variabls with specified user details
	/
	*/
	public function login($credentialsArray = false, $sessionTime = 86400, $encryptedPassword = false) {
		//Get the credentials in an array, also get the session time in seconds, and an encrypted password if set.
		//This function will return true if the username and password are correct.

		// Set the valid session time, default is 1 day.
		$expireTime = time() + $sessionTime;

		// Make sure the credentials array has been set
		if($credentialsArray) {
			$username = $credentialsArray[0];

			// Check if an already encrypted password has been set.
			if ($encryptedPassword) {
				$password = $encryptedPassword;
			} else {
				$password = md5(sha1($credentialsArray[1]));
			}

			if ($this->checkAccount($username,$password)) {
				// Credentials are valid, log them in!
				$this->session($username, $password, $expireTime);
				return true;
			} else {
				// Unable to login, bad credentials.
				return false;
			}
		} else {
			return false;
		}
	}

	/*
	/ Log's out the current user.
	/ Destroys the PHP session and cookies.
	*/
	public function logout() {
		$this->destoryCookies();
		$this->destorySession();
	}

	##############################
	# Session Management Methods #
	##############################

	/*
	/ Sets all of the session information and cookies.
	/
	*/
	private function session($username, $password, $expire) {
		//going to set all the sessions and the expiry of the sessions.
		//baking some cookies here too.

		$_SESSION['nAuth']['Username'] = $username;
		$_SESSION['nAuth']['Password'] = $password;
		setcookie("Username", $username, $expire);
		setcookie("Password", $password, $expire);
	}

	/*
	/ Check if user is logged in.
	/
	*/
	public function checkSession() {
		//This function is in charge of finding out if a user is logged in after a session timeout, or a page click

		if (isset($_SESSION['nAuth']['Username']) && isset($_SESSION['nAuth']['Password'])) {
			//the sessions are set,
			//set the local variables for this function
			$username = $_SESSION['nAuth']['Username'] ;
			$password = $_SESSION['nAuth']['Password'];
			//see if it's all still correct and valid in the DB's
			if ($this->checkAccount($username, $password)) {
				return true;
			} else {
				$this->logout();
				return false;
			}
		} elseif (isset($_COOKIE["Username"]) && isset($_COOKIE["Password"])) {
			//well, the user has still got cookies at least, these can be used.
			$username = $_COOKIE["Username"];
			$password = $_COOKIE["Password"];
			//see if it's all still correct and valid in the DB's (cuz people can edit cookies you know)
			if ($this->checkAccount($username, $password)) {
				//set the sessions
				$_SESSION['nAuth']['Username'] = $username;
				$_SESSION['nAuth']['Password'] = $password;
				return true;
			} else {
				//user doesn't exit
				return false;
				$this->logout();
			}

		}
		else {
			return false; //There is no open login, they will need to be taken to a login page.
		}
	}

	##############################
	# Account Management Methods #
	##############################

	/*
	/ Modifies a certain row of the specified user (or current user if no user is specified)
	/
	*/
	public function modifyUserRow($table_col, $value, $userID = false) {
		//Edit a user row in the DB. Defaults to current user if no User ID is given

		if (!isset($userID) || $userID == false) {
			$userID	= $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		$value = $this->SQL['CONNECTION']->real_escape_string($value);
		$sql = "UPDATE `" .$this->SQL['CONFIG']['TABLE'] . "` SET `$table_col` = '$value' WHERE id = $userID;";
		$query = $this->SQL['CONNECTION']->query($sql);
		if ($query) {
			return true;
		}
		else {
			return 'Error in Query';
		}
	}

	/*
	/ Deletes the account of the specified user ID.
	/
	*/
	public function deleteAccount($userID) {
		if (!isset($userID) || $userID == false) {
			$userID	= $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		$account = $this->SQL['CONNECTION']->real_escape_string($userID);
		$query 	= $this->SQL['CONNECTION']->query("DELETE FROM `" . $this->SQL['CONFIG']['TABLE'] . "` WHERE `uid` = '$account';");
		if ($query) {
			return true;
		}
		else {
			return false;
		}
	}

	/*
	/ Return's the ID of the username specified
	/
	*/
	public function getIDFromUsername($username) {
		$username = $this->SQL['CONNECTION']->real_escape_string($username);
		$sql = "SELECT * FROM `" . $this->SQL['CONFIG']['TABLE'] . "` WHERE `username` = '$username';";
		$query = $this->SQL['CONNECTION']->query($sql);
		$row	= $query->fetch_assoc();
		return $row['uid'];
	}

	/*
	/ Set's the password of a specified user by ID.
	/
	*/
	public function setPasswordByID($password1, $password2, $userID = false) {
		//This function allows updating of a user's password.

		if (!isset($userID) || $userID == false) {
			//get the current user ID
			$userID	= $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		if ($password1 == $password2) {
				//encrypt the password
			$password =  md5(sha1($password1));

				//Clean up inputs before insertion and encryption
			$password 	= 	$this->SQL['CONNECTION']->real_escape_string($password);
			$userID 	= 	$this->SQL['CONNECTION']->real_escape_string($userID);

				//insert into the SQL Database.
			$sql = "UPDATE `" .$this->SQL['CONFIG']['TABLE'] . "` SET `password` = '$password' WHERE id = $userID;";
			$query = $this->SQL['CONNECTION']->query($sql);

				//handle the errors
			if ($query) {
				return true;
			}
			else {
				return 'Error in Query';
			}
		}
		else{
			return 'Passwords Don\'t Match';
		}
	}

	/*
	/ Set's the password of a specified user by username.
	/
	*/
	public function setPasswordByUsername($password1, $password2, $userName = false) {

		if (!isset($userName) || $userName == false) {
			$userID	= $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		else {
			$userID	= $this->getIDFromUsername($userName);
		}
		return $this->update_user_password_by_ID($password1, $password2, $userID);
	}


	##############################
	# Registration Methods       #
	##############################

	/*
	/ Create a user with the specified data.
	/
	*/
	public function register($data_to_insert, $column_headings, $column_min_lengths) {
		//get data in an array
		//also get the column headings, but check to see if i already have them or not;
		//also get min lengths for each column, in an array, BUT, if it's given in int, it applies to all cols. it doesn't have to be set, if it isn't defualts to no min len
		//let's check the datatype of the min lenths,

		$heads_for_sql = '';
		$vals_for_sql = '';

		if(!is_array($column_min_lengths)  || !is_array($column_headings) || !is_array($data_to_insert)) {
			//Invalid Usage, need to parse these as arrays lol.
			die('You must pass Arrays to the register function. Read documentation online');
		}
		if(count($data_to_insert) == count($column_headings) && count($column_min_lengths) == count($column_headings) ) {
			//inserted columns are all the same length, begin sorting the inserted data :3
			foreach ($column_headings as $key => $val) {
				if ($val == 'password') { $p1K = $key; }
				elseif ($val == 'password2') { $p2K = $key; }
				elseif ($val == 'username') { $uK = $key; }
				if ($val != 'password2' && $val != 'password') {
					$heads_for_sql .= '`' . $val . '`,';
				}
			}
			foreach ($data_to_insert as $key => $val) {
				//loop through the data to insert, and extract passwords and usernames
				if ($key == $p1K) {
					$pass1 = $this->SQL['CONNECTION']->real_escape_string($val);
				}
				else if ($key == $p2K) {
					$pass2 = $this->SQL['CONNECTION']->real_escape_string($val);
				}
				else if ($key == $uK) {
					$username = $this->SQL['CONNECTION']->real_escape_string($val);
				}
				if ($key != $p2K && $key != $p1K) {
					$vals_for_sql .= '\'' . $this->SQL['CONNECTION']->real_escape_string($val) .'\', ';
				}
			}
			$sql = "SELECT * FROM `" . $this->SQL['CONFIG']['TABLE'] . "` WHERE `username` = '$username';";
			$query = 	$this->SQL['CONNECTION']->query($sql);
			$count	=	$query->num_rows;
			if ($count > 0) {
				return 'User Exists';
			}
			$heads_for_sql = preg_replace('/(.*),/','$1',$heads_for_sql);
			$vals_for_sql = preg_replace('/(.*),/','$1',$vals_for_sql);

			//begin validation section.
			$col_count = count($column_headings) - 1;
			$valid		= true;
			for ($i = 0; $i <= $col_count; $i++) {
				if (strlen($data_to_insert[$i]) < $column_min_lengths[$i]) {
					$valid		= false;
				}
			}
			if ($valid) {
				if ($pass1 == $pass2) {
					//let's craft that query
					$password = md5(sha1($pass1));
					$sql = "INSERT INTO `" . $this->SQL['CONFIG']['TABLE'] . "` ($heads_for_sql, `password`) VALUES ($vals_for_sql, '$password');";
					$query = $this->SQL['CONNECTION']->query($sql);
					if ($query) {
						return true;
					}
					else {
						return 'SQL Insertion Failed'.$this->SQL['CONNECTION']->error();
					}
				}
				else {
					return 'passwords';
				}
			}
			else {
				//Data lengths are wrong, you must fulfil the site requirements
				return 'Data Lengths';
			}
		}
		else {
			//inserted data fails - arrays are invalid;
			die('Arrays are not the same length - You must input 3 arrays of the same length. View documentation online');
		}
	}


	###############################
	# Account Referencing Methods #
	###############################

	/*
	/ Returns a users details in an array.
	/
	*/
	public function getUserDetailsArray($user = false) {
		if (!$user) {
			$user = $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		$sql = "SELECT * FROM `".  $this->SQL['CONFIG']['TABLE'] . "` WHERE `uid` = $user ;";
		$query = $this->SQL['CONNECTION']->query($sql);
		return $query->fetch_assoc();
	}

	/*
	/ Returns a users details by column.
	/
	*/
	public function getUserDetails($column, $userID = false) {
		//Entry of a user ID is required. Use getIDFromUsername() to convert
		//user entry is not needed, it gets the current user instead.
		if (!$userID) {
			$userID = $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		$sql = "SELECT `$column` FROM `".  $this->SQL['CONFIG']['TABLE'] . "` WHERE `uid` = $userID ;";
		$query = $this->SQL['CONNECTION']->query($sql);
		$row = $query->fetch_assoc();
		return $row[$column];
	}

	/*
	/ Returns true if the specified username and encrypted password are in the database. Otherwise false.
	/
	*/
	private function checkAccount($enc_user, $enc_pass) {
		$enc_pass = $this->SQL['CONNECTION']->real_escape_string($enc_pass);
		$enc_user = $this->SQL['CONNECTION']->real_escape_string($enc_user);
		$sql = "SELECT * FROM `" . $this->SQL['CONFIG']['TABLE'] . "` WHERE `username` = '$enc_user' AND `password` = '$enc_pass';";
		$query =  $this->SQL['CONNECTION']->query($sql);
		$count =  $query->num_rows;
		if ($count == 1) {
			return true;
		}
		else {
			return false;
		}
	}


	###############################
	# Misc Other Methods  		  #
	###############################

	/*
	/ Destroys the nAuth session, used when logging out.
	/
	*/
	private function destorySession() {
		unset($_SESSION['nAuth']['Username']);
		unset($_SESSION['nAuth']['Password']);
		return true;
	}

	/*
	/ Destroys the nAuth cookie variables, used when logging out.
	/
	*/
	private function destoryCookies() {
		setcookie("Username","",0);
		setcookie("Password","",0);
		return true;
	}
}
?>