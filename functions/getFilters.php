<?php
$name_app = $_GET["app"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$app_module = $db -> app_module;
$allModulesOfApp = $app_module->distinct("name_application",array("name_application_parent"=>$name_app));
$allModulesOfApp[] = $name_app;
$collection = $db -> function;

/*-----DISTINCT SITE-----*/
$cursor_site = $collection -> distinct("site", array("name_department" => "prd-risques", "name_application" => array('$in'=>$allModulesOfApp)));

/*-----DISTINCT ENV-----*/
$cursor_env = $collection -> distinct("name_environment", array("name_department" => "prd-risques", "name_application" => array('$in'=>$allModulesOfApp)));
$conn -> close();

$box_site = "<span style='font-size: 14px;'><strong>Site : </strong></span>";
$checkbox_env = "<span style='font-size: 14px;'><strong>Environnement : </strong></span>";

if (count($cursor_site) != 0) {

	foreach ($cursor_site as $key => $value) {
		$box_site .= "<span class='checkbox'>" . $value . "</span>";
	}
	$box_site .= '';
}

$box_os = "<span style='font-size: 14px;'><strong>OS : </strong></span>";
$color_os = array("Windows"=>"#D1F0FF","Linux"=>"#C299FF","Solaris"=>"#FFA3A3","Vmware"=>"#DBFFDB");
foreach ($color_os as $key => $value) {
	$box_os .= "<span style='background-color:" .$value . "'>".$key."</span>";
}



$color_env = array("Dev" => "#FFB3FF", "Rec" => "#CCCCB2", "Qualif" => "lightBlue", "Bench" => "orange", "Prod" => "#86CB8F");
if (count($cursor_env) != 0) {
	//sort env by order
	$env_sorted = array();
	if (in_array("Dev", $cursor_env)) {
		$checkbox_env .= "<div class='checkbox' style='border-radius:5px;border:2px solid " . $color_env["Dev"] . ";border-top-width:5px'><label><input type='checkbox' onclick=filterChanged() value='Dev'>Dev</label></div>";
	}
	if (in_array("Rec", $cursor_env)) {
		$checkbox_env .= "<div class='checkbox' style='border-radius:5px;border:2px solid " . $color_env["Rec"] . ";border-top-width:5px'><label><input type='checkbox' onclick=filterChanged() value='Rec'>Rec</label></div>";
	}
	if (in_array("Qualif", $cursor_env)) {
		$checkbox_env .= "<div class='checkbox' style='border-radius:5px;border:2px solid " . $color_env["Qualif"] . ";border-top-width:5px'><label><input type='checkbox' onclick=filterChanged() value='Qualif'>Qualif</label></div>";
	}
	if (in_array("Bench", $cursor_env)) {
		$checkbox_env .= "<div class='checkbox' style='border-radius:5px;border:2px solid " . $color_env["Bench"] . ";border-top-width:5px'><label><input type='checkbox' onclick=filterChanged() value='Bench'>Bench</label></div>";
	}
	if (in_array("Prod", $cursor_env)) {
		$checkbox_env .= "<div class='checkbox' style='border-radius:5px;border:2px solid " . $color_env["Prod"] . ";border-top-width:5px'><label><input type='checkbox' onclick=filterChanged() checked value='Prod'>Prod</label></div>";
	}

	//Prod Only Button goes here
	$checkbox_env .= "&nbsp;&nbsp;<a onclick=prodOnly() class='btn btn-default btn-sm'><span class='glyphicon glyphicon-thumbs-up'></span>
   	Prod Only</a>";
	//Select All Button goes here
	$checkbox_env .= "&nbsp;&nbsp;<a onclick=selectAll() class='btn btn-default btn-sm'><span class='glyphicon glyphicon-th-large'></span>
   	Select All</a>";
}

$delimiter1 = "***";
echo $box_site .$box_os. $delimiter1 . $checkbox_env;
?>