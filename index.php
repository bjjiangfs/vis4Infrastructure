<?php
require_once 'Menu.Class.php';

$conn = new MongoClient('mongodb://localhost');
$db = $conn -> COCKPIT;
$collection = $db -> application;
$cursor = $collection -> distinct("name_application_parent", array("name_department" => "prd-risques"));

$conn -> close();
?>
<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="site_schema">
		<meta name="author" content="PR2">

		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
		<script src="js/jquery-ui.js"></script>

		<!-- Optional theme -->
		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">

		<!-- Latest compiled and minified JavaScript -->
		<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>

		<!--CSS for main page-->
		<link href="css/main.css" rel="stylesheet">

		<title>Architecture</title>

		<script src="js/go.js"></script>
		<script src="js/architecture.js"></script>

		<link href="css/autocomplete.css" rel="stylesheet">

	</head>
	<?php
	if (isset($_GET["name_application"])) {
		$name_application = str_replace(" ", "%20", $_GET["name_application"]);
	}
	?>
	<body role="document" onload=init("<?php echo $name_application; ?>")>
		<!-- Fixed navbar -->
		<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
			<div class="container">

				<div class="navbar-header">
					<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" ><img id="logo" src="img/logo-natixis.jpg"/>&nbsp;&nbsp;&nbsp;</a>
				</div>

				<div class="navbar-collapse collapse">
					<ul class="nav navbar-nav">
						<?php
						// pass the current module as parameter, so that the current tag has an active effect
						$menu = new Menu("Architecture");
						echo $menu -> createMenu();
						?>
					</ul>
					<ul class="nav navbar-nav pull-right">
						<li class="dropdown">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">APP <span class="caret"></span></a>
							<ul class="dropdown-menu scrollable-menu" role="menu">
								<?php
								$li = "";
								foreach ($cursor as $key => $value) {
									$li .= '<li><a href="index.php?name_application=' . $value . '">' . $value . '</a></li>';
								}
								echo $li;
								?>
							</ul>
						</li>
						<li>
							<form class="navbar-form navbar-right pull-right" role="form">
								<div class="form-group" id="divInputApp">
									<input id="inputApp" class="typeahead" type="text" placeholder="name_application" onkeypress="checkEnter(event)">
								</div>
								<span class="btn btn-success" onclick=goToApp()> Go </span>
							</form>
						</li>
					</ul>

				</div><!--end nav-collapse -->

			</div>
		</div><!--end nav-bar -->
		</div><!--end nav-bar -->

		<div id="MainContent" class="container theme-showcase" role="main">

			<!------Navigation bar under the menu bar-------->
			<div class="row" id="title">

				<div class="alert alert-warning" style="padding:10px;margin-bottom: 10px">
					<button id="saveSVG" class="btn btn-sm btn-default" onclick="makeImage()">
						<span class="glyphicon glyphicon-camera"></span>
					</button>
					<button id="collapse" onclick="collapse()" class="btn btn-sm btn-default" disabled style="width: 70px">
						Collapse
					</button>
					<button id="expand" onclick="expand()"  class="btn btn-sm btn-default"  style="display:none;width: 70px">
						Expand
					</button>
					<button id='zoomFit' class="btn btn-sm btn-default" onclick="zoomToFit()"/ disabled>
						Zoom to Fit (Shift+Z)
					</button>

					<div class="btn-group" data-toggle="buttons">
						<label class="btn btn-sm btn-default active" onclick="logView()">
							<input  type="radio" name="view" id="log">
							<span class="glyphicon glyphicon-link"></span> Logique </label>
						<label class="btn btn-sm btn-default" onclick="geoView()">
							<input type="radio"  name="view" id="geo" >
							<span class="glyphicon glyphicon-globe"></span> Géographique </label>
					</div>

					<button id='hideNote' class="btn btn-sm btn-default" onclick="hideNote()"/ disabled style="width: 70px">
						Hide
					</button>
					<button id='showNote' class="btn btn-sm btn-default" onclick="showNote()"/ disabled style="display:none;width: 70px">
						Show
					</button>

					<button id='hideNote' class="btn btn-sm btn-default" onclick="changeLayout()"/ disabled style="width: 70px">
						Layout
					</button>

					<span id="relatedApps"></span>
					<span id="current_app" style="margin-left:10px;font-weight: bold;"></span>
					<button id="SaveButton"  class='pull-right btn btn-sm btn-success ' onclick="save()" disabled>
						Sauvegarder
					</button>

				</div>

			</div>
			<div class="row">
				<form class="form-inline col-md-5" role="form" style="padding-left: 0">

					<div id="mode_legende" ></div>

				</form>
				<form class="form-inline col-md-7" role="form" style="padding-left: 0">
					<div id="env_filter" ></div>
				</form>
			</div>

			<div class="row">

				<div id="myDiagram" style="border: solid 1px black; width:100%; height:700px" onclick='activeDiagram(this.id)'></div>

				<div id="myGeoDiagram" style="visibility:hidden;width:100%; height:700px"></div>
			</div>

			<div id="local" style="position:fixed;right: -345px;height: 300px;bottom: 0px;z-index: 10">

				<div style="border-bottom-left-radius:6px;border-top-left-radius:6px;width:20px;height:50%;background:#464646;float:left;border: solid 1px black;">

					<div style="height: 20%;">
						<img id="fleche_gauche" src="img/fleche_gauche.png" style="cursor:pointer"/>
					</div>
					<div style="height: 20%;">
						<img id="fleche_droite" src="img/fleche_droite.png" style="cursor:pointer"/>
					</div>

					<div onclick="toggleLocal()" style="height: 60%;">

					</div>

				</div>

				<div id="myLocalDiagram" onclass="slide-out-div"style="float:right;width:350px;height: 300px;background:white;border: solid 1px black;"></div>
			</div>

			<input type="hidden" id="activeDiagram"/>
			</textarea-->
			<div  class="modal fade" id="infoModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
				<div class="modal-dialog " style="padding-top: 15%;  ">
					<div class="modal-content ">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-hidden="true">
								&times;
							</button>
							<h4 class="modal-title" id="myModalLabel">Info.</h4>
						</div>
						<div class="modal-body scrollable-menu ">

						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">
								Fermer
							</button>
						</div>
					</div>
				</div>
			</div>

		</div>
	</body>
</html>
