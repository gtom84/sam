<?php

	class Database {
		public $mysqli;
		function db_connect(){
			$this->$mysqli = new mysqli("localhost",DB_USERNAME,DB_PASSWORD,DB_NAME);
			if ($this->$mysqli -> connect_errno) {
			  echo "Failed to connect to MySQL: " . $this->$mysqli -> connect_error;
			  exit();
			}
			mysqli_set_charset($this->$mysqli,"UTF8");
		}
		function db_cmd($sql){
			$this->db_connect();
			$sqlResult = mysqli_query($this->$mysqli,$sql);	
			return $sqlResult;			
		}		
	}
		
?>
