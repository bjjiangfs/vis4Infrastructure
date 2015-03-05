<?php
$name_app = $_GET["app"];
$conn = new MongoClient('mongodb://localhost');
$collection = $conn -> COCKPIT -> function;
$conn -> close();
$app_module = $conn -> COCKPIT -> app_module;

$allModulesOfApp = $app_module->distinct("name_application",array("name_application_parent"=>$name_app));
$allModulesOfApp[] = $name_app;
/*-----DISTINCT SITE-----*/
$cursor_site = $collection -> distinct("site", array("name_department" => "prd-risques", "name_application" => array('$in'=>$allModulesOfApp)));

if (count($cursor_site) != 0) {
	foreach ($cursor_site as $key => $value) {
		$array_site[] = $value;
	}
	echo count($array_site) . "***";
	echo implode("***", $array_site);
} else {
	echo 0;
}
?>