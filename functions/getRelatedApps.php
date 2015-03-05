<?php
$name_app = $_GET["app"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$app_module = $db -> app_module;
$app_parent = $app_module -> find(array("name_application" => $name_app));
$app_children = $app_module -> find(array("name_application_parent" => $name_app));

$message = "";
if ($app_children -> count() != 0) {
	
	$message .= '<span class="dropdown">
		  <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
		    Sous-modules
		    <span class="caret"></span>
		  </button>
	<ul class="dropdown-menu" role="menu">';
	foreach ($app_children as $key => $value) {
		$message .= '<li role="presentation"><a role="menuitem" tabindex="-1" href="architecture.php?name_application='.urlencode($value["name_application"]).'">' . $value["name_application"] . '</a></li>';
	}
	$message .= '</ul></span>';

}

if ($app_parent -> count() != 0) {
	$message .= '<span class="dropdown">
		  <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
		    Module Parent
		    <span class="caret"></span>
		  </button>
	<ul class="dropdown-menu" role="menu">';
	foreach ($app_parent as $key => $value) {
		$message .= '<li role="presentation"><a role="menuitem" tabindex="-1" href="architecture.php?name_application='.urlencode($value["name_application_parent"]).'">' . $value["name_application_parent"] . '</a></li>';
	}
	$message .= '</ul></span>';
}

echo $message;
?>