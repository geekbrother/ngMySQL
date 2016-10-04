<?php

class mysqlbinder {
/* 
Global variables:
*/
// mysql connection
protected $host, $port, $user, $password, $db, $charset;
// Mysqli connector
protected $mysqli;
// Table global variable
public $table;
// Debug. Show debug information in error message.
public $debug = false;
// Json input from php://input
public $json_in;

/* 
Constants:
*/
// Expressions for allowed keys
const KEYS_EXPR = '/[^a-zA-Z0-9_]+/';

	// Constructor
	function __construct(
		$host     ='localhost', 
		$user     ='nouser', 
		$password ='nopassword', 
		$db       ='nodb', 
		$table    ="notable", 
		$port     =3306,
		$charset  ="utf8"
	) {
		// Setting mysql connect variables
		$this->host     =$host;
		$this->port     =$port;
		$this->user     =$user;
		$this->password =$password;
		$this->db       =$db;
		$this->charset  =$charset;
		// Default table if set
		$this->table    =$table;
		
		// Trying to connect to mysql
		$this->connect();
	}

/** 
 * 	PRIVATE METHODS
 */
	
	// Connect to Mysql Server and get json from input
	private function connect() {
		// Trying to connect ot mysql
		$this->mysqli = new mysqli($this->host, $this->user, $this->password, $this->db);
		if (mysqli_connect_errno()) {
		    printf("Cant connect to mysql: %s\n", mysqli_connect_error());
		    exit();
		}
		$mysqli=&$this->mysqli;
		$mysqli->set_charset($this->charset);
		// Getting JSON from input and push to var
		$this->json_in = file_get_contents('php://input');
	}

	// Mysql prepared statement execute
	// return last_insert_id
	protected function ps_execute($sql_query, $bind_params){
		// mysql link
		$mysqli=&$this->mysqli;

		$stmt = $mysqli->prepare($sql_query);
		if($stmt === false) $this->send_error('mysql', $mysqli->errno.':'.$mysqli->error );
		// mysqli_bind values
		call_user_func_array(array($stmt, 'bind_param'), $this->refValues($bind_params) );

		if (!$stmt->execute()) $this->send_error('mysql', $stmt->error);
		return $mysqli->insert_id;
	}
	
	// Send error in JSON format and stop execution
	/*
	$code - Error code
	$description - Error description
	 */
	protected function send_error($code, $description){
		header('HTTP/1.1 406 Not Acceptable');
		
		if ($this->debug)
			$out = ['error' => $code, 'description' => $description];
		else
			$out = ['error' => $code];

		die(json_encode($out));
	}

	public function send_success(){
		echo '{"success":"ok"}';
	}

	// Get Columns names from table
	// return array of columns names
	protected function getCols(){
		$mysqli=&$this->mysqli;
		$cols=array();

		$result = $mysqli->query('SHOW COLUMNS FROM '.$this->table);
			while ($myrow = $result->fetch_array(MYSQLI_ASSOC)) {
				$cols[]=$myrow['Field'];
			};
		return $cols;
	}

	// Comma separated params to array
	protected function commaToArray($string){
		$array=array();
		if ($string !== ''){
			$string1=preg_replace('/\s+/', '', $string);
			$array=explode(',', $string1);
		}
		return $array;
	}

	// Comma separated key=value to array
	protected function kvToArray($string){
		$return_array=array();
		if ($string !== ''){
			$array=explode(',', $string);
				foreach ($array as $v) {
					$a=explode('=', $v);
					$return_array[$a[0]]=$a[1];
				}
		}
		return $return_array;
	}

	// Json Decoder
	/*
	Return decoded array or send erorr in json format
	[{error:'code',description:''}]
	 */
	protected function json_decode(&$json_in){
		$obj = json_decode($json_in,true);
		if(json_last_error() != JSON_ERROR_NONE)
	        $this->send_error("JSON_ERROR PARSE CODE", json_last_error());
		return $obj;
	}

	// Check type of var for mysql_bind
	// return type string: i,d or s
	protected function bind_type($var){
		if(is_numeric($var)){
			if(is_integer($var)){
				return 'i';
			}else return 'd';
		}else return 's';
	}

	// Make array from values to links to values
	protected function refValues($arr){
		if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+ 
	    { 
	        $refs = array(); 
	        foreach($arr as $key => $value) 
	            $refs[$key] = &$arr[$key]; 
	        return $refs; 
	    } 
    return $arr; 
	} 

	// Check array is multidemensional or not
	protected function is_multi($array){
		if (count($array) == count($array, COUNT_RECURSIVE))
			return false;
		else
			return true;
	}

/** 
 *  PUBLIC METHODS
 */
	public function json_concat(){
		$out=array();
		$json='';
		foreach (func_get_args() as $json_concats) {
			if (!is_array($json)) $json=$this->json_decode($json_concats);
			else
			$json = array_merge_recursive($json[0], $this->json_decode($json_concats)[0] );
		}
		$out[]=$json;
		return json_encode($out);
	}
	/**
	 * Json values hardering. Replace values by regular expressions. Stop execution or replace with ''
	 * @param  array  $keys_exps  [Array with keys and expressions to them. Always use negative expressions. Examples:
	 *                            array('*'=>'/[^0-9]/') 
	 *                            This apply only digits for all keys.
	 *                            array('id'=>'/[^0-9]/', '*'=>'/[^a-zA-Z0-9]+/') 
	 *                            This apply only digits for id key and only alphabets and digits for all another keys. ]
	 * @param  boolean $dowestop  [Do we stop or just remove forbidden chars]
	 * @param  string  $replacer  [Replace with '']
	 * @return string             [Return hardered JSON string]
	 */
	public function values_hardering($keys_exps, $dowestop=true, $replacer=''){

		// mysql link
		$mysqli=&$this->mysqli;
		// json decode
		$obj = $this->json_decode($this->json_in);
		// if we have multi array or single array
		$is_multi = $this->is_multi($obj);
		// Multi array json
		for ($i = 0; $i < count($obj); ++$i ) {
			// check for multi arrays or single array and pass new listobj;
			if ($is_multi) $listobj = &$obj[$i]; else $listobj = &$obj;
			// Looping the values from JSON array
			while (list($key, $value) = each($listobj)) {

				$key=$mysqli->real_escape_string(stripslashes(preg_replace(self::KEYS_EXPR, '', $key)));

				if ( isset($keys_exps[$key]) ){
					$exp = $keys_exps[$key];
				}elseif( isset($keys_exps['*']) ){
					$exp = $keys_exps['*'];
				}
				// Do we stop or just replace
				if ($dowestop){
					if( preg_match($exp, $value) ) $this->send_error('Invalid value','Value hardering error for key: '.$key.'. Hardering expression is: '.$exp);
				}else{ 
					$listobj[$key] = preg_replace($exp, $replacer, $value);
				}
			}
			// Break FOR if we have not multi dimensiona array
			if (!$is_multi) break;
		}
		$json=json_encode($obj);
		return json_encode($obj);
	}

	/**
	 * Selecting records from mysql table and return them in JSON format
	 * @param  string $where_to_get [this is actualy WHERE clause, ALL records by default]
	 * @param  string $cols_to_show [what columns to select, * or ALL collumns by default,
	 *                              ! prepend for NOT show cols. Example: !password,hash
	 *                              will no show password and hash columns.]
	 * @param  string $order_by		[Order by key. Example: id DESC (or ASC)]
	 * @return string $json_output  [JSON string with results]
	 */
	public function select_json($where_to_get='', $cols_to_show="*", $order_by='') {
		// Setting where_to_get to 1 if null passed
		if($where_to_get==='') $where_to_get='1';

		$order_by = ($order_by==='') ? '' : 'ORDER BY '.$order_by  ;
		// Get records from database in JSON format
		$mysqli=&$this->mysqli;

		// Check for ! not show columns.
		if (substr($cols_to_show,0,1) === '!'){
			// Copy of cols_to_show variable to manipulate
			// parsing cols to array
			$cols_to_parse = $this->commaToArray($cols_to_show);
			// Clear $cols_to_show becuse we shall add to it only wanted cols
			$cols_to_show='';
			// geting all cols from table
			$cols=$this->getCols();
			// find unwanted cols
			foreach ($cols as &$col) {
			    if(!in_array($col, $cols_to_parse)){
			    	if(strlen($cols_to_show) > 1) $cols_to_show .= ','; // just add comma separator
			    	$cols_to_show .= $col;
			    }
			}
		}

		// Getting results from mysql
		if ($result = $mysqli->query("SELECT $cols_to_show FROM ".$this->table." WHERE $where_to_get ".$order_by.";")) {
				$json_output="[";
			    	while ($myrow = $result->fetch_array(MYSQLI_ASSOC)){
			    		
			    		if (strlen($json_output) > 2) $json_output .= ','; // just add comma separator
			    		$json_output .= json_encode($myrow);
			    	}
		    	$json_output .= "]";
		    	$result->close();

		return $json_output;
		}else{
			printf("Error during select_json: %s\n", $mysqli->error);
			exit();
		}

	}

	/**
	 * Update table from json
	 * @param  string $updkeys        [Which keys from JSON we will use as keys in WHERE sql statement. Comma separated.
	 *                                Example: id,title]
	 * @param  string $protected_cols [Table columns which cannot be modified from JSON data. We'll just igonre them in sql.
	 *                                Comma separated. Example: userid,password,cost ]
	 * @param  string $add_keys       [Additional keys which will be passed to sql WHERE statement. 
	 *                                Example: userid=123,titile="test user"]
	 * @param  string $add_values     [Additional values wich will be added to table (SET statement). 
	 *                                Example: cost=120,created=2015-01-01 ]
	 * @return boolean                [Return true if success or generate error]
	 */
	public function update_json($updkeys, $protected_cols='', $add_keys='', $add_values='') {

		// mysql link
		$mysqli         =&$this->mysqli;
		// parse update keys
		$updkeys        =$this->commaToArray($updkeys);
		// parse additional keys
		$add_keys       =$this->kvToArray($add_keys);
		// parse protected cols
		$protected_cols =$this->commaToArray($protected_cols);
		// parse additional values
		$add_values     =$this->kvToArray($add_values);
		
		// decode json
		$obj            = $this->json_decode($this->json_in);
		// if we have multi array or single array
		$is_multi       = $this->is_multi($obj);

		// Multi array json (if we have multi strings to add)
		for ($i = 0; $i < count($obj); ++$i ) {
			// check for multi arrays or single array and pass new listobj;
			if ($is_multi) $listobj = &$obj[$i]; else $listobj = &$obj;

			// Keys variables
			$keys=""; // keys string for where statement
			$keys_values= array(); // keys values array
			$where=""; // where string statement

			// Values variables
			$param_types=''; // mysqli prepared statement param types for values [i,d,s]

			$a_params = array(); // total array for send to mysqli_bind

			// Looping the values from JSON array
			while (list($key, $value) = each($listobj)) {

				// Security escaping
				$key=$mysqli->real_escape_string(stripslashes(preg_replace(self::KEYS_EXPR, "", $key)));
				$listobj[$key] = $value = $mysqli->real_escape_string($value);
				// Getting protected columns and make cheks
				if ( !in_array($key, $protected_cols) 
					AND !in_array($key, $updkeys) 
					AND !in_array($key, $add_keys) 
					AND !in_array($key, $add_values)
					) {
					// Adding keys
					$keys .= (strlen($keys)>0) ? ', '.$key.'=?' : $key.'=?';
					// Adding values
					$a_params[] = $value;
					// Adding param type [i d or s]
					$param_types .= $this->bind_type($value);
				}
			}

			// Looping the values from add_values added from parameters
			while (list($key, $value) = each($add_values)) {
				// Adding keys
				$keys .= (strlen($keys)>0) ? ', '.$key.'=?' : $key.'=?';
				// Adding values
				$a_params[] = $value;
				// Adding param type [i d or s]
				$param_types .= $this->bind_type($value);
			}

			// Looping the WHERE keys from upd_keys where values get from the JSON
			foreach ($updkeys as $key) {
				// Adding where sql state
				$where .= (strlen($where)>0) ? ' AND '.$key.'=?' : $key.'=?';
				// Adding values
				$a_params[] = $listobj[$key];
				// Adding param type [i d or s]
				$param_types .= $this->bind_type($listobj[$key]);
			}

			// Looping the WHERE keys from $add_keys added from parameters
			while (list($key, $value) = each($add_keys)) {
				// Adding where sql state
				$where .= (strlen($where)>0) ? ' AND '.$key.'=?' : $key.'=?';
				// Adding values
				$a_params[] = $value;
				// Adding param type [i d or s]
				$param_types .= $this->bind_type($value);
			}

			// push bind types at start of array
			array_unshift($a_params, $param_types);

			// make MySql query
			$last_id = $this->ps_execute('UPDATE '.$this->table.' SET '.$keys.' WHERE '.$where, $a_params);	

			// Break FOR if we have not multi dimensiona array
			if (!$is_multi) break;
		} // end for each

		return true;
	}

	/**
	 * Insert record into MYSQL table from JSON data
	 * @param  string $protected_cols [Columns protected for inserting. 
	 *                                Cannot insert to this columns from JSON.
	 *                                Passed as array separated by comma.
	 *                                Example id,password ]
	 * @param strin $add_value  [Additional values to insert. 
	 *                          	  Example: account=892,text='help']
	 * @return array 			[Array of IDs of inserted records]
	 */
	public function insert_json( $protected_cols='', $add_values='' ){
		// Return last_insert id's arrays
		$last_ids       = array();
		
		// mysql link
		$mysqli         =&$this->mysqli;
		// parse protected cols
		$protected_cols =$this->commaToArray($protected_cols);
		// parse add values
		$add_values     = $this->kvToArray($add_values);
		// Decode json
		$obj            = $this->json_decode($this->json_in);
		// if we have multi array or single array
		$is_multi       = $this->is_multi($obj);

		// Multi array json (if we have multi strings to add)
		for ($i = 0; $i < count($obj); ++$i ) {
			// check for multi arrays or single array and pass new listobj;
			if ($is_multi) $listobj = &$obj[$i]; else $listobj = &$obj;

			$keys=''; // keys array
			$param_types=''; // mysqli prepared statement param types [ids]
			$a_params = array();
			$qs=''; // Just ?,? for VALUES in SQL

			// looping the keys and values from JSON array
			while (list($key, $value) = each($listobj)) {

				// Security escaping
				$key=$mysqli->real_escape_string(stripslashes(preg_replace(self::KEYS_EXPR, "", $key)));
				$value=$mysqli->real_escape_string($value);

				// Getting protected columns and make cheks
				if (in_array($key, $protected_cols)) {
					//! print 'Trying to insert into protected cols, escaping.';
				}elseif(isset($add_values[$key])){
					//! print 'Trying insert into addional value, skiping.';
				}else{
					// Adding keys
					$keys.= (strlen($keys)>0) ? ','.$key : $key ;
					// Adding values
					$a_params[] = $value;
					// Adding param type [i d or s]
					$param_types .= $this->bind_type($value);
					// Adding ? for sql query
					$qs .= (strlen($qs) > 0) ? ',?' : '?';
				}
			}

			// Checking for additional values and append them
			while (list($key, $value) = each($add_values)) {
				// Adding keys
					$keys.= (strlen($keys)>0) ? ','.$key : $key ;
					// Adding values
					$a_params[] = $value;
					// Adding param type [i d or s]
					$param_types .= $this->bind_type($value);
					// Adding ? for sql query
					$qs .= (strlen($qs) > 0) ? ',?' : '?';
			}

			// push bind types at start of array
			array_unshift($a_params, $param_types);
			$last_ids[] = $this->ps_execute(
				'INSERT INTO '.$this->table.' ('.$keys.') VALUES ('.$qs.')', 
				$this->refValues($a_params));

			// Break FOR if we have not multi dimensiona array
			if (!$is_multi) break;
		} //end for
		return $last_ids;
	} // end method

	/**
	 * Delete from table by key in json
	 * @param  string $delkeys  [Keys from JSON data which values will be used as keys for delete. 
	 *                          Comma separated. Example: id, userid]
	 * @param  string $add_keys [Additional keys and values which will be added to WHERE statement. 
	 *                          Example: id=123,userid=10982]
	 * @return boolean          [Return true if success]
	 */
	public function delete_json( $delkeys='', $add_keys='' ){
		// mysql link
		$mysqli         =&$this->mysqli;
		// parse update keys
		$delkeys      	    =$this->commaToArray($delkeys);
		// parse additional keys
		$add_keys       =$this->kvToArray($add_keys);
		// decode json
		$obj            = $this->json_decode($this->json_in);
		// if we have multi array or single array
		$is_multi       = $this->is_multi($obj);

		// Multi array json (if we have multi strings to add)
		for ($i = 0; $i < count($obj); ++$i ) {
			// check for multi arrays or single array and pass new listobj;
			if ($is_multi) $listobj = &$obj[$i]; else $listobj = &$obj;

			// Keys variables
			$keys=""; // keys string for where statement
			$keys_values= array(); // keys values array
			$where=""; // where string statement

			// Values variables
			$param_types=''; // mysqli prepared statement param types for values [i,d,s]

			$a_params = array(); // total array for send to mysqli_bind

			// looping the keys and values from JSON array
			while (list($key, $value) = each($listobj)) {
				// Security escaping
				$key=$mysqli->real_escape_string(stripslashes(preg_replace(self::KEYS_EXPR, "", $key)));
				$listobj[$key] = $value = $mysqli->real_escape_string($value);
			}

			// Looping the WHERE keys from keys where values get from the JSON
			foreach ($delkeys as $key) {
				// Adding where sql state
				$where .= (strlen($where)>0) ? ' AND '.$key.'=?' : $key.'=?';
				// Adding values
				$a_params[] = $listobj[$key];
				// Adding param type [i d or s]
				$param_types .= $this->bind_type($listobj[$key]);
			}

			// Looping the WHERE keys from $add_keys added from parameters
			while (list($key, $value) = each($add_keys)) {
				// Adding where sql state
				$where .= (strlen($where)>0) ? ' AND '.$key.'=?' : $key.'=?';
				// Adding values
				$a_params[] = $value;
				// Adding param type [i d or s]
				$param_types .= $this->bind_type($value);
			}

			// push bind types at start of array
			array_unshift($a_params, $param_types);
			// make MySql query
			$last_id = $this->ps_execute('DELETE FROM '.$this->table.' WHERE '.$where, $a_params);	

			// Break FOR if we have not multi dimensiona array
			if (!$is_multi) break;
		} // end for each

		return true;
	} // end method
}
