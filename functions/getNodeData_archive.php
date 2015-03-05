<?php

//	Create a node with all its information in JSON format
function createNode($info_machine, $key) {
	$oneNode = array();
	foreach ($info_machine as $k => $v) {
		if ($k == "_id") {
			$k = "key";
			$v = $key;
		}
		$oneNode[] = '"' . $k . '":"' . $v . '"';
		//JSON format
	}
	return $oneNode;
}

// color for a node
function getNodeColor($name_env) {
	if ($name_env == "Dev") {
		return '"color":"#FFACA8"';
	} elseif ($name_env == "Rec") {
		return '"color":"#FFD1A8"';
	} elseif ($name_env == "Qualif") {
		return '"color":"lightBlue"';
	} elseif ($name_env == "Bench") {
		return '"color":"orange"';
	} elseif ($name_env == "Prod") {
		return '"color":"#86CB8F"';
	}
}

// get the sigle if there is any , otherwise use its original name
function getMachineModelName($name_model, $sigle_model) {
	if (array_key_exists($name_model, $sigle_model))
		return $sigle_model[$name_model];
	else
		return $name_model;
}

// get the sum of an array
function getSum($myArray) {
	if (!is_array($myArray))
		return 0;

	$sum = 0;
	foreach ($myArray as $value) {
		$sum += $value;
	}
	return $sum;
}

try {

	//get the name of current application
	$name_app = $_GET["app"];
	//get the site, it may be "all" which means the schema logique, or the name of the site like "VEGA" or "SIRIUS"
	$site = $_GET["site"];

	// Connect to DB
	$conn = new Mongo('slpafrpr2adm1');
	$db = $conn -> COCKPIT;
	$col_function = $db -> function;

	//every machine of the current application
	$machines = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app));
	$dbms = $db -> dbms;
	$app_module = $db -> app_module;
	$conn -> close();

	$vmWare = array("Vmware ESX", "VMware ESXi");
	$machinePhysique = array("LAME", "SERVEUR PHYSIQUE", "DOMAIN");
	$machineLogique = array("SERVEUR LOGIQUE", "ZONE", "LOGICAL DOMAIN", "MACHINE VIRTUELLE", "PARTITION");
	$chiffre_db = array( array());

	$oneNode = array();
	$dataArray = array();

	$sigle_model = array();

	// all model sigles are saved in collection "model_sigle"
	$model_sigle = $db -> model_sigle -> find();
	foreach ($model_sigle as $key => $value) {
		$sigle_model[$value["name_model"]] = $value["sigle"];
	}

	$diff_db = array();
	$diff_note = array();
	/*------MACHINE NODE--------*/
	foreach ($machines as $key => $value) {

		if ($site == $value["site"] || $site == "all") {
			$name_model = $value["name_model"];
			if (in_array($name_model, $machinePhysique)) {//if this machine is a machine physique

				if ($site == "all" && in_array($value["rewrited_type"], $vmWare))
					continue;
				if ($site != "all" && in_array($value["rewrited_type"], $vmWare)) {
					if ($nb_log == 0)
						continue;
				}

				$nb_log = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "server_parent" => $value["name_machine"], "name_function" => $value["name_function"], "name_subfunction" => $value["name_subfunction"])) -> count();

				$concat_fun_subfun = $value["name_function"] . "***" . $value["name_subfunction"];
				$key_phy = $concat_fun_subfun . "***" . $value["name_machine"];

				$oneNode = createNode($value, $key_phy);

				$oneNode[] = '"group":"' . $concat_fun_subfun . '"';
				$oneNode[] = '"isGroup":true';
				$oneNode[] = getNodeColor($value["name_environment"]);

				//nb de machine log appartenant à la machine phy
				$nb_log = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "server_parent" => $value["name_machine"], "name_function" => $value["name_function"], "name_subfunction" => $value["name_subfunction"])) -> count();

				//Set text of non DB
				if ($value["name_function"] != "DATABASE") {
					if ($nb_log == 0) {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ')  ' . $value["rewrited_type"] . '"';
					} else {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ')[' . $nb_log . ' log]  ' . $value["rewrited_type"] . '"';
					}
				}

				if ($value["name_function"] == "DATABASE") {
					//direct db + indirect db
					$database_in_phy = $dbms -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_machine_physical" => $value["name_machine"]));
					//direct db
					$database = $dbms -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_machine_logical" => $value["name_machine"]));

					//set text of DB
					$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ') [' . $nb_log . ' log ' . $database_in_phy -> count() . ' db]  ' . $value["rewrited_type"] . '"';

					$oneDB = array();
					if ($database -> count() != 0) {//if serveur physique contains direct db

						if (isset($chiffre_db[$value["name_function"]][$value["name_subfunction"]])) {
							$chiffre_db[$value["name_function"]][$value["name_subfunction"]] += $database -> count();
						} else {
							$chiffre_db[$value["name_function"]][$value["name_subfunction"]] = $database -> count();
						}
						foreach ($database as $k => $v) {

							$key_db = $key_phy . "***" . $v["_id"];
							if (!in_array($key_db, $diff_db)) {
								$oneDB = createNode($v, $key_db);
								$oneDB[] = '"isGroup":false';
								$oneDB[] = '"group":"' . $key_phy . '"';
								$text_db = $v["name"] . ' ' . $v["instname"] . '\n' . $v["techno"];
								$oneDB[] = '"text":"' . $text_db . '"';
								$oneDB[] = '"category":"db"';
								$st = "{" . implode(",", $oneDB) . "}";
								$dataArray[] = $st;
								$diff_db[] = $key_db;
							}
						}
					}
				}
				$st = "{" . implode(",", $oneNode) . "}";
				$dataArray[] = $st;

				if (!in_array($key_phy . "***note", $diff_note)) {
					$relatedApps = array();
					$notRelatedApps = array();
					$textServerNote = array();
					$serverNoteData = $col_function -> distinct("name_application", array("name_machine" => $value["name_machine"], "name_department" => "prd-risques"));
					$diff_note[] = $key_phy . "***note";
					foreach ($serverNoteData as $key => $value) {
						$nb_parent = $app_module -> find(array("name_application" => $name_app, "name_application_parent" => $value)) -> count();
						$nb_children = $app_module -> find(array("name_application_parent" => $name_app, "name_application" => $value)) -> count();
						if ($name_app != $value) {
							if ($nb_children || $nb_parent) {
								$relatedApps[] = $value;
							} else {
								$notRelatedApps[] = $value;
							}
						}

					}
					foreach ($relatedApps as $key => $value) {
						$textServerNote[] = $value;
					}
					if (count($relatedApps) != 0 || count($notRelatedApps) != 0)
						$textServerNote[] = '---';

					foreach ($notRelatedApps as $key => $value) {
						$textServerNote[] = $value;
					}
					if (count($relatedApps) != 0 || count($notRelatedApps) != 0) {
						//$serverNoteGroup = '{"text":"Apps qui tournent sur ce server","isGroup":true,"group":"' . $key_phy . '","key":"'.$key_phy."***note".'","color":"#FFBC58"}';
						$serverNote = '{"text":"' . implode('\n', $textServerNote) . '","group":"' . $key_phy . '","category":"serverNote","key":"' . $key_phy . '***note"}';
						//$dataArray[] = $serverNoteGroup;
						$dataArray[] = $serverNote;
					}

				}

			} elseif (in_array($name_model, $machineLogique)) {//--------------LOGICAL--------------

				$concat_fun_subfun = $value["name_function"] . "***" . $value["name_subfunction"];

				if ($site == "all" && $name_model == "MACHINE VIRTUELLE") {
					$key_parent = $concat_fun_subfun;
				} else {
					$key_parent = $concat_fun_subfun . "***" . $value["server_parent"];
				}
				$key_log = $key_parent . "***" . $value["name_machine"];

				$oneNode = createNode($value, $key_log);

				$oneNode[] = '"group":"' . $key_parent . '"';
				$oneNode[] = '"isGroup":true';
				$oneNode[] = getNodeColor($value["name_environment"]);

				//Set text of non-DB
				if ($value["name_function"] != "DATABASE") {
					if ($name_model == "MACHINE VIRTUELLE") {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ')  ' . $value["rewrited_type"] . '"';
					} else {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ')"';
					}
				}

				if ($value["name_function"] == "DATABASE") {

					$oneDB = array();
					//nb of db in a log
					$database = $dbms -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_machine_logical" => $value["name_machine"], "name_machine_physical" => $value["server_parent"]));
					if ($database -> count() != 0) {//if serveur physique contains directly db

						if (isset($chiffre_db[$value["name_function"]][$value["name_subfunction"]])) {
							$chiffre_db[$value["name_function"]][$value["name_subfunction"]] += $database -> count();
						} else {
							$chiffre_db[$value["name_function"]][$value["name_subfunction"]] = $database -> count();
						}
						if ($name_model == "MACHINE VIRTUELLE") {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ') [' . $database -> count() . 'db]  ' . $value["rewrited_type"] . '"';						
						} else {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model, $sigle_model) . " " . $value["site"] . ') [' . $database -> count() . 'db]"';
						}
						foreach ($database as $k => $v) {
							$key_db = $key_log . "***" . $v["_id"];
							$oneDB = createNode($v, $key_db);
							$oneDB[] = '"isGroup":false';
							$oneDB[] = '"group":"' . $key_log . '"';
							$text_db = $v["name"] . ' ' . $v["instname"] . '\n' . $v["techno"];
							$oneDB[] = '"text":"' . $text_db . '"';
							$oneDB[] = '"category":"db"';
							//set category to db to get column shape
							$st = "{" . implode(",", $oneDB) . "}";
							$dataArray[] = $st;
						}
					} else {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . $sigle_model[$name_model] . " " . $value["site"] . ')"';
					}
				}

				$st = "{" . implode(",", $oneNode) . "}";
				$dataArray[] = $st;

				if (!in_array($key_log . "***note", $diff_note)) {
					$diff_note[] = $key_log;

					$textServerNote = array();
					$relatedApps = array();
					$notRelatedApps = array();
					$serverNoteData = $col_function -> distinct("name_application", array("name_machine" => $value["name_machine"], "name_department" => "prd-risques"));

					foreach ($serverNoteData as $key => $value) {
						$nb_parent = $app_module -> find(array("name_application" => $name_app, "name_application_parent" => $value)) -> count();
						$nb_children = $app_module -> find(array("name_application_parent" => $name_app, "name_application" => $value)) -> count();
						if ($name_app != $value) {

							if ($nb_children || $nb_parent) {
								$relatedApps[] = $value;
							} else {
								$notRelatedApps[] = $value;
							}
						}

					}
					foreach ($relatedApps as $key => $value) {
						$textServerNote[] = $value;
					}
					if (count($relatedApps) != 0 || count($notRelatedApps) != 0)
						$textServerNote[] = '---';

					foreach ($notRelatedApps as $key => $value) {
						$textServerNote[] = $value;
					}
					if (count($relatedApps) != 0 || count($notRelatedApps) != 0) {
						//$serverNoteGroup = '{"text":"Apps qui tournent sur ce server","isGroup":true,"group":"' . $key_phy . '","key":"'.$key_phy."***note".'","color":"#FFBC58"}';
						$serverNote = '{"text":"' . implode('\n', $textServerNote) . '","group":"' . $key_log . '","category":"serverNote","key":"' . $key_log . '***note"}';
						//$dataArray[] = $serverNoteGroup;
						$dataArray[] = $serverNote;
					}

				}

			}
		}

	}

	/*------FUNCTION--------*/
	$parents = array();
	$diffFunc = array();
	//contains distinct functions
	foreach ($machines as $key => $value) {
		if (!in_array($value["name_function"], $diffFunc)) {//if it's a new function
			$name_function = $value["name_function"];
			$diffFunc[] = $name_function;
			if ($site == "all") {
				$nb_phy = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "name_model" => array('$in' => $machinePhysique))) -> count();
				$nb_log = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "name_model" => array('$in' => $machineLogique))) -> count();
			} else {
				$nb_phy = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "site" => $site, "name_model" => array('$in' => $machinePhysique))) -> count();
				$nb_log = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "site" => $site, "name_model" => array('$in' => $machineLogique))) -> count();
			}

			if ($name_function == "DATABASE") {
				$parents[] = '{"key":"' . $name_function . '","text":"' . $name_function . ' [' . $nb_phy . ' phy ' . $nb_log . ' log ' . getSum($chiffre_db[$name_function]) . ' db]","isGroup":true,"color":"lightGray"}';
			} else {
				$parents[] = '{"key":"' . $name_function . '","text":"' . $name_function . ' [' . $nb_phy . ' phy ' . $nb_log . ' log]","isGroup":true,"color":"lightGray"}';
			}
		}
	}

	/*-----SUB FUNCTION------*/
	$diffSubFunc = array();
	foreach ($machines as $key => $value) {
		$concat_func_subfunc = $value["name_function"] . "***" . $value["name_subfunction"];
		//the concat distinguishes subfunctions
		if (!in_array($concat_func_subfunc, $diffSubFunc)) {
			$diffSubFunc[] = $concat_func_subfunc;
			$name_function = $value["name_function"];
			$name_subfunction = $value["name_subfunction"];
			//add the new sub function to $parents array
			if ($site == "all") {
				$nb_phy = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "name_subfunction" => $name_subfunction, "name_model" => array('$in' => $machinePhysique), "rewrited_type" => array('$nin' => $vmWare))) -> count();
				$nb_log = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "name_subfunction" => $name_subfunction, "name_model" => array('$in' => $machineLogique))) -> count();
			} else {
				$nb_phy = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "name_subfunction" => $name_subfunction, "name_model" => array('$in' => $machinePhysique), "site" => $site)) -> count();
				$nb_log = $col_function -> find(array("name_department" => "prd-risques", "name_application" => $name_app, "name_function" => $name_function, "name_subfunction" => $name_subfunction, "name_model" => array('$in' => $machineLogique), "site" => $site)) -> count();
			}

			if (!isset($chiffre_db[$name_function][$name_subfunction]))
				$chiffre_db[$name_function][$name_subfunction] = 0;
			if ($name_function == "DATABASE") {
				$parents[] = '{"key":"' . $concat_func_subfunc . '","text":"' . $value["name_subfunction"] . ' [' . $nb_phy . ' phy ' . $nb_log . ' log ' . $chiffre_db[$name_function][$name_subfunction] . ' db]","isGroup":true,"group":"' . $value["name_function"] . '","color":"lightGray"}';
			} else {
				$parents[] = '{"key":"' . $concat_func_subfunc . '","text":"' . $value["name_subfunction"] . ' [' . $nb_phy . ' phy ' . $nb_log . ' log]","isGroup":true,"group":"' . $value["name_function"] . '","color":"lightGray"}';
			}
		}

	}

	if (count($dataArray) == 0) {
		$stringTotal = "[" . implode(",", $parents) . "]";
	} else {
		$stringTotal = "[" . implode(",", $parents) . "," . implode(",", $dataArray) . "]";
	}

	echo $stringTotal;
} catch ( MongoConnectionException $e ) {
	echo $e -> getMessage();
} catch ( MongoException $e ) {
	echo $e -> getMessage();
}
?>