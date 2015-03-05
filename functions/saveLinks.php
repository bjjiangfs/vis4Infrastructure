<?php
$data = $_GET["data"];
$app = $_GET["app"];

$links = json_decode(str_replace("\\", "", $data), true);

$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$collection = $db -> links;

//First clear all existing links of the app
$collection -> remove(array("name_application" => $app));
//Then insert links
foreach ($links as $key => $value) {
	if ($value["text"] == null)
		$value["text"] = "...";
	$collection -> insert(array("name_application" => $app, "from" => $value["from"], "to" => $value["to"], "text" => $value["text"]));
}
?>