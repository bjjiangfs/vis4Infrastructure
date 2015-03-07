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

$conn -> close();

// END main.php
