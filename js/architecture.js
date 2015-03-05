/*
* 		Variables globales
*/
//myLocalDiagram est la loupe
var myDiagram, myGeoDiagram = {};
//, myLocalDiagram;
//current_app est l'appli actuellement ouverte
var current_app, array_sites = [], nb_site;
//hasGeo indique si les diagrammes Geo sont déjà calculés ou pas.
var hasGeo = false;
//raccourci pour le constructeur du plugin
var GO = go.GraphObject.make;

var num_layout = 2;
function changeApp(name_app) {
	name_app = name_app.replace(/%20/g, " ");
	current_app = name_app
	$("#current_app").html(current_app);

	$.ajax({
		url : "functions/getRelatedApps.php",
		async : false,
		data : "app=" + current_app,
		success : function(result) {
			$("#relatedApps").html(result);
		}
	});
	$.ajax({
		url : "functions/getFilters.php",
		async : false,
		data : {
			app : current_app
		},
		success : function(result) {
			var filter = result.split("***");
			$("#mode_legende").empty().html(filter[0]);
			$("#env_filter").empty().html(filter[1]);
		}
	});

	$.ajax({
		url : "functions/getSites.php",
		async : false,
		data : {
			app : current_app
		},
		success : function(result) {
			var sites = result.split("***");
			nb_site = sites[0];
			array_sites = [];
			for (var i = 1; i <= nb_site; i++) {
				array_sites.push(sites[i]);
			}
		}
	});
	clearSchema();
	createSchema(current_app, array_sites);
	logView();
	$("#title button").removeAttr("disabled");

}

function init(app) {
	if ( typeof app !== 'undefined' && app != "") {
		changeApp(app);
	}
}

function getSchema(app) {
	var nodeDataArray, linkDataArray;
	var envChecked = ["Prod"];

	var rawLinkData = $.ajax({
		url : "functions/getLinkData.php",
		async : false,
		data : {
			app : app
		}
	}).responseText;
	linkDataArray = $.parseJSON(rawLinkData);

	$.ajax({
		type : "POST",
		url : "functions/getSchema.php",
		async : false,
		data : {
			app : app,
			env : JSON.stringify(envChecked),
			type : "log"
		},
		success : function(result) {

			myDiagram.model = new go.GraphLinksModel(JSON.parse(result), linkDataArray);
		}
	});
}

function makeImage() {
	myDiagram.makeImageData();
	//var myWindow = window.open("", "myWindow");
	//myWindow.document.body.appendChild(img);
}

function goToApp() {
	var name_app = $("#inputApp").val();
	window.location.href = "index.php?name_application=" + name_app;
}

function checkEnter(e) {
	if (e.keyCode == 13) {
		goToApp();
	}
}

function clearSchema() {
	$("myDiagram").html("");
	$("myGeoDiagram").html("");
}

function toggleLocal() {
	var w = myLocalDiagram.div.style.width;
	if ($('#local').hasClass("isClicked")) {
		$("#local").animate({
			right : "-" + w
		}, 300);
		$("#local").removeClass("isClicked");
	} else {
		$("#local").animate({
			right : "0px"
		}, 300);
		$("#local").addClass("isClicked");
	}
}

function changeOnLocal(node) {

	var it = myLocalDiagram.findTopLevelGroups();
	if (it.next() === true) {
		if (it.value === node) {
			goToParentOnLocal(node);
		} else {
			goToChildOnLocal(node);
		}
	} else {
		goToParentOnLocal(node);
	}

}

function goToParentOnLocal(node) {

	var activeDiagram = $("#activeDiagram").val();
	var key_parent, node_parent;
	if (node.data.group != "" && typeof node.data.group != undefined) {
		key_parent = node.data.group;
		if (activeDiagram === "myDiagram") {
			node_parent = myDiagram.findPartForKey(node.data.group);
			myDiagram.select(node_parent);
		} else {
			node_parent = myGeoDiagram[activeDiagram].findPartForKey(node.data.group);
			myGeoDiagram[activeDiagram].select(node_parent);
		}
		showLocalOnFullClick(activeDiagram);
	}
}

function goToChildOnLocal(node) {

	var activeDiagram = $("#activeDiagram").val();
	var key_parent, node_cur;

	if (activeDiagram === "myDiagram") {
		node_cur = myDiagram.findPartForKey(node.data.key);
		myDiagram.select(node_cur);
	} else {
		node_cur = myGeoDiagram[activeDiagram].findPartForKey(node.data.key);
		myGeoDiagram[activeDiagram].select(node_cur);
	}
	showLocalOnFullClick(activeDiagram);
}

function activeDiagram(activeDiagram) {
	$("#activeDiagram").val(activeDiagram);
	showLocalOnFullClick(activeDiagram)
}

function showLocalOnFullClick(activeDiagram) {
	if (activeDiagram === "myDiagram") {
		var node = myDiagram.selection.first();
	} else {
		var node = myGeoDiagram[activeDiagram].selection.first();
	}

	if (node !== null) {
		var model = new go.GraphLinksModel();
		//highlighter.location = node.location;
		if (node.data.isGroup === true) {
			var it = node.memberParts;
			model.addNodeData(node.data);
			while (it.next()) {
				model.addNodeData(it.value.data);
			}

		} else {

			model.addNodeData(node.data);
		}
		myLocalDiagram.model = model;
	}
}

function createSchema(name_app, array_sites) {

	myLocalDiagram = GO(go.Diagram, "myLocalDiagram", // create a Diagram for the DIV HTML element
	{
		initialContentAlignment : go.Spot.Center,
		initialAutoScale : go.Diagram.Uniform,
		"toolManager.mouseWheelBehavior" : go.ToolManager.WheelZoom,
		allowDelete : false
	});

	myDiagram = GO(go.Diagram, "myDiagram", // create a Diagram for the DIV HTML element
	{
		initialContentAlignment : go.Spot.Center,
		initialAutoScale : go.Diagram.Uniform,
		"toolManager.mouseWheelBehavior" : go.ToolManager.WheelZoom,
		allowDelete : true,
		layout : GO(go.LayeredDigraphLayout, {
			direction : 90,
			layerSpacing : 100
			//arrangementSpacing : new go.Size(50,50)
		})

	});
	myDiagram.addDiagramListener("Modified", function(e) {
		var button = document.getElementById("SaveButton");
		if (button)
			button.disabled = !myDiagram.isModified;

		var idx = document.title.indexOf("*");
		if (myDiagram.isModified) {
			if (idx < 0)
				document.title += "*";
		} else {
			if (idx >= 0)
				document.title = document.title.substr(0, idx);
		}
	});
	// Define the appearance and behavior for Nodes:

	// First, define the shared context menu for all Nodes, Links, and Groups.

	// To simplify this code we define a function for creating a context menu button:
	function makeButton(text, action, visiblePredicate) {
		if (visiblePredicate === undefined)
			visiblePredicate = function() {
				return true;
			};
		return GO("ContextMenuButton", GO(go.TextBlock, text), {
			click : action
		}, new go.Binding("visible", "", visiblePredicate).ofObject());
	}

	// a context menu is an Adornment with a bunch of buttons in them
	var partContextMenu = GO(go.Adornment, "Vertical", makeButton("Properties", function(e, obj) {// the OBJ is this Button
		var contextmenu = obj.part;
		// the Button is in the context menu Adornment
		var part = contextmenu.adornedPart;
		// the adornedPart is the Part that the context menu adorns
		// now can do something with PART, or with its data, or with the Adornment (the context menu)
		if ( part instanceof go.Link) {
			$("#infoModal .modal-body").empty().html(linkInfo(part.data).replace(/\n/g, "<br/>"));
			$("#infoModal").modal("show");
		} else if ( part instanceof go.Group) {
			$("#infoModal .modal-body").empty().html(groupInfo(/*contextmenu*/
			part.data).replace(/\n/g, "<br/>"));
			$("#infoModal").modal("show");
		} else {
			$("#infoModal .modal-body").empty().html(nodeInfo(part.data).replace(/\n/g, "<br/>"));
			$("#infoModal").modal("show");
		}

	}));

	function nodeInfo(d) {// Tooltip info for a node data object

		var str = "";
		for (key in d) {
			if (key !== 'loc' && key !== '__gohashid')
				str += key + ' : ' + d[key] + '\n';
		}
		return str;
	}

	// These nodes have text surrounded by a rounded rectangle
	// whose fill color is bound to the node data.
	// The user can drag a node by dragging its TextBlock label.
	// Dragging from the Shape will start drawing a new link.
	myDiagram.nodeTemplate = GO(go.Node, "Auto", {
		locationSpot : go.Spot.Center
	}, GO(go.Shape, "RoundedRectangle", {
		fill : "white",
		portId : "",
		cursor : "pointer",
		fromLinkableDuplicates : true,
		toLinkableDuplicates : true,
		fromLinkable : true,
		toLinkable : true
	}, new go.Binding("fill", "color")), GO(go.TextBlock, {
		margin : 4, // make some extra space for the shape around the text
		isMultiline : false, // don't allow newlines in text
		editable : false
	}, // allow in-place editing by user
	new go.Binding("text", "text")),
	// the label shows the node data's text
	{
		contextMenu : partContextMenu
	});

	var dbtemplate = GO(go.Node, "Auto", {
		locationSpot : go.Spot.Center,
		doubleClick : function(e, node) {
			changeOnLocal(node);
		}
	}, GO(go.Shape, "Cylinder1", {

		portId : "",
		cursor : "pointer",
		fromLinkableDuplicates : true,
		toLinkableDuplicates : true,
		fromLinkable : true,
		toLinkable : true
	}, new go.Binding("fill", "color")), GO(go.TextBlock, {
		margin : 4, // make some extra space for the shape around the text
		isMultiline : true, // don't allow newlines in text
		editable : false
	}, // allow in-place editing by user
	new go.Binding("text", "text")), // the label shows the node data's text
	{
		contextMenu : partContextMenu
	});

	var serverNote = GO(go.Node, "Auto", {
		locationSpot : go.Spot.Center,
		doubleClick : function(e, node) {
			changeOnLocal(node);
		}
	}, GO(go.Shape, "Rectangle", {
		fill : "white",
		portId : "",
		cursor : "pointer",
		fromLinkableDuplicates : true,
		toLinkableDuplicates : true,
		fromLinkable : true,
		toLinkable : true
	}, new go.Binding("fill", "color")), GO(go.TextBlock, {
		margin : 4, // make some extra space for the shape around the text
		isMultiline : true, // don't allow newlines in text
		editable : false
	}, // allow in-place editing by user
	new go.Binding("text", "text")), // the label shows the node data's text
	{
		// this context menu Adornment is shared by all nodes
		contextMenu : partContextMenu
	});

	// Define the appearance and behavior for Links:
	var templmap = new go.Map("string", go.Node);
	templmap.add("db", dbtemplate);
	templmap.add("serverNote", serverNote);
	templmap.add("", myDiagram.nodeTemplate);
	myDiagram.nodeTemplateMap = templmap;
	myLocalDiagram.nodeTemplateMap = templmap;

	function linkInfo(d) {// Tooltip info for a link data object
		return "Link:\nfrom " + d.from + " to " + d.to;
	}

	// The link shape and arrowhead have their stroke brush data bound to the "color" property
	myDiagram.linkTemplate = GO(go.Link, {
		relinkableFrom : true,
		relinkableTo : true,
		corner : 10,
		//curviness : 20,
		routing : go.Link.AvoidsNodes
	}, GO(go.Shape, {
		strokeWidth : 2
	}), GO(go.Shape, {
		toArrow : "Standard",
		stroke : null
	}), GO(go.TextBlock, {
		font : "18px sans-serif",
		editable : true,
		text : "...",
		segmentOffset : new go.Point(-10, -10)
	}, new go.Binding("text", "text").makeTwoWay()), {
		contextMenu : partContextMenu
	});
	myLocalDiagram.linkTemplate = myDiagram.linkTemplate;
	function groupInfo(d) {// takes the tooltip, not a group node data object

		var str = "";
		for (key in d) {
			if (key !== '__gohashid')
				str += key + ' : ' + d[key] + '\n';
		}
		return str;
	}

	// Groups consist of a title in the color given by the group node data
	// above a translucent gray rectangle surrounding the member parts
	myDiagram.groupTemplate = GO(go.Group, go.Panel.Auto, {
		doubleClick : function(e, node) {
			changeOnLocal(node);
		}
		// layout:GO(go.GridLayout,{spacing:new go.Size(5,5),wrappingColumn:4,alignment:go.GridLayout.Position})
	}, new go.Binding("background", "background"), GO(go.Panel, go.Panel.Vertical, //title  and content  are vertical
	GO(go.Panel, go.Panel.Horizontal, // button and textbloc are horizontal
	{
		stretch : go.GraphObject.Horizontal,
		background : "white"
	}, new go.Binding("background", "color"), GO("SubGraphExpanderButton", {
		alignment : go.Spot.Right,
		margin : 2
	}), GO(go.TextBlock, {
		alignment : go.Spot.Left,
		editable : false,
		margin : 2,
		wrap : go.TextBlock.None
	}, new go.Binding("text", "text"), new go.Binding("font", "font"))), // end Horizontal Panel
	GO(go.Placeholder, {
		padding : 10,

	})), // end Vertical Panel
	GO(go.Shape, "RoundedRectangle", {
		isPanelMain : true, // the RoundedRectangle Shape is in front of the Vertical Panel
		fill : null,
		cursor : "pointer",
		portId : "",
		fromLinkable : true,
		toLinkable : true,
		fromLinkableDuplicates : true,
		toLinkableDuplicates : true,
		fromLinkable : true,
		toLinkable : true,
		spot1 : new go.Spot(0, 0, 1, 1), // the Vertical Panel is placed
		spot2 : new go.Spot(1, 1, -1, -1) // slightly inside the rounded rectangle
	}), {// this tooltip Adornment is shared by all groups
		contextMenu : partContextMenu
	});

	var mv = GO(go.Group, go.Panel.Auto, {
		doubleClick : function(e, node) {
			changeOnLocal(node);
		}
		// layout:GO(go.GridLayout,{spacing:new go.Size(5,5),wrappingColumn:4,alignment:go.GridLayout.Position})
	}, new go.Binding("background", "background"), GO(go.Panel, go.Panel.Vertical, //title  and content  are vertical
	GO(go.Panel, go.Panel.Horizontal, // button and textbloc are horizontal
	{
		stretch : go.GraphObject.Horizontal,
		background : "white"
	}, new go.Binding("background", "color"), GO("SubGraphExpanderButton", {
		alignment : go.Spot.Right,
		margin : 2
	}), GO(go.TextBlock, {
		alignment : go.Spot.Left,
		editable : false,
		margin : 2,
		wrap : go.TextBlock.None
	}, new go.Binding("text", "text"), new go.Binding("font", "font")), GO(go.TextBlock, {
		background : "yellow",
		alignment : go.Spot.Right
	})), // end Horizontal Panel
	GO(go.Placeholder, {
		padding : 10
	})), // end Vertical Panel
	GO(go.Shape, "RoundedRectangle", {
		isPanelMain : true, // the RoundedRectangle Shape is in front of the Vertical Panel
		fill : null,
		cursor : "pointer",
		portId : "",
		fromLinkable : true,
		toLinkable : true,
		fromLinkableDuplicates : true,
		toLinkableDuplicates : true,
		fromLinkable : true,
		toLinkable : true,
		spot1 : new go.Spot(0, 0, 1, 1), // the Vertical Panel is placed
		spot2 : new go.Spot(1, 1, -1, -1) // slightly inside the rounded rectangle
	}), {// this tooltip Adornment is shared by all groups
		contextMenu : partContextMenu
	});

	myLocalDiagram.groupTemplate = myDiagram.groupTemplate;
	var templGrpMap = new go.Map("string", go.Group);
	templGrpMap.add("mv", mv);
	templGrpMap.add("", myDiagram.groupTemplate);
	myDiagram.groupTemplateMap = templGrpMap;
	myLocalDiagram.groupTemplateMap = templGrpMap;

	function diagramInfo(model) {// Tooltip info for the diagram's model
		return "Model:\n" + model.nodeDataArray.length + " nodes, " + model.linkDataArray.length + " links";
	}

	if ( typeof name_app !== 'undefined' && name_app != "") {
		var nb_site = array_sites.length;

		var tempDiagram, divName;
		for (var i = 0; i < nb_site; i++) {
			divName = "my" + array_sites[i] + "Diagram";

			$("#myGeoDiagram").append('<div id="' + divName + '" class="col-md-6" style="border: solid 1px black; height:700px"></div>');
			tempDiagram = GO(go.Diagram, divName, // create a Diagram for the DIV HTML element
			{
				initialContentAlignment : go.Spot.Center,
				initialAutoScale : go.Diagram.Uniform,
				"toolManager.mouseWheelBehavior" : go.ToolManager.WheelZoom,
				allowDelete : false,
				allowLink : false,
				layout : GO(go.LayeredDigraphLayout, {
					direction : 90,
					layerSpacing : 100
					//arrangementSpacing : new go.Size(50,50)
				})
			});
			myGeoDiagram[divName] = tempDiagram;
			document.getElementById(divName).onclick = function() {
				activeDiagram(this.id);
			};
		}

		for (var i in myGeoDiagram) {
			myGeoDiagram[i].nodeTemplateMap = myDiagram.nodeTemplateMap;
			myGeoDiagram[i].linkTemplate = myDiagram.linkTemplate;
			myGeoDiagram[i].groupTemplateMap = myDiagram.groupTemplateMap;
		}

	}

	var fleche_gauche = document.getElementById('fleche_gauche');
	fleche_gauche.addEventListener("click", function() {
		var div = myLocalDiagram.div;
		div.style.width = $("#myLocalDiagram").width() + 100 + 'px';
		myLocalDiagram.requestUpdate();

	});

	var fleche_droite = document.getElementById('fleche_droite');
	fleche_droite.addEventListener("click", function() {
		var div = myLocalDiagram.div;
		div.style.width = $("#myLocalDiagram").width() - 100 + 'px';
		myLocalDiagram.requestUpdate();

	});

	getSchema(name_app);

}

function changeLayout() {
	if (num_layout === 1) {
		myDiagram.layout = new go.LayeredDigraphLayout();
		myDiagram.layout.direction = 90;
		myDiagram.layout.layerSpacing = 100;
		for (var i in myGeoDiagram) {
			myGeoDiagram[i].layout = new go.LayeredDigraphLayout();
			myGeoDiagram[i].layout.direction = 90;
			myGeoDiagram[i].layout.layerSpacing = 100;
		}
		num_layout = 2;
	} else if (num_layout === 2) {
		myDiagram.layout = new go.ForceDirectedLayout();
		myDiagram.layout.defaultSpringLength = 50;
		myDiagram.layout.defaultElectricalCharge = 20;
		myDiagram.layout.maxIterations = 500;

		for (var i in myGeoDiagram) {
			myGeoDiagram[i].layout = new go.ForceDirectedLayout();
			myGeoDiagram[i].layout.defaultSpringLength = 50;
			myGeoDiagram[i].layout.defaultElectricalCharge = 20;
			myGeoDiagram[i].layout.maxIterations = 500;
		}
		num_layout = 1;
	}
}


$(document).ready(function() {

	var substringMatcher = function(strs) {
		return function findMatches(q, cb) {
			var matches, substringRegex;

			// an array that will be populated with substring matches
			matches = [];

			// regex used to determine if a string contains the substring `q`
			substrRegex = new RegExp(q, 'i');

			// iterate through the pool of strings and for any string that
			// contains the substring `q`, add it to the `matches` array
			$.each(strs, function(i, str) {
				if (substrRegex.test(str)) {
					// the typeahead jQuery plugin expects suggestions to a
					// JavaScript object, refer to typeahead docs for more info
					matches.push({
						value : str
					});
				}
			});

			cb(matches);
		};
	};
	var apps;
	$.ajax({
		url : "functions/getApplications.php",
		async : false,
		success : function(result) {
			apps = JSON.parse(result);
		}
	});

	$('#divInputApp .typeahead').typeahead({
		hint : true,
		highlight : true,
		minLength : 1
	}, {
		name : 'apps',
		displayKey : 'value',
		source : substringMatcher(apps)
	});

});
function save() {
	// do this first, before writing to JSON
	//saveDiagramProperties();
	var app = $("#current_app").val();
	var str = myDiagram.model.toJson();

	var temp_array = $.parseJSON(str);
	str = JSON.stringify(temp_array["linkDataArray"]);

	$.ajax({
		url : "functions/saveLinks.php",
		async : false,
		data : "data=" + str + "&app=" + current_app,
		success : function(result) {

			console.log("success");
		}
	});
	myDiagram.isModified = false;
}

function saveDiagramProperties() {
	myDiagram.model.modelData.position = go.Point.stringify(myDiagram.position);
}

function collapse() {
	$("#collapse").hide();
	$("#expand").show();

	var topGroups = myDiagram.findTopLevelGroups();
	while (topGroups.next()) {
		var g = topGroups.value;
		g.collapseSubGraph();
	}

	for (var i in myGeoDiagram) {
		topGroups = myGeoDiagram[i].findTopLevelGroups();
		while (topGroups.next()) {
			var g = topGroups.value;
			g.collapseSubGraph();
		}
	}
}

function expand() {

	$("#collapse").show();
	$("#expand").hide();

	var topGroups = myDiagram.findTopLevelGroups();
	while (topGroups.next()) {
		var g = topGroups.value;
		g.expandSubGraph();
	}
	for (var i in myGeoDiagram) {
		topGroups = myGeoDiagram[i].findTopLevelGroups();
		while (topGroups.next()) {
			var g = topGroups.value;
			g.expandSubGraph();
		}
	}
}

function zoomToFit() {
	myDiagram.zoomToFit();
	for (var i in myGeoDiagram) {
		myGeoDiagram[i].zoomToFit();
	}
}

function filterChanged() {

	var envChecked = [];

	inputs = $("#env_filter input");
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].checked)
			envChecked.push(inputs[i].value);
	};
	$.ajax({
		type : "POST",
		url : "functions/getSchema.php",
		async : false,
		data : {
			app : current_app,
			env : JSON.stringify(envChecked),
			type : "log"
		},
		success : function(result) {
			myDiagram.model = new go.GraphLinksModel(JSON.parse(result), myDiagram.model.linkDataArray);
		}
	});
	for (var i = 0; i < nb_site; i++) {
		$.ajax({
			type : "POST",
			url : "functions/getSchema.php",
			async : false,
			data : {
				app : current_app,
				site : array_sites[i],
				type : "geo",
				env : JSON.stringify(envChecked)
			},
			success : function(result) {
				myGeoDiagram["my" + array_sites[i] + "Diagram"].model = new go.GraphLinksModel(JSON.parse(result), myDiagram.model.linkDataArray);
			}
		});
	}
}

function logView() {
	$("#log").parent().addClass("active");
	$("#geo").parent().removeClass("active");
	$("#myGeoDiagram").hide();
	$("#myDiagram").show();
}

function geoView() {
	$("#myDiagram").hide();
	$("#myGeoDiagram").css("visibility", "visible");
	$("#myGeoDiagram").show();
	var envChecked = ["Prod"];

	if (hasGeo === false) {

		for (var i = 0; i < nb_site; i++) {
			$.ajax({
				type : "POST",
				url : "functions/getSchema.php",
				async : false,
				data : "app=" + current_app + "&site=" + array_sites[i] + "&type=geo&env=" + JSON.stringify(envChecked),
				success : function(result) {

					nodeDataArray = $.parseJSON(result);
					myGeoDiagram["my" + array_sites[i] + "Diagram"].model = new go.GraphLinksModel(nodeDataArray, myDiagram.model.linkDataArray);
				}
			});
		}
		hasGeo = true;
	}

}

function prodOnly() {
	var checkbox_env = $("#env_filter input[type=checkbox]");
	for (var i = 0; i < checkbox_env.length; i++) {
		if (checkbox_env[i].value != "Prod")
			checkbox_env[i].checked = "";
		else
			checkbox_env[i].checked = "checked";
	}
	filterChanged();
}

function selectAll() {
	var checkbox_env = $("#env_filter input[type=checkbox]");
	for (var i = 0; i < checkbox_env.length; i++) {
		checkbox_env[i].checked = "checked";
	}
	filterChanged();
}

function hideNote() {
	$("#hideNote").hide();
	$("#showNote").show();
	var it = myDiagram.nodes;
	while (it.next()) {
		var n = it.value;
		if (n.data.category === "serverNote") {
			n.visible = false;
		}
	}
	for (var i in myGeoDiagram) {
		var it = myGeoDiagram[i].nodes;
		while (it.next()) {
			var n = it.value;
			if (n.data.category === "serverNote") {
				n.visible = false;
			}
		}
	}
}

function showNote() {
	$("#hideNote").show();
	$("#showNote").hide();
	var it = myDiagram.nodes;
	while (it.next()) {
		var n = it.value;
		if (n.data.category === "serverNote") {
			n.visible = true;
		}
	}
	for (var i in myGeoDiagram) {
		var it = myGeoDiagram[i].nodes;
		while (it.next()) {
			var n = it.value;
			if (n.data.category === "serverNote") {
				n.visible = true;
			}
		}
	}

}
