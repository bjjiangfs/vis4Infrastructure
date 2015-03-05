<?php

// Connect to DB
$name_app = $_GET["app"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$collection = $db -> links;

/*-----GET ALL LINKS OF THE APP-----*/
$cursor = $collection -> find(array("name_application" => $name_app));
$conn -> close();

$links = array();
foreach ($cursor as $key => $value) {
	$links[] = '{"from":"' . $value["from"] . '","to":"' . $value["to"] . '","text":"'.$value["text"].'"}';
}
$stringTotal = "[" . implode(",", $links) . "]";
echo $stringTotal;
?>