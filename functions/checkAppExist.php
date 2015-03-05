<?php
$name_app = $_POST["app"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$collection = $db -> application;
$apps = $collection -> distinct("name_application",array("name_department" => "prd-risques"));
$conn->close();

if(in_array($name_app, $apps)){
	echo true;
}else{
	echo false;
}
?>