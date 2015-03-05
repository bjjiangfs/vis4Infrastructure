<?php
// Save application_name of applications of prd-riques as well as all sub modules of those applications
function createAppModule() {
	$conn = new MongoClient('mongodb://localhost');
	$db = $conn -> COCKPIT;
	$collection = $db -> application;
	$cursor = $collection -> find(array("name_department" => "prd-risques"));
	//get all application of prd-risques
	$conn -> close();

	foreach ($cursor as $key => $value) {
		if ($value["name_application_parent"] != "") {
			$db -> app_module -> insert(array("name_application_parent" => $value["name_application_parent"], "name_application" => $value["name_application"]));
		}
	}

}
?>