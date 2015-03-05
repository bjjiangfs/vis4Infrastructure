<?php

//Remove the selected APP from express list
$category = $_GET["category"];
$name_app = $_GET["app"];
$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$collection = $db -> express;
$collection->remove(array("category"=>$category,"name_application"=>$name_app));
$conn -> close();
?>