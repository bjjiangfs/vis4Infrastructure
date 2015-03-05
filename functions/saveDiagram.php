<?php
$name_app = $_POST["app"];
$dataToSave = $_POST["dataToSave"];
$type = $_POST["type"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;

//echo ($dataToSave);

if (get_magic_quotes_gpc())  
 $dataToSave =  stripslashes($dataToSave);
if ($type == "log") {
	$collection = $db -> diagram_log;
} else {
	$collection = $db -> diagram_geo;
}

$collection->update(array("name_application" => $name_app),
					array("name_application" => $name_app,"data"=>json_decode($dataToSave)),
					array("upsert"=>1));

$conn->close();
?>