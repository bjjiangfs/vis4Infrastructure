<?php
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;

$dataArray = array();
$linkArray = array();
function addPartToDataArray($onePart) {
	global $dataArray;
	$st = "{" . implode(",", $onePart) . "}";
	$dataArray[] = $st;
}

function addLinkToLinkArray($oneLink) {
	global $linkArray;
	$st = "{" . implode(",", $oneLink) . "}";
	$linkArray[] = $st;
}

function createLink($info) {
	$oneLink = array();
	foreach ($info as $k => $v) {

		$v = str_replace("\\", "\\\\", $v);
		$v = str_replace("\r", "", $v);
		$oneLink[] = '"' . $k . '":"' . $v . '"';
	}
	return $oneLink;
}

function getAppName($code_app) {
	global $db;
	$app = $db -> application -> findOne(array("code_application_mega" => $code_app));
	if ($app == null) {
		return $code_app;
	} else {
		return $app["name_application"];
	}
}

$distinct_sender_app = $db -> costream -> distinct("sender_code_app", array("team" => "prd-risques"));
$distinct_receiver_app = $db -> costream -> distinct("receiver_code_app", array("team" => "prd-risques"));

$distinct_app = array();

foreach ($distinct_sender_app as $key => $value) {
	if (!in_array($value, $distinct_app)) {
		$distinct_app[] = $value;
	}
}

foreach ($distinct_receiver_app as $key => $value) {
	if (!in_array($value, $distinct_app)) {
		$distinct_app[] = $value;
	}
}

foreach ($distinct_app as $key => $value) {
	$oneNode = array();
	$oneNode[] = '"key":"' . $value . '"';
	$oneNode[] = '"text":"' . getAppName($value) . '"';
	addPartToDataArray($oneNode);
}

$allFlux = $db -> costream -> find(array("team" => "prd-risques"));

$count = 0;

$megaLink = array();
foreach ($allFlux as $key => $value) {
	$sender = $value["sender_code_app"];
	$receiver = $value["receiver_code_app"];
	$id_flux = $sender . "***" . $receiver;
	if (in_array($id_flux, $megaLink))
		continue;

	$oneLink = createLink($value);
	$nb_links = $db -> costream -> find(array("sender_code_app" => $sender, "receiver_code_app" => $receiver)) -> count();
	if ($nb_links > 20) {
		$megaLink[] = $id_flux;
		$oneLink[] = '"nb_mega_link":'.$nb_links;
		$oneLink[] = '"thick":5';
	}

	$oneLink[] = '"from":"' . $sender . '"';
	$oneLink[] = '"to":"' . $receiver . '"';
	addLinkToLinkArray($oneLink);
}

$dataStringTotal = "[" . implode(",", $dataArray) . "]";
$linkStringTotal = "[" . implode(",", $linkArray) . "]";
$conn -> close();

$delimiter = "#*@+-";
echo $dataStringTotal . $delimiter . $linkStringTotal;
?>