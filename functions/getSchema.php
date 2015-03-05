<?php
$name_app = $_POST["app"];
$type = $_POST["type"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;


if ($type == "log") {
	$col = $db -> diagram_log;
	$result = $col -> findOne(array("name_application" => $name_app));
} elseif ($type == "geo") {
	$site = $_POST["site"];
	$col = $db -> diagram_geo;
	$result = $col -> findOne(array("name_application" => $name_app, "site" => $site));
}

$env = $_POST["env"];
if (isset($env)) {
	$env_list = json_decode(stripslashes($env));
	$data_filtered = array();
	foreach ($result["data"] as $k => $v) {
		if (in_array($v["name_environment"], $env_list) || $v["category"] == "function") {
			$data_filtered[] = $v;
		}
	}
	echo json_encode($data_filtered);
} else {

	echo json_encode($result["data"]);
}
$conn -> close();
?>