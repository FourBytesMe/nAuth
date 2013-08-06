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
	# SQL Management Methods     #
	##############################

	/*
	/ Connects to SQL, will be replaced by __construct() soon.
	/ 
	*/
	public function connect_SQL() {
		// This is the method that connects to an SQL Server. It gets the settings from the SQL array. This must be set before using this method, otherwise, it will return an error
		if (!isset($this->SQL['Settings'])) { //question if there is an array or not
			die('You must configure the SQL Settings first'); 	
		}
		else {
			//begin connection process.
			$this->SQL['Connection'] =  @mysql_connect($this->SQL['Settings']['SQL_Server'], $this->SQL['Settings']['SQL_User'], $this->SQL['Settings']['SQL_Password']);
			$this->SQL['DB'] 		 =	@mysql_select_db($this->SQL['Settings']['SQL_Database'], $this->SQL['Connection']);
			if ($this->SQL['Connection']) { //Catch any error that may occur during connection
				if ($this->SQL['DB']) {
					return $this->SQL['Connection'];
				}
				else {
					die('Database Connection Could Not Be Established - Could not specified find Database');	
				}
			}
			else {
				die('Error Connecting To The Database');
			}	
		}
	}

	/*
	/ Set the SQL settings.
	/ 
	*/
	public function set_SQL($SQL_User = '', $SQL_Password = '', $SQL_Server = '', $SQL_Database = '', $SQL_Table = '') {
		//let's set up the SQL Array to store the settings in.
		$this->SQL['Settings'] = array('SQL_User' => $SQL_User, 'SQL_Password' => $SQL_Password, 'SQL_Server' => $SQL_Server,'SQL_Database' => $SQL_Database, 'SQL_Table' => $SQL_Table);
		return true;

	}

	/*
	/ Close the MySQL session. Soon will be in __destruct().
	/ 
	*/
	public function unset_SQL() {
		//This function is used to unset the SQL array
		mysql_close($this->SQL['Connection']);
		unset($this->SQL);
		return true;
	}


	##############################
	# Login Methods              #
	##############################

	/*
	/ Set session variabls with specified user details
	/ 
	*/
	public function login($credentials_array = '', $session_time_seconds = 86400, $enc_password = false) {
		//Get the credentials in an array, also get the session time in seconds, and an encrypted password if set.
		//This function will return true if the username and password are correct. 

		//set the time that the cookie will expire.
		$expire = time() + $session_time_seconds;
		//grab the username
		$username = $credentials_array[0];

		//determine whether or not to use the username, or the enc_username
		if ($enc_password) {
			$password = $enc_password;
		} else {
			$password = md5(sha1($credentials_array[1]));
		}
		//get the check results,
		if ($this->check($username,$password)) {
			//they are logged in, so let's set their session up.
			$this->session($username, $password, $expire);
			return true;
		}
		else {
			//not able to be logged in. bad credentials
			return false;	
		}
	}

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
	public function manage_open_login() {
		//This function is in charge of finding out if a user is logged in after a session timeout, or a page click

		if (isset($_SESSION['nAuth']['Username']) && isset($_SESSION['nAuth']['Password'])) {
			//the sessions are set,
			//set the local variables for this function
			$username = $_SESSION['nAuth']['Username'] ;
			$password = $_SESSION['nAuth']['Password'];
			//see if it's all still correct and valid in the DB's
			if ($this->check($username, $password)) {
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
			if ($this->check($username, $password)) {
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

	/*
	/ Returns true if specified username exists.
	/ 
	*/
	private function check($enc_user, $enc_pass) {
		$enc_pass = mysql_real_escape_string($enc_pass);
		$enc_user = mysql_real_escape_string($enc_user);
		$query = "SELECT * FROM `" . $this->SQL['Settings']['SQL_Table'] . "` WHERE `username` = '$enc_user' AND `password` = '$enc_pass';";
		$query =  @mysql_query($query,$this->SQL['Connection']);
		$count =  @mysql_num_rows($query);
		if ($count == 1) {
			return true;
		}
		else {
			return false;	
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
		$value = mysql_real_escape_string($value);
		$sql = "UPDATE `" .$this->SQL['Settings']['SQL_Table'] . "` SET `$table_col` = '$value' WHERE id = $userID;";
		$query = @mysql_query($sql,$this->SQL['Connection']);
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
		$account = mysql_real_escape_string($userID);
		$query 	= @mysql_query("DELETE FROM `" . $this->SQL['Settings']['SQL_Table'] . "` WHERE `id` = '$account';", $this->SQL['Connection']);
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
		$username = mysql_real_escape_string($username);
		$sqlll = "SELECT * FROM `" . $this->SQL['Settings']['SQL_Table'] . "` WHERE `username` = '$username';";
		$query = @mysql_query($sqlll,$this->SQL['Connection']);
		$row	= @mysql_fetch_array($query);
		return $row['id'];
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
			$password 	= 	mysql_real_escape_string($password);
			$userID 	= 	mysql_real_escape_string($userID);

				//insert into the SQL Database.
			$sql = "UPDATE `" .$this->SQL['Settings']['SQL_Table'] . "` SET `password` = '$password' WHERE id = $userID;";
			$query = @mysql_query($sql,$this->SQL['Connection']);

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
	/ Registers a user with the specified data.
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
					$pass1 = mysql_real_escape_string($val);
				}
				else if ($key == $p2K) {
					$pass2 = mysql_real_escape_string($val);	
				}
				else if ($key == $uK) {
					$username = mysql_real_escape_string($val);	
				}
				if ($key != $p2K && $key != $p1K) {
					$vals_for_sql .= '\'' . mysql_real_escape_string($val) .'\', ';
				}
			}
			$sqll = "SELECT * FROM `" . $this->SQL['Settings']['SQL_Table'] . "` WHERE `username` = '$username';";
			$check_if_user = 	@mysql_query($sqll,$this->SQL['Connection']);
			$check_count	=	@mysql_num_rows($check_if_user);
			if ($check_count > 0) {
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
					$sql = "INSERT INTO `" . $this->SQL['Settings']['SQL_Table'] . "` ($heads_for_sql, `password`) VALUES ($vals_for_sql, '$password');";
					$query = @mysql_query($sql,$this->SQL['Connection']);
					if ($query) {
						return true;	
					}
					else {
						return 'SQL Insertion Failed'.mysql_error();
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
		$query = @mysql_query("SELECT * FROM `".  $this->SQL['Settings']['SQL_Table'] . "` WHERE `id` = $user ;", $this->SQL['Connection']);
		return	 @mysql_fetch_array($query);
	}

	/*
	/ Returns a users details by column.
	/ 
	*/
	public function getUserDetails($column, $user = false) {
		//Entry of a user ID is required. Use getIDFromUsername() to convert
		//user entry is not needed, it gets the current user instead.
		if (!$user) {
			$user = $this->getIDFromUsername($_SESSION['nAuth']['Username']);
		}
		$query = @mysql_query("SELECT `$column` FROM `".  $this->SQL['Settings']['SQL_Table'] . "` WHERE `id` = $user ;",$this->SQL['Connection']);
		$row = @mysql_fetch_array($query);
		return $row[$column];
	}

	###############################
	# Misc Other Methods  		  #
	###############################	

	/*
	/ Log's out the current user.
	/ 
	*/
	public function logout() {
		$this->destory_cookies();
		$this->destory_session();
		return true;
	}

	/*
	/ Destroys the nAuth session, used when logging out.
	/ 
	*/
	private function destory_session() {
		unset($_SESSION['nAuth']['Username']);
		unset($_SESSION['nAuth']['Password']);
		return true;
	}

	/*
	/ Destroys the nAuth cookie variables, used when logging out.
	/ 
	*/
	private function destory_cookies() {
		setcookie("Username","",0);
		setcookie("Password","",0);	
		return true;
	}
}
?>