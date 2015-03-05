<?php
$name_app = $_GET["app"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$collection = $db -> application;
$cursor = $collection -> distinct("name_application",array("name_department" => "prd-risques"));
$conn->close();
$applications = array();
foreach ($cursor as $key => $value) {
	$applications[] = $value;
}
echo json_encode($applications);
?>