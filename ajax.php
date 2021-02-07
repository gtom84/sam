<?php

	require "class.php";
	require "settings.php";
	
	$token = $_REQUEST["id"];
	
	$db = new Database;
	$db->db_connect;
	$sql = "SELECT * FROM `apps` WHERE `token` = '$token'";
	$sqlResult = $db->db_cmd($sql);
	while($obj = $sqlResult->fetch_object()){
		$jidlo_str = $obj->jidlo;
	}
	$jidlo_arr = unserialize($jidlo_str);
	$day = date("D");
	$hour = date("H");
	if($hour > 7 AND $hour < 11){ $i2 = 0; }
	if($hour >= 11 AND $hour < 15){ $i2 = 1; }
	if($hour >= 17 AND $hour < 20){ $i2 = 2; }
	switch($day){
		case "Thu":
			$i1 = 0;
		break;
		case "Fri":
			$i1 = 1;
		break;
		case "Sat":
			$i1 = 2;
		break;
		case "Sun":
			$i1 = 3;
		break;			
	}
	$i = $i1.".".$i2;

	if($jidlo_arr[$i] == "on"){ echo "OK!"; } else { echo "NO!"; }

	/*
		Connect to WP database, get data (column jidlo) for scanned token.
		Check if individual payed for this meal and return response.
		
			CT	PA	SO	NE
		S		x	x	x
		O		x	x	
		V	x	x	x 

		CT	0.3	Vecere	
		PA	1.0	Snidane
		PA	1.1	Obed
		PA	1.2	Vecere
		SO	2.0	Snidane
		SO	2.1	Obed
		SO	2.2	Vecere
		NE	3.0	Snidane	
		
	*/

?>
