<?php

/*
	This file re-organises related data into right format that GoJS can take directly as input to generate schema
	The reorganised data will be saved in db for faster query afterwards

	hypersion.ksh exports rawdata from CMDB cockpit every night
	and this file will be executed right after the export finishes to update all pre-saved schemas

*/
//integrer toutes les fonctions necessaires
require_once '../functions/appmodule.php';
require_once '../functions/modelSigle.php';


//Connexion au cockpit
$conn = new MongoClient('mongodb://localhost'); // localhost pour test
$db = $conn -> COCKPIT;
if ($db -> app_module -> count() == 0) createAppModule();
if ($db -> model_sigle -> count() == 0) createModelSigle();


//Créer un tableau associatif de (name_model => sigle)
$list_sigleOfModel = $db -> model_sigle -> find();
foreach ($list_sigleOfModel as $key => $value) {
	$sigle_model[$value["name_model"]] = $value["sigle"];
}

//Définir Vmware , machine physique et machine logique
$vmWare = array("Vmware ESX", "VMware ESXi");
$machinePhysique = array("LAME", "SERVEUR PHYSIQUE", "DOMAIN", "DATABASE SERVER");
$machineLogique = array("SERVEUR LOGIQUE", "ZONE", "LOGICAL DOMAIN", "MACHINE VIRTUELLE", "PARTITION");

//Get toutes les applications
$all_apps = $db -> application -> find(array("name_department" => "prd-risques"));

//Vider la collection de diagram_log et diagram_geo
clear();

//Boucler sur toutes les applications
foreach ($all_apps as $key => $value) {

	//dataArray stocke l'info de chaque noeud dans le schéma
	$dataArray = array();

	$name_app = $value["name_application"];
	
	//Get tous les modules appartenant à cette appli
	$allModulesOfApp = $db -> app_module -> distinct("name_application", array("name_application_parent" => $name_app));
	//Inclure aussi cette appli
	$allModulesOfApp[] = $name_app;

	echo "-------" . $name_app . " starts--------<br/>";
	//Get tous les site de cette appli
	$sites_Of_App = $db -> function -> distinct("site", array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp)));
	//CreateSchema renvoie une chaîne de caractères contenant tous noeuds du schéma. "all" signifie "all sites", donc diagram logique
	$dataToSave = createSchema($allModulesOfApp, "all");
	//Sauvegarder cette chaîne de caractères dans "diagram_log"
	saveSchema($name_app, "log", $dataToSave);
	echo "Le schéma logique est sauvegardé.<br/>";

	//Boucler sur tous les sites
	foreach ($sites_Of_App as $key => $site) {
		$dataToSave = createSchema($allModulesOfApp, $site);
		//Sauvegarder cette chaîne de caractères dans "diagram_geo"
		saveSchema($name_app, "geo", $dataToSave, $site);
		echo "Un schéma géographique (" . $site . ")est sauvegardé.<br/>";
	}
	echo "-------" . $name_app . " finished--------<br/><br/>";
	//break;	
}

echo "Succès";

//	Creer toutes les DB
function createDataBase($dbs_of_this_app, $name_app, $site) {
	global $db, $allModulesOfApp;
	global $machinePhysique, $machineLogique;
	//différents phys pour cette application

	$all_db_machine = array();
	$diff_phys = $db -> dbms -> distinct("name_machine_physical", array("name_application" => array('$in' => $allModulesOfApp)));
	foreach ($diff_phys as $key => $value) {
		if (!in_array($value, $all_db_machine)) {
			$all_db_machine[] = $value;
		}
	}
	//différents log pour cette application
	$diff_log = $db -> dbms -> distinct("name_machine_logical", array("name_application" => array('$in' => $allModulesOfApp), '$where' => "this.name_machine_physical != this.name_machine_logical"));
	foreach ($diff_log as $key => $value) {
		if (!in_array($value, $all_db_machine)) {
			$all_db_machine[] = $value;
		}
	}
	//machines qui n'existent pas dans "server"
	$non_exist_machine = array();
	$newly_created_phys = array();
	//boucler sur toutes les machines
	foreach ($all_db_machine as $key => $name_machine) {
		//	chercher le phys dans "function"

		$machine_in_server = $db -> server -> findOne(array("name_server" => $name_machine));
		if ($machine_in_server == null) {// si la machine n'existe pas dans "server"
			$non_exist_machine[] = $name_machine;
		} else {
			$machine_in_func = $db -> function -> findOne(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine" => $name_machine, "name_function" => "DATABASE"));
			if ($machine_in_func == null) {

				if (in_array($machine_in_server["name_model"], $machinePhysique)) {//si c'est une machine physique

					//Créer un nouveau phys qui s'attache au groupe "DATABASE"
					if ($machine_in_server["site"] == $site || $site == "all") {
						$key_new_phys = "DATABASE***" . $name_machine;
						$oneNode = createNode($machine_in_server, $key_new_phys);
						$oneNode[] = '"group":"DATABASE"';
						$oneNode[] = '"text":"' . $name_machine . ' (' . getMachineModelName($machine_in_server["name_model"]) . ' ' . $machine_in_server["site"] . ')"';
						$oneNode[] = '"isGroup":true';
						$oneNode[] = getOSColor($machine_in_server["rewrited_type"]);
						$newly_created_phys[] = $name_machine;
						addPartToDataArray($oneNode);
						addServerNote($name_machine, $name_app, $key_new_phys, $machine_in_server["name_environment"]);
					}

				} elseif (in_array($machine_in_server["name_model"], $machineLogique)) {//si machine logique

					if ($machine_in_server["name_model"] == "MACHINE VIRTUELLE" && $site == "all") {

						//Créer un nouveau log
						$key_new_log = "DATABASE***" . $name_machine;
						$oneNode = createNode($machine_in_server, $key_new_log);
						$oneNode[] = '"group":"DATABASE"';
						$oneNode[] = '"isGroup":true';
						$oneNode[] = '"category":"mv"';
						$oneNode[] = '"text":"' . $name_machine . ' (' . getMachineModelName($machine_in_server["name_model"]) . ' ' . $machine_in_server["site"] . ')"';
						$oneNode[] = getOSColor($machine_in_server["rewrited_type"]);
						addPartToDataArray($oneNode);
						addServerNote($name_machine, $name_app, $key_new_log, $machine_in_server["name_environment"]);

					} else {
						//check si sa machine parent est dans "function"
						if ($machine_in_server["site"] == $site || $site == "all") {
							$machine_parent_in_func = $db -> function -> findOne(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine" => $machine_in_server["server_parent"], "name_function" => "DATABASE"));

							if ($machine_parent_in_func == null) {
								$machine_parent_in_server = $db -> server -> findOne(array("name_server" => $machine_in_server["server_parent"]));

								if (!in_array($machine_in_server["server_parent"], $all_db_machine) && $machine_parent_in_server != null && !in_array($machine_in_server["server_parent"], $newly_created_phys)) {

									$key_new_phys = "DATABASE***" . $machine_in_server["server_parent"];
									$oneNode = createNode($machine_in_server, $key_new_phys);
									$oneNode[] = '"group":"DATABASE"';
									$oneNode[] = '"isGroup":true';
									$oneNode[] = getOSColor($machine_in_server["rewrited_type"]);
									$oneNode[] = '"text":"' . $machine_in_server["server_parent"] . '(' . getMachineModelName($machine_in_server["name_model"]) . ' ' . $machine_in_server["site"] . ')"';

									$newly_created_phys[] = $machine_in_server["server_parent"];
									addPartToDataArray($oneNode);
									addServerNote($name_machine, $name_app, $key_new_phys, $machine_parent_in_server["name_environment"]);
								}
								$key_new_log = "DATABASE***" . $machine_in_server["server_parent"] . "***" . $name_machine;
								$oneNode = createNode($machine_in_server, $key_new_log);
								$oneNode[] = '"group":"DATABASE***' . $machine_in_server["server_parent"] . '"';
								$oneNode[] = '"isGroup":true';
								if ($machine_in_server["name_model"] == "MACHINE VIRTUELLE") {
									$oneNode[] = '"category":"mv"';
									$oneNode[] = getOSColor($machine_in_server["rewrited_type"]);
								}

								$oneNode[] = '"text":"' . $name_machine . ' (' . getMachineModelName($machine_in_server["name_model"]) . ' ' . $machine_in_server["site"] . ')"';
								addPartToDataArray($oneNode);
								addServerNote($name_machine, $name_app, $key_new_log, $machine_in_server["name_environment"]);

							} else {
								$key_new_log = "DATABASE***" . $machine_parent_in_func["name_subfunction"] . "***" . $machine_in_server["server_parent"] . "***" . $name_machine;
								$oneNode = createNode($machine_in_server, $key_new_log);
								$oneNode[] = '"group":"DATABASE***' . $machine_parent_in_func["name_subfunction"] . "***" . $machine_in_server["server_parent"] . '"';
								$oneNode[] = '"isGroup":true';
								if ($machine_in_server["name_model"] == "MACHINE VIRTUELLE")
									$oneNode[] = '"category":"mv"';
								$oneNode[] = '"text":"' . $name_machine . ' (' . getMachineModelName($machine_in_server["name_model"]) . ' ' . $machine_in_server["site"] . ')"';
								addPartToDataArray($oneNode);
								addServerNote($name_machine, $name_app, $key_new_log, $machine_in_server["name_environment"]);
								
							}
						}
						$relatedApps = array();
						$notRelatedApps = array();
						$textServerNote = array();
						$serverNoteData = $db -> function -> distinct("name_application", array("name_machine" => $value["name_machine"], "name_department" => "prd-risques"));
						$diff_note[] = $key_phy . "***note";
						foreach ($serverNoteData as $key => $value) {
							$nb_parent = $db -> app_module -> find(array("name_application" => $name_app, "name_application_parent" => $value)) -> count();
							$nb_children = $db -> app_module -> find(array("name_application_parent" => $name_app, "name_application" => $value)) -> count();
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
							$serverNote = '{"text":"' . implode('\n', $textServerNote) . '","group":"' . $key_phy . '","category":"serverNote","key":"' . $key_phy . '***note","name_environment":"' . $name_env . '"}';
							$dataArray[] = $serverNote;
						}
					}

				} else {
					throw new Exception($machine_in_server["name_model"] . ' n est precisé ni comme machine physique ni comme machine logique');
				}
			}

		}

	}

	//	Boucler sur toutes les db de cette appli
	foreach ($dbs_of_this_app as $key => $dbvalue) {

		$server_log = $db -> server -> findOne(array("name_server" => $dbvalue["name_machine_logical"]));

		if ($server_log["name_model"] == "MACHINE VIRTUELLE" && $site == "all") {
			$server_parent = $dbvalue["name_machine_logical"];
		} else {
			if (in_array($server_log["name_model"], $machineLogique)) {
				$server_parent = $server_log["server_parent"];
			} elseif (in_array($server_log["name_model"], $machinePhysique)) {
				$server_parent = $dbvalue["name_machine_physical"];
			} else {
				throw new Exception($server_log["name_model"] . 'nest precisé ni commemachine physique ni comme machine logique');
			}
		}

	
		//Si la db appartient directement à la machine physique

		if ($server_parent == $dbvalue["name_machine_logical"]) {

			if (in_array($server_parent, $non_exist_machine)) {
				//Attacher la db au groupe "DATABASE"
				$key_parent = "DATABASE";
				$key_db = $key_parent . "***" . $dbvalue["_id"];
				$oneDB = createDB($dbvalue, $key_db, $key_parent);
				addPartToDataArray($oneDB);
			} else {
				$machine_in_func = $db -> function -> findOne(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine" => $server_parent, "name_function" => "DATABASE"));
				if ($server_log["site"] == $site || $site == "all") {
					if ($machine_in_func == null) {

						$machine_in_server = $db -> server -> findOne(array("name_server" => $dbvalue["name_machine_logical"]));
						$key_parent = "DATABASE***" . $server_parent;
						$key_db = $key_parent . "***" . $dbvalue["_id"];

					} else {
					
						$key_parent = "DATABASE***" . $machine_in_func["name_subfunction"] . "***" . $server_parent;
						$key_db = $key_parent . "***" . $dbvalue["_id"];
					}
					$oneDB = createDB($dbvalue, $key_db, $key_parent);
					addPartToDataArray($oneDB);
				}

			}

		} else {// si sa machine log et sa machine phy sont différentes

			if (in_array($server_parent, $non_exist_machine)) {//sa machine physique  n'existe pas dans "server"
				if (in_array($dbvalue["name_machine_logical"], $non_exist_machine)) {//sa machine logique n'existe pas dans "server"
					//Creer la db et l'attacher au groupe "DATABASE"
					$key_db = "DATABASE***" . $dbvalue["_id"];
					$oneDB = createDB($dbvalue, $key_db, "DATABASE");
					addPartToDataArray($oneDB);
				} else {//sa machine logique existe dans "server"
					//Créer la db et l'attacher à la machine logique
					if ($server_log["site"] == $site || $site == "all") {
						$key_db = "DATABASE***" . $dbvalue["name_machine_log"] . "***" . $dbvalue["_id"];
						$oneDB = createDB($dbvalue, $key_db, "DATABASE***" . $dbvalue["name_machine_log"]);
						addPartToDataArray($oneDB);
					}
				}

			} else {//phys existe

				$tmp = $db -> server -> findOne(array("name_server" => $server_parent));
				$site_phys = $tmp["site"];
				if ($site == $site_phys || $site == "all") {
					if (in_array($dbvalue["name_machine_logical"], $non_exist_machine)) {// log n'existe pas
						//attacher la db au phys
						$machine_in_func = $db -> function -> findOne(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine" => $server_parent, "name_function" => "DATABASE"));
						if ($machine_in_func == null) {
							$key_db = "DATABASE***" . $dbvalue["name_machine_physical"] . "***" . $dbvalue["_id"];
							$oneDB = createDB($dbvalue, $key_db, "DATABASE***" . $server_parent);
						} else {
							$key_db = "DATABASE***" . $machine_in_func["name_subfunction"] . "***" . $server_parent . "***" . $dbvalue["_id"];
							$oneDB = createDB($dbvalue, $key_db, "DATABASE***" . $machine_in_func["name_subfunction"] . "***" . $server_parent);
						}
					} else {// log existe et phys existe
						$machine_phys_in_func = $db -> function -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine" => $server_parent, "name_function" => "DATABASE"));
						$machine_log_in_func = $db -> function -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine" => $dbvalue["name_machine_logical"], "name_function" => "DATABASE"));
						$subfunction = "";
						foreach ($machine_phys_in_func as $key => $value) {
							foreach ($machine_log_in_func as $k => $v) {
								if ($value["name_subfunction"] == $v["name_subfunction"]) {
									$subfunction = $value["name_subfunction"];
									break;
								}
							}
						}
						if ($subfunction == "") {

							if ($machine_phys_in_func -> count() != 0 && $machine_log_in_func -> count() == 0) {
								foreach ($machine_phys_in_func as $key => $value) {
									$subfunction = $value["name_subfunction"];
									break;
								}

								$key_db = "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"] . "***" . $dbvalue["_id"];
								$oneDB = createDB($dbvalue, $key_db, "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"]);

							} elseif ($machine_log_in_func -> count() == 0 && $machine_phys_in_func -> count() == 0) {

								$key_db = "DATABASE***" . $server_parent . "***" . $dbvalue["name_machine_logical"] . "***" . $dbvalue["_id"];
								$oneDB = createDB($dbvalue, $key_db, "DATABASE***" . $server_parent . "***" . $dbvalue["name_machine_logical"]);

							} else {
								$key_db = "DATABASE***" . $dbvalue["_id"];
								$oneDB = createDB($dbvalue, $key_db, "DATABASE");

							}

						} else {

							$key_db = "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"] . "***" . $dbvalue["_id"];
							$oneDB = createDB($dbvalue, $key_db, "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"]);

						}

					}
					addPartToDataArray($oneDB);
				}

			}

		}
	}
}

function createSchema($allModulesOfApp, $site) {
	global $db;
	global $vmWare, $machinePhysique, $machineLogique;
	global $sigle_model, $dataArray;
	$name_app = end(array_values($allModulesOfApp));
	try {
		//$machines  = toutes les machines dans "function"
		$machines = $db -> function -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp)));
		$chiffre_db = array( array());

		$oneNode = array();
		$dataArray = array();

		$diff_note = array();
		$diff_server = array();
		// pour afficher qu'une seule fois un serveur qui fonctionne à la fois pour l'app et ses modules
		$dbs_with_parent_in_function = array();
		$dbs_of_this_app = $db -> dbms -> find(array("name_department" => "prd-risques", "name_application" => $name_app));

		createDataBase($dbs_of_this_app, $name_app, $site);

		/*------MACHINE NODE--------*/
		foreach ($machines as $key => $value) {

			if ($site == $value["site"] || $site == "all") {

				$name_model = $value["name_model"];
				$name_env = $value["name_environment"];

				$this_server = $value["name_function"] . $value["name_subfunction"] . $value["name_machine"] . $value["name_environment"];
				if (in_array($this_server, $diff_server)) {
					continue;
				}
				$diff_server[] = $this_server;

				if (in_array($name_model, $machinePhysique)) {//if this machine is a machine physique

					if ($site == "all" && in_array($value["rewrited_type"], $vmWare))
						continue;
					/*if ($site != "all" && in_array($value["rewrited_type"], $vmWare)) {
					 if ($nb_log == 0)
					 continue;
					 }*/

					$concat_fun_subfun = $value["name_function"] . "***" . $value["name_subfunction"];
					$key_phy = $concat_fun_subfun . "***" . $value["name_machine"];

					$oneNode = createNode($value, $key_phy);

					$oneNode[] = '"group":"' . $concat_fun_subfun . '"';
					$oneNode[] = '"isGroup":true';
					$oneNode[] = getOSColor($value["rewrited_type"]);
					//nb de machine log appartenant à la machine phy
					$nb_log = $db -> function -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "server_parent" => $value["name_machine"], "name_function" => $value["name_function"], "name_subfunction" => $value["name_subfunction"])) -> count();

					//Set text of non DB
					if ($value["name_function"] != "DATABASE") {
						if ($nb_log == 0) {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ')"';
						} else {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ')[' . $nb_log . ' log]"';
						}
					}

					if ($value["name_function"] == "DATABASE") {
						//direct db + indirect db
						$database_in_phy = $db -> dbms -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine_physical" => $value["name_machine"]));
						//direct db
						$database = $db -> dbms -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine_logical" => $value["name_machine"]));

						//set text of DB
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ') [' . $nb_log . ' log ' . $database_in_phy -> count() . ' db]"';

						$oneDB = array();

					}
					addPartToDataArray($oneNode);

					if (!in_array($key_phy . "***note", $diff_note)) {
						$diff_note[] = $key_phy . "***note";
						addServerNote($value["name_machine"], $name_app, $key_phy, $name_env);
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

					if ($name_model == "MACHINE VIRTUELLE") {
						$oneNode[] = '"category":"mv"';
						$oneNode[] = getOSColor($value["rewrited_type"]);

					} else {
						$oneNode[] = '"background":"white"';
					}
					//Set text of non-DB
					if ($value["name_function"] != "DATABASE") {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ')"';
					}

					if ($value["name_function"] == "DATABASE") {

						$oneDB = array();
						//nb of db in a log
						$database = $db -> dbms -> find(array("name_department" => "prd-risques", "name_application" => array('$in' => $allModulesOfApp), "name_machine_logical" => $value["name_machine"], "name_machine_physical" => $value["server_parent"]));
						if ($database -> count() != 0) {//if serveur physique contains directly db

							if ($name_model == "MACHINE VIRTUELLE") {
								$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ')"';
							} else {
								$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ')"';
							}

						} else {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($name_model) . " " . $value["site"] . ')"';
						}
					}

					addPartToDataArray($oneNode);

					if (!in_array($key_log . "***note", $diff_note)) {
						$diff_note[] = $key_log;
						addServerNote($value["name_machine"], $name_app, $key_log, $name_env);
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
				$parents[] = '{"key":"' . $name_function . '","text":"' . $name_function . '","isGroup":true,"font":"18px sans-serif","color":"lightGray","category":"function"}';

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
				$parents[] = '{"key":"' . $concat_func_subfunc . '","text":"' . $value["name_subfunction"] . '","isGroup":true,"group":"' . $value["name_function"] . '","font":"15px sans-serif","color":"lightGray","category":"function"}';

			}
		}

		if (count($dataArray) == 0) {
			$stringTotal = "[" . implode(",", $parents) . "]";
		} else {
			$stringTotal = "[" . implode(",", $parents) . "," . implode(",", $dataArray) . "]";
		}

		//echo $stringTotal;
		return $stringTotal;

	} catch ( MongoConnectionException $e ) {
		return $e -> getMessage();
	} catch ( MongoException $e ) {
		return $e -> getMessage();
	}
}

// Vider diagram_log et diagram_geo
function clear() {
	global $db;
	$db -> diagram_log -> remove();
	$db -> diagram_geo -> remove();
}

// Sauvegarder le schema dans la collection correspondante
function saveSchema($name_app, $type, $dataToSave, $site = "") {
	global $db;
	if ($type == "log") {
		$db -> diagram_log -> update(array("name_application" => $name_app), array("name_application" => $name_app, "data" => json_decode($dataToSave)), array("upsert" => 1));
	} elseif ($type == "geo") {
		$db -> diagram_geo -> update(array("name_application" => $name_app, "site" => $site), array("name_application" => $name_app, "data" => json_decode($dataToSave), "site" => $site), array("upsert" => 1));

	}
}

//	Créer un noeud de machine
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
	$oneNode[] = getEnvColor($info_machine["name_environment"]);
	return $oneNode;
}

function addServerNote($name_machine, $name_app, $key_parent, $name_env) {

	global $db, $dataArray;

	$textServerNote = array();
	$relatedApps = array();
	$notRelatedApps = array();

	$serverNoteData = $db -> function -> distinct("name_application", array("name_machine" => $name_machine, "name_department" => "prd-risques"));

	foreach ($serverNoteData as $key => $value) {
		$nb_parent = $db -> app_module -> find(array("name_application" => $name_app, "name_application_parent" => $value)) -> count();
		$nb_children = $db -> app_module -> find(array("name_application_parent" => $name_app, "name_application" => $value)) -> count();
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
		$serverNote = '{"text":"' . implode('\n', $textServerNote) . '","group":"' . $key_parent . '","category":"serverNote","key":"' . $key_parent . '***note","name_environment":"' . $name_env . '"}';
		$dataArray[] = $serverNote;
	}
}

//	Ajouter un noeud ou une db dans le DataArray
function addPartToDataArray($onePart) {
	global $dataArray;
	$st = "{" . implode(",", $onePart) . "}";
	$dataArray[] = $st;
}

//	Créer une DB
function createDB($info_db, $key, $key_parent) {
	$oneDB = createNode($info_db, $key);
	$oneDB[] = '"isGroup":false';
	$oneDB[] = '"group":"' . $key_parent . '"';

	$oneDB[] = '"category":"db"';
	$oneDB[] = getDBcolor($info_db["techno"]);
	$text_db = $info_db["name"] . ' ' . $info_db["instname"] . '\n' . $info_db["techno"];
	$oneDB[] = '"text":"' . $text_db . '"';

	return $oneDB;
}

// Renvoyer la couleur pour l'environement
function getEnvColor($name_env) {
	if ($name_env == "Dev") {
		return '"color":"#FFB3FF"';
	} elseif ($name_env == "Rec") {
		return '"color":"#CCCCB2"';
	} elseif ($name_env == "Qualif") {
		return '"color":"lightBlue"';
	} elseif ($name_env == "Bench") {
		return '"color":"orange"';
	} elseif ($name_env == "Prod") {
		return '"color":"#86CB8F"';
	} else {
		return '"color":"white"';
	}
}

// Renvoyer la couleur pour l'OS
function getOSColor($rewrited_type) {
	if ($rewrited_type == "Windows") {
		return '"background":"#D1F0FF"';
	} elseif ($rewrited_type == "Linux") {
		return '"background":"#C299FF"';
	} elseif ($rewrited_type == "Solaris") {
		return '"background":"#FFA3A3"';
	} elseif ($rewrited_type == "Vmware ESX" || $rewrited_type == "VMware ESXi") {
		return '"background":"#DBFFDB"';
	} else {
		return '"background":"white"';
	}
}

// Renvoyer le sigle de machine_model
function getMachineModelName($name_model) {
	global $sigle_model;
	if (array_key_exists($name_model, $sigle_model))
		return $sigle_model[$name_model];
	else
		return $name_model;
}

//	Renvoyer la couleur pour la DB
function getDBcolor($techno) {
	if (strpos($techno, "Sybase") !== FALSE) {
		return '"color":"lightGray"';
	} elseif (strpos($techno, "Oracle") !== FALSE) {
		return '"color":"#CC80E6"';
	} elseif (strpos($techno, "Sql") !== FALSE) {
		return '"color":"#5EA2CF"';
	} else {
		return '"color":"white"';
	}
}

$conn -> close();
?>