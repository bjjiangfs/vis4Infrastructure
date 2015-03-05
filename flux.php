<?php
require_once 'Menu.Class.php';
?>
<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="site_schema">
		<meta name="author" content="PR2">

		<!--Bootstrap CSS-->
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap-theme.min.css">

		<!--CSS for main page-->
		<link href="css/main.css" rel="stylesheet">

		<title></title>
		<!-- Copyright 1998-2014 by Northwoods Software Corporation. -->

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
		<script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
		<script src="js/go.js"></script>
		<script src="js/flux.js"></script>
		<style>
			.scrollable-menu {
				height: auto;
				max-height: 200px;
				overflow-x: hidden;
			}
		</style>
	</head>
	<body role="document" onload="init()">

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
					<a class="navbar-brand" href="#"><img id="logo" src="img/logo-natixis.jpg"/>&nbsp;&nbsp;&nbsp;</a>
				</div>

				<div class="navbar-collapse collapse">
					<ul class="nav navbar-nav">
						<?php
						$menu = new Menu("Flux");
						echo $menu -> createMenu();
						?>
					</ul>
				</div><!--end nav-collapse -->

			</div>
		</div><!--end nav-bar -->

		<div id="MainContent" class="container theme-showcase" role="main">
			<div class="row">
				<div id="myDiagram" style="border: solid 1px black; width:100%; height:700px" ></div>
			</div>
		</div>
		<!-- end container -->
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
	</body>
</html>