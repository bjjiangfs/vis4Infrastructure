<?php
function createModelSigle() {
	$conn = new MongoClient('mongodb://localhost');
	$db = $conn -> COCKPIT;
	$sigles = $db -> model_sigle;
	$sigles -> insert(array("name_model" => "SERVEUR PHYSIQUE", "sigle" => "SP"));
	$sigles -> insert(array("name_model" => "DOMAIN", "sigle" => "DM"));
	$sigles -> insert(array("name_model" => "SERVEUR LOGIQUE", "sigle" => "SL"));
	$sigles -> insert(array("name_model" => "MACHINE VIRTUELLE", "sigle" => "MV"));
	$sigles -> insert(array("name_model" => "PARTITION", "sigle" => "PART"));
	$conn -> close();
}
?>