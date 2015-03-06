<?php

/*
	This file re-organises related data into right format that GoJS can take directly as input to 
	generate schema.
	The reorganised data will be saved in db for faster query afterwards.

	hypersion.ksh exports rawdata from CMDB cockpit every night and this file will be executed
	right after the export finishes to update all pre-saved schemas.

*/

// Intégrer toutes les fonctions publiques necessaires
require_once '../functions/appmodule.php';
require_once '../functions/modelSigle.php';

// Intégrer les fonctions dédiées au cron
require_once './utils.php';

// Connexion au cockpit
$conn = new MongoClient('mongodb://localhost'); // localhost pour test
$db = $conn -> COCKPIT;
if ($db -> app_module -> count() == 0) {
	createAppModule();
}
if ($db -> model_sigle -> count() == 0) {
	createModelSigle();
}

// Récupérer tous les model-sigle
$listSigleOfModel = $db -> model_sigle -> find();
// Créer un tableau associatif de (name_model => sigle)
$sigleModel = array();
foreach ($listSigleOfModel as $key => $value) {
	$sigleModel[$value["name_model"]] = $value["sigle"];
}

// Définir Vmware , machine physique et machine logique
$vmWare = array(
	"Vmware ESX", "VMware ESXi"
);
$machinePhysique = array(
	"LAME", "SERVEUR PHYSIQUE", "DOMAIN", "DATABASE SERVER"
);
$machineLogique = array(
	"SERVEUR LOGIQUE", "ZONE", "LOGICAL DOMAIN", "MACHINE VIRTUELLE", "PARTITION"
);

// Récupérer toutes les applications
$apps = $db -> application -> find(array(
	"name_department" => "prd-risques"
));

// Vider la collection de diagram_log et diagram_geo
clear();

// Boucler sur toutes les applications
foreach ($apps as $key => $value) {

	// dataArray pour stocker l'info de chaque noeud dans le schéma
	$dataArray = array();

	// Récupérer le nom de l'application courante
	$nameApp = $value["name_application"];
	
	// Récupérer tous les modules appartenant à cette appli
	$allModulesOfApp = $db -> app_module -> distinct("name_application", array(
		"name_application_parent" => $nameApp
	));
	// Inclure aussi cette appli
	$allModulesOfApp[] = $nameApp;

	// TODO: vérifier si ce message sert à debug ou à l'affichage au client
	//             virer si c'est le cas de debug
	echo "-------" . $nameApp . " starts--------<br/>";

	// Récupérer tous les site de cette appli
	$sitesOfApp = $db -> function -> distinct("site", array(
		"name_department" => "prd-risques",
		"name_application" => array(
			'$in' => $allModulesOfApp
		)
	));

	// Recueillir le renvoi de la chaîne de caractères contenant tous noeuds du schéma.
	//     "all" signifie "all sites", donc diagram logique
	$dataToSave = createSchema($allModulesOfApp, "all");
	// Sauvegarder cette chaîne de caractères dans "diagram_log"
	saveSchema($nameApp, "log", $dataToSave);

	// TODO: vérifier si ce message sert à debug ou à l'affichage au client
	//             virer si c'est le cas de debug
	echo "Le schéma logique est sauvegardé.<br/>";

	// Boucler sur tous les sites
	foreach ($sitesOfApp as $key => $site) {
		// Recueillir seulement la chaîne des noeuds du site courant
		$dataToSave = createSchema($allModulesOfApp, $site);
		// Sauvegarder cette chaîne de caractères dans "diagram_geo"
		saveSchema($nameApp, "geo", $dataToSave, $site);

		// TODO: vérifier si ce message sert à debug ou à l'affichage au client
		//             virer si c'est le cas de debug
		echo "Un schéma géographique (" . $site . ")est sauvegardé.<br/>";
	}

	// TODO: vérifier si ce message sert à debug ou à l'affichage au client
	//             virer si c'est le cas de debug
	echo "-------" . $nameApp . " finished--------<br/><br/>";	
}

// TODO: vérifier si ce message sert à debug ou à l'affichage au client
//             virer si c'est le cas de debug
echo "Succès";

// Créer toutes les DB
function createDataBase($dbsOfApp, $nameApp, $site) {

	// Déclarer l'utilisation des variables globales (NON-RECOMMANDEES)
	global $db;
	global $allModulesOfApp;
	global $machinePhysique;
	global $machineLogique;

	// différents phys pour cette application (COMMENTAIRE NON-CLAIRE)
	$dbMachines = array();
	$phys = $db -> dbms -> distinct("name_machine_physical", array(
		"name_application" => array(
			'$in' => $allModulesOfApp
		)
	));
	foreach ($phys as $key => $value) {
		if (!in_array($value, $dbMachines)) {
			$dbMachines[] = $value;
		}
	}

	// différents log pour cette application
	$logs = $db -> dbms -> distinct("name_machine_logical", array(
		"name_application" => array(
			'$in' => $allModulesOfApp
		),
		'$where' => "this.name_machine_physical != this.name_machine_logical"
	));
	foreach ($logs as $key => $value) {
		if (!in_array($value, $dbMachines)) {
			$dbMachines[] = $value;
		}
	}

	// Machines qui n'existent pas dans "server"
	$nonExistMachine = array();
	$newlyCreatedPhys = array();

	// Boucler sur toutes les machines
	foreach ($dbMachines as $key => $nameMachine) {

		// Chercher le phys dans "server"
		$machineInServer = $db -> server -> findOne(array(
			"name_server" => $nameMachine
		));

		// Si la machine n'existe pas dans "server", stocker la
		// Sinon chercher le phys dans "function"
		if ($machineInServer == null) {
			$nonExistMachine[] = $nameMachine;
		} else {

			// Chercher le phys dans "function"
			$machineInFunc = $db -> function -> findOne(array(
				"name_department" => "prd-risques",
				"name_application" => array(
					'$in' => $allModulesOfApp
				),
				"name_machine" => $nameMachine,
				"name_function" => "DATABASE"
			));

			// Si la machine n'existe pas dans "function", (BLABLA)
			// Sinon, (BLABLA)
			if ($machineInFunc == null) {

				// Si c'est une machine physique, (BLABLA)
				// Si c'est une machine logique, (BLABLA)
				// Sinon, retourner une exception
				if (in_array($machineInServer["name_model"], $machinePhysique)) {

					// (MANQUE D'EXPLICATION DES CONDITIONS)
					if ($machineInServer["site"] == $site || $site == "all") {

						// Créer un nouveau phys qui s'attache au groupe "DATABASE"
						$keyNewPhys = "DATABASE***" . $nameMachine;
						$oneNode = createNode($machineInServer, $keyNewPhys);
						$oneNode[] = '"group":"DATABASE"';
						$oneNode[] = '"text":"' . $nameMachine . ' (' . getMachineModelName($machineInServer["name_model"]) . ' ' . $machineInServer["site"] . ')"';
						$oneNode[] = '"isGroup":true';
						$oneNode[] = getOSColor($machineInServer["rewrited_type"]);
						$newlyCreatedPhys[] = $nameMachine;
						addPartToDataArray($oneNode);
						addServerNote($nameMachine, $nameApp, $keyNewPhys, $machineInServer["name_environment"]);
					
					}

				} elseif (in_array($machineInServer["name_model"], $machineLogique)) {

					// (MANQUE D'EXPLICATION DES CONDITIONS)
					if ($machineInServer["name_model"] == "MACHINE VIRTUELLE" && $site == "all") {

						// Créer un nouveau log
						$keyNewLog = "DATABASE***" . $nameMachine;
						$oneNode = createNode($machineInServer, $keyNewLog);
						$oneNode[] = '"group":"DATABASE"';
						$oneNode[] = '"isGroup":true';
						$oneNode[] = '"category":"mv"';
						$oneNode[] = '"text":"' . $nameMachine . ' (' . getMachineModelName($machineInServer["name_model"]) . ' ' . $machineInServer["site"] . ')"';
						$oneNode[] = getOSColor($machineInServer["rewrited_type"]);
						addPartToDataArray($oneNode);
						addServerNote($nameMachine, $nameApp, $keyNewLog, $machineInServer["name_environment"]);

					} else {

						// Vérifier si sa machine parent est dans "function"
						if ($machineInServer["site"] == $site || $site == "all") {

							// Récupérer la machine parent dans "function"
							$machineParentInFunc = $db -> function -> findOne(array(
								"name_department" => "prd-risques",
								"name_application" => array(
									'$in' => $allModulesOfApp
								),
								"name_machine" => $machineInServer["server_parent"],
								"name_function" => "DATABASE"
							));

							// Si la machine parent n'existe pas dans "function", (BLABLA)
							// Sinon, (BLABLA)
							if ($machineParentInFunc == null) {

								// Récupérer la machine parent dans "server"
								$machine_parent_in_server = $db -> server -> findOne(array(
									"name_server" => $machineInServer["server_parent"]
								));

								// (MANQUE D'EXPLICATION DES CONDITIONS)
								if (!in_array($machineInServer["server_parent"], $dbMachines) &&
									$machine_parent_in_server != null &&
									!in_array($machineInServer["server_parent"], $newlyCreatedPhys)) {

									// Créer un nouveau phys
									$keyNewPhys = "DATABASE***" . $machineInServer["server_parent"];
									$oneNode = createNode($machineInServer, $keyNewPhys);
									$oneNode[] = '"group":"DATABASE"';
									$oneNode[] = '"isGroup":true';
									$oneNode[] = getOSColor($machineInServer["rewrited_type"]);
									$oneNode[] = '"text":"' . $machineInServer["server_parent"] . '(' . getMachineModelName($machineInServer["name_model"]) . ' ' . $machineInServer["site"] . ')"';

									// Affecter le nouveau phys comme la machine parent
									$newlyCreatedPhys[] = $machineInServer["server_parent"];
									addPartToDataArray($oneNode);
									addServerNote($nameMachine, $nameApp, $keyNewPhys, $machine_parent_in_server["name_environment"]);
								}

								// Créer un nouveau log
								$keyNewLog = "DATABASE***" . $machineInServer["server_parent"] . "***" . $nameMachine;
								$oneNode = createNode($machineInServer, $keyNewLog);
								$oneNode[] = '"group":"DATABASE***' . $machineInServer["server_parent"] . '"';
								$oneNode[] = '"isGroup":true';
								if ($machineInServer["name_model"] == "MACHINE VIRTUELLE") {
									$oneNode[] = '"category":"mv"';
									$oneNode[] = getOSColor($machineInServer["rewrited_type"]);
								}
								$oneNode[] = '"text":"' . $nameMachine . ' (' . getMachineModelName($machineInServer["name_model"]) . ' ' . $machineInServer["site"] . ')"';
								addPartToDataArray($oneNode);
								addServerNote($nameMachine, $nameApp, $keyNewLog, $machineInServer["name_environment"]);

							} else {

								// Créer un nouveau log
								$keyNewLog = "DATABASE***" . $machineParentInFunc["name_subfunction"] . "***" . $machineInServer["server_parent"] . "***" . $nameMachine;
								$oneNode = createNode($machineInServer, $keyNewLog);
								$oneNode[] = '"group":"DATABASE***' . $machineParentInFunc["name_subfunction"] . "***" . $machineInServer["server_parent"] . '"';
								$oneNode[] = '"isGroup":true';
								if ($machineInServer["name_model"] == "MACHINE VIRTUELLE")
									$oneNode[] = '"category":"mv"';
								$oneNode[] = '"text":"' . $nameMachine . ' (' . getMachineModelName($machineInServer["name_model"]) . ' ' . $machineInServer["site"] . ')"';
								addPartToDataArray($oneNode);
								addServerNote($nameMachine, $nameApp, $keyNewLog, $machineInServer["name_environment"]);
								
							}
						}

						// (MANQUE D'EXPLICATIONS GENERALES)
						$relatedApps = array();
						$notRelatedApps = array();
						$textServerNote = array();
						$serverNoteData = $db -> function -> distinct("name_application", array(
							"name_machine" => $value["name_machine"],
							"name_department" => "prd-risques"
						));
						$diffNote[] = $keyPhy . "***note";

						// Boucler sur les notes
						foreach ($serverNoteData as $key => $value) {
							$nbParents = $db -> app_module -> find(array(
								"name_application" => $nameApp,
								"name_application_parent" => $value
							)) -> count();
							$nbChildren = $db -> app_module -> find(array(
								"name_application_parent" => $nameApp,
								"name_application" => $value
							)) -> count();

							// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
							if ($nameApp != $value) {
								if ($nbChildren || $nbParents) {
									$relatedApps[] = $value;
								} else {
									$notRelatedApps[] = $value;
								}
							}

						}

						// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
						foreach ($relatedApps as $key => $value) {
							$textServerNote[] = $value;
						}
						if (count($relatedApps) != 0 || count($notRelatedApps) != 0)
							$textServerNote[] = '---';

						// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
						foreach ($notRelatedApps as $key => $value) {
							$textServerNote[] = $value;
						}
						if (count($relatedApps) != 0 || count($notRelatedApps) != 0) {
							$serverNote = '{"text":"' . implode('\n', $textServerNote) . '","group":"' . $keyPhy . '","category":"serverNote","key":"' . $keyPhy . '***note","name_environment":"' . $nameEnv . '"}';
							$dataArray[] = $serverNote;
						}
					}

				} else {
					throw new Exception($machineInServer["name_model"] . ' n est precisé ni comme machine physique ni comme machine logique');
				}
			}

		}

	}

	// Boucler sur toutes les db de cette appli
	foreach ($dbsOfApp as $key => $dbvalue) {

		// Récupérer le log dans "server"
		$server_log = $db -> server -> findOne(array(
			"name_server" => $dbvalue["name_machine_logical"]
		));

		// (MANQUE D'EXPLICATION DES CONDITIONS)
		if ($server_log["name_model"] == "MACHINE VIRTUELLE" && $site == "all") {
			$server_parent = $dbvalue["name_machine_logical"];
		} else {
			// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
			if (in_array($server_log["name_model"], $machineLogique)) {
				$server_parent = $server_log["server_parent"];
			} elseif (in_array($server_log["name_model"], $machinePhysique)) {
				$server_parent = $dbvalue["name_machine_physical"];
			} else {
				throw new Exception($server_log["name_model"] . 'nest precisé ni commemachine physique ni comme machine logique');
			}
		}

	
		// Si la db appartient directement à la machine physique, (BLABLA)
		// Sinon, sa machine log et sa machine phy sont différentes, alors (BLABLA)
		if ($server_parent == $dbvalue["name_machine_logical"]) {

			if (in_array($server_parent, $nonExistMachine)) {

				//Attacher la db au groupe "DATABASE"
				$keyParent = "DATABASE";
				$keyDb = $keyParent . "***" . $dbvalue["_id"];
				$oneDB = createDB($dbvalue, $keyDb, $keyParent);
				addPartToDataArray($oneDB);

			} else {

				// Récupérer la machine dans "function"
				$machineInFunc = $db -> function -> findOne(array(
					"name_department" => "prd-risques",
					"name_application" => array(
						'$in' => $allModulesOfApp
					),
					"name_machine" => $server_parent,
					"name_function" => "DATABASE"
				));

				// (MANQUE D'EXPLICATION DES CONDITIONS)
				if ($server_log["site"] == $site || $site == "all") {

					// Si la machine n'existe pas dans "function", (BLABLA)
					// Sinon, (BLABLA)
					if ($machineInFunc == null) {
						$machineInServer = $db -> server -> findOne(array(
							"name_server" => $dbvalue["name_machine_logical"]
						));
						$keyParent = "DATABASE***" . $server_parent;
						$keyDb = $keyParent . "***" . $dbvalue["_id"];

					} else {
						$keyParent = "DATABASE***" . $machineInFunc["name_subfunction"] . "***" . $server_parent;
						$keyDb = $keyParent . "***" . $dbvalue["_id"];
					}

					$oneDB = createDB($dbvalue, $keyDb, $keyParent);
					addPartToDataArray($oneDB);
				}

			}

		} else {

			// Si sa machine physique n'existe pas dans "server", (BLABLA)
			// Sinon, (BLABLA)
			if (in_array($server_parent, $nonExistMachine)) {

				// Si sa machine logique n'existe pas dans "server", créer la db et l'attacher
				//     au groupe "DATABASE"
				// Sinon, créer la db et l'attacher à la machine logique
				if (in_array($dbvalue["name_machine_logical"], $nonExistMachine)) {
					$keyDb = "DATABASE***" . $dbvalue["_id"];
					$oneDB = createDB($dbvalue, $keyDb, "DATABASE");
					addPartToDataArray($oneDB);
				} else {
					if ($server_log["site"] == $site || $site == "all") {
						$keyDb = "DATABASE***" . $dbvalue["name_machine_log"] . "***" . $dbvalue["_id"];
						$oneDB = createDB($dbvalue, $keyDb, "DATABASE***" . $dbvalue["name_machine_log"]);
						addPartToDataArray($oneDB);
					}
				}

			} else {

				// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
				$tmp = $db -> server -> findOne(array(
					"name_server" => $server_parent
				));
				$site_phys = $tmp["site"];

				// (MANQUE D'EXPLICATION DES CONDITIONS)
				if ($site == $site_phys || $site == "all") {

					// Si le log n'existe pas, attacher la db au phys
					// Sinon, (BLABLA)
					if (in_array($dbvalue["name_machine_logical"], $nonExistMachine)) {

						// Récupérer la machine dans "function"
						$machineInFunc = $db -> function -> findOne(array(
							"name_department" => "prd-risques",
							"name_application" => array(
								'$in' => $allModulesOfApp
							),
							"name_machine" => $server_parent,
							"name_function" => "DATABASE"
						));

						// Si la machine n'existe pas dans "function", (BLABLA)
						// Sinon, (BLABLA)
						if ($machineInFunc == null) {
							$keyDb = "DATABASE***" . $dbvalue["name_machine_physical"] . "***" . $dbvalue["_id"];
							$oneDB = createDB($dbvalue, $keyDb, "DATABASE***" . $server_parent);
						} else {
							$keyDb = "DATABASE***" . $machineInFunc["name_subfunction"] . "***" . $server_parent . "***" . $dbvalue["_id"];
							$oneDB = createDB($dbvalue, $keyDb, "DATABASE***" . $machineInFunc["name_subfunction"] . "***" . $server_parent);
						}
					} else {

						// Récupérer les phys dans "function"
						$machinePhysInFunc = $db -> function -> find(array(
							"name_department" => "prd-risques",
							"name_application" => array(
								'$in' => $allModulesOfApp
							),
							"name_machine" => $server_parent,
							"name_function" => "DATABASE"
						));

						// Récupérer les log dans "function"
						$machineLogInFunc = $db -> function -> find(array(
							"name_department" => "prd-risques",
							"name_application" => array(
								'$in' => $allModulesOfApp
							),
							"name_machine" => $dbvalue["name_machine_logical"],
							"name_function" => "DATABASE"
						));

						// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
						$subfunction = "";
						foreach ($machinePhysInFunc as $key => $value) {
							foreach ($machineLogInFunc as $k => $v) {
								if ($value["name_subfunction"] == $v["name_subfunction"]) {
									$subfunction = $value["name_subfunction"];
									break;
								}
							}
						}

						// (MANQUE D'EXPLICATION DE LA CONDITION)
						if ($subfunction == "") {

							// (MANQUE D'EXPLICATION DES CONDITIONS)
							if ($machinePhysInFunc -> count() != 0 &&
								$machineLogInFunc -> count() == 0) {

								foreach ($machinePhysInFunc as $key => $value) {
									$subfunction = $value["name_subfunction"];
									break;
								}

								$keyDb = "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"] . "***" . $dbvalue["_id"];
								$oneDB = createDB($dbvalue, $keyDb, "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"]);

							} elseif ($machineLogInFunc -> count() == 0 &&
								$machinePhysInFunc -> count() == 0) {

								$keyDb = "DATABASE***" . $server_parent . "***" . $dbvalue["name_machine_logical"] . "***" . $dbvalue["_id"];
								$oneDB = createDB($dbvalue, $keyDb, "DATABASE***" . $server_parent . "***" . $dbvalue["name_machine_logical"]);

							} else {

								$keyDb = "DATABASE***" . $dbvalue["_id"];
								$oneDB = createDB($dbvalue, $keyDb, "DATABASE");

							}

						} else {

							$keyDb = "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"] . "***" . $dbvalue["_id"];
							$oneDB = createDB($dbvalue, $keyDb, "DATABASE***" . $subfunction . "***" . $server_parent . "***" . $dbvalue["name_machine_logical"]);

						}

					}

					addPartToDataArray($oneDB);
				}

			}

		}
	}
}

function createSchema($allModulesOfApp, $site) {

	// Déclarer l'utilisation des variables globales (NON-RECOMMANDEES)
	global $db;
	global $vmWare;
	global $machinePhysique;
	global $machineLogique;
	global $sigleModel;
	global $dataArray;

	$nameApp = end(array_values($allModulesOfApp));

	try {
		// $machines  = toutes les machines dans "function"
		$machines = $db -> function -> find(array(
			"name_department" => "prd-risques",
			"name_application" => array(
				'$in' => $allModulesOfApp
			)
		));

		// (VARIABLES NON-UTILISEE, VERIFIER SI C'EST VALIDE DE LA VIRER)
		$chiffre_db = array(array());

		$oneNode = array();
		$dataArray = array();

		$diffNote = array();
		$diffserver = array();

		// Pour afficher qu'une seule fois un serveur qui fonctionne à la fois pour l'app
		//     et ses modules
		// (VARIABLES NON-UTILISEE, VERIFIER SI C'EST VALIDE DE LA VIRER)
		$dbs_with_parent_in_function = array();
		$dbsOfApp = $db -> dbms -> find(array(
			"name_department" => "prd-risques",
			"name_application" => $nameApp
		));

		createDataBase($dbsOfApp, $nameApp, $site);

		/*------MACHINE NODE--------*/
		foreach ($machines as $key => $value) {

			if ($site == $value["site"] || $site == "all") {

				$nameModel = $value["name_model"];
				$nameEnv = $value["name_environment"];

				$this_server = $value["name_function"] . $value["name_subfunction"] . $value["name_machine"] . $value["name_environment"];
				if (in_array($this_server, $diffserver)) {
					continue;
				}
				$diffserver[] = $this_server;

				// Si c'est une machine physique, (BLABLA)
				// Sinon, c'est une machine logique, alors (BLABLA)
				if (in_array($nameModel, $machinePhysique)) {

					// (MANQUE D'EXPLICATION DES CONDITIONS)
					if ($site == "all" && in_array($value["rewrited_type"], $vmWare)) {
						continue;
					}

					// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
					$concatFunSubfun = $value["name_function"] . "***" . $value["name_subfunction"];
					$keyPhy = $concatFunSubfun . "***" . $value["name_machine"];

					$oneNode = createNode($value, $keyPhy);

					$oneNode[] = '"group":"' . $concatFunSubfun . '"';
					$oneNode[] = '"isGroup":true';
					$oneNode[] = getOSColor($value["rewrited_type"]);

					// Nombre de machine log appartenant à la machine phy
					$nbLog = $db -> function -> find(array(
						"name_department" => "prd-risques",
						"name_application" => array(
							'$in' => $allModulesOfApp
						),
						"server_parent" => $value["name_machine"],
						"name_function" => $value["name_function"],
						"name_subfunction" => $value["name_subfunction"]
					)) -> count();

					// Mettre le texte de non db
					if ($value["name_function"] != "DATABASE") {
						if ($nbLog == 0) {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ')"';
						} else {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ')[' . $nbLog . ' log]"';
						}
					}

					if ($value["name_function"] == "DATABASE") {

						// Direct db + indirect db
						$databaseInPhy = $db -> dbms -> find(array(
							"name_department" => "prd-risques",
							"name_application" => array(
								'$in' => $allModulesOfApp
							),
							"name_machine_physical" => $value["name_machine"]
						));
						
						// Direct db
						$database = $db -> dbms -> find(array(
							"name_department" => "prd-risques",
							"name_application" => array(
								'$in' => $allModulesOfApp
							),
							"name_machine_logical" => $value["name_machine"]
						));

						// Mettre le texte de db
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ') [' . $nbLog . ' log ' . $databaseInPhy -> count() . ' db]"';

						$oneDB = array();

					}
					addPartToDataArray($oneNode);

					if (!in_array($keyPhy . "***note", $diffNote)) {
						$diffNote[] = $keyPhy . "***note";
						addServerNote($value["name_machine"], $nameApp, $keyPhy, $nameEnv);
					}

				} elseif (in_array($nameModel, $machineLogique)) {

					// (MANQUE D'EXPLICATION SUR LA LOGIQUE)
					$concatFunSubfun = $value["name_function"] . "***" . $value["name_subfunction"];

					if ($site == "all" && $nameModel == "MACHINE VIRTUELLE") {
						$keyParent = $concatFunSubfun;
					} else {
						$keyParent = $concatFunSubfun . "***" . $value["server_parent"];
					}
					$keyLog = $keyParent . "***" . $value["name_machine"];

					$oneNode = createNode($value, $keyLog);

					$oneNode[] = '"group":"' . $keyParent . '"';
					$oneNode[] = '"isGroup":true';

					if ($nameModel == "MACHINE VIRTUELLE") {
						$oneNode[] = '"category":"mv"';
						$oneNode[] = getOSColor($value["rewrited_type"]);

					} else {
						$oneNode[] = '"background":"white"';
					}

					// Mettre le texte de non db
					if ($value["name_function"] != "DATABASE") {
						$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ')"';
					}

					if ($value["name_function"] == "DATABASE") {

						$oneDB = array();

						// Nombre de db dans le log
						$database = $db -> dbms -> find(array(
							"name_department" => "prd-risques",
							"name_application" => array(
								'$in' => $allModulesOfApp
							),
							"name_machine_logical" => $value["name_machine"],
							"name_machine_physical" => $value["server_parent"]
						));

						// Si le serveur physique contient directement le db,
						//     mettre le texte (BLABLA)
						// Sinon, mettre le texte (BLABLA)
						if ($database -> count() != 0) {

							if ($nameModel == "MACHINE VIRTUELLE") {
								$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ')"';
							} else {
								$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ')"';
							}

						} else {
							$oneNode[] = '"text":"' . $value["name_machine"] . " (" . getMachineModelName($nameModel) . " " . $value["site"] . ')"';
						}
					}

					addPartToDataArray($oneNode);

					if (!in_array($keyLog . "***note", $diffNote)) {
						$diffNote[] = $keyLog;
						addServerNote($value["name_machine"], $nameApp, $keyLog, $nameEnv);
					}

				}
			}

		}

		/*------FUNCTION--------*/
		$parents = array();
		$diffFunc = array();
		//contains distinct functions (COMMENTAIRE NON-CLAIRE)
		foreach ($machines as $key => $value) {

			// Si c'est une nouvelle "function", (BLABLA)
			if (!in_array($value["name_function"], $diffFunc)) {
				$name_function = $value["name_function"];
				$diffFunc[] = $name_function;
				$parents[] = '{"key":"' . $name_function . '","text":"' . $name_function . '","isGroup":true,"font":"18px sans-serif","color":"lightGray","category":"function"}';
			}
		}

		/*-----SUB FUNCTION------*/
		$diffSubFunc = array();
		foreach ($machines as $key => $value) {
			$concatFuncSubfunc = $value["name_function"] . "***" . $value["name_subfunction"];
			//the concat distinguishes subfunctions (COMMENTAIRE NON-CLAIRE)
			if (!in_array($concatFuncSubfunc, $diffSubFunc)) {
				$diffSubFunc[] = $concatFuncSubfunc;
				$name_function = $value["name_function"];
				$name_subfunction = $value["name_subfunction"];
				$parents[] = '{"key":"' . $concatFuncSubfunc . '","text":"' . $value["name_subfunction"] . '","isGroup":true,"group":"' . $value["name_function"] . '","font":"15px sans-serif","color":"lightGray","category":"function"}';

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
function saveSchema($nameApp, $type, $dataToSave, $site = "") {
	global $db;

	if ($type == "log") {
		$db -> diagram_log -> update(
			array(
				"name_application" => $nameApp
			),
			array(
				"name_application" => $nameApp,
				"data" => json_decode($dataToSave)
			),
			array(
				"upsert" => 1
			)
		);
	} elseif ($type == "geo") {
		$db -> diagram_geo -> update(
			array(
				"name_application" => $nameApp,
				"site" => $site
			),
			array(
				"name_application" => $nameApp,
				"data" => json_decode($dataToSave),
				"site" => $site
			),
			array(
				"upsert" => 1
			)
		);

	}
}

// Créer un noeud de machine
function createNode($infoMachine, $key) {
	$oneNode = array();
	foreach ($infoMachine as $k => $v) {
		if ($k == "_id") {
			$k = "key";
			$v = $key;
		}
		$oneNode[] = '"' . $k . '":"' . $v . '"';
		//JSON format
	}
	$oneNode[] = getEnvColor($infoMachine["name_environment"]);
	return $oneNode;
}

function addServerNote($nameMachine, $nameApp, $keyParent, $nameEnv) {

	global $db;
	global $dataArray;

	$textServerNote = array();
	$relatedApps = array();
	$notRelatedApps = array();

	$serverNoteData = $db -> function -> distinct("name_application", array(
		"name_machine" => $nameMachine,
		"name_department" => "prd-risques"
	));

	// Boucler sur les notes
	foreach ($serverNoteData as $key => $value) {

		// Compter le nombre de parents
		$nbParents = $db -> app_module -> find(array(
			"name_application" => $nameApp,
			"name_application_parent" => $value
		)) -> count();

		// Compter le nombre de fils
		$nbChildren = $db -> app_module -> find(array(
			"name_application_parent" => $nameApp,
			"name_application" => $value
		)) -> count();

		if ($nameApp != $value) {
			if ($nbChildren || $nbParents) {
				$relatedApps[] = $value;
			} else {
				$notRelatedApps[] = $value;
			}
		}

	}

	foreach ($relatedApps as $key => $value) {
		$textServerNote[] = $value;
	}
	if (count($relatedApps) != 0 || count($notRelatedApps) != 0) {
		$textServerNote[] = '---';
	}

	foreach ($notRelatedApps as $key => $value) {
		$textServerNote[] = $value;
	}
	if (count($relatedApps) != 0 || count($notRelatedApps) != 0) {
		$serverNote = '{"text":"' . implode('\n', $textServerNote) . '","group":"' . $keyParent . '","category":"serverNote","key":"' . $keyParent . '***note","name_environment":"' . $nameEnv . '"}';
		$dataArray[] = $serverNote;
	}
}

// Ajouter un noeud ou une db dans le DataArray
function addPartToDataArray($onePart) {
	global $dataArray;
	$st = "{" . implode(",", $onePart) . "}";
	$dataArray[] = $st;
}

// Créer une DB
function createDB($infoDb, $key, $keyParent) {
	$oneDB = createNode($infoDb, $key);
	$oneDB[] = '"isGroup":false';
	$oneDB[] = '"group":"' . $keyParent . '"';

	$oneDB[] = '"category":"db"';
	$oneDB[] = getDBcolor($infoDb["techno"]);
	$text_db = $infoDb["name"] . ' ' . $infoDb["instname"] . '\n' . $infoDb["techno"];
	$oneDB[] = '"text":"' . $text_db . '"';

	return $oneDB;
}

// Renvoyer la couleur pour l'environement
function getEnvColor($nameEnv) {
	if ($nameEnv == "Dev") {
		return '"color":"#FFB3FF"';
	} elseif ($nameEnv == "Rec") {
		return '"color":"#CCCCB2"';
	} elseif ($nameEnv == "Qualif") {
		return '"color":"lightBlue"';
	} elseif ($nameEnv == "Bench") {
		return '"color":"orange"';
	} elseif ($nameEnv == "Prod") {
		return '"color":"#86CB8F"';
	} else {
		return '"color":"white"';
	}
}

// Renvoyer la couleur pour l'OS
function getOSColor($rewritedType) {
	if ($rewritedType == "Windows") {
		return '"background":"#D1F0FF"';
	} elseif ($rewritedType == "Linux") {
		return '"background":"#C299FF"';
	} elseif ($rewritedType == "Solaris") {
		return '"background":"#FFA3A3"';
	} elseif ($rewritedType == "Vmware ESX" || $rewritedType == "VMware ESXi") {
		return '"background":"#DBFFDB"';
	} else {
		return '"background":"white"';
	}
}

// Renvoyer le sigle de machine_model
function getMachineModelName($nameModel) {
	global $sigleModel;

	if (array_key_exists($nameModel, $sigleModel)) {
		return $sigleModel[$nameModel];
	} else {
		return $nameModel;
	}
}

// Renvoyer la couleur pour la DB
function getDBcolor($techno) {
	if (strpos($techno, "Sybase") !== false) {
		return '"color":"lightGray"';
	} elseif (strpos($techno, "Oracle") !== false) {
		return '"color":"#CC80E6"';
	} elseif (strpos($techno, "Sql") !== false) {
		return '"color":"#5EA2CF"';
	} else {
		return '"color":"white"';
	}
}

$conn -> close();

// END createSchema.php
