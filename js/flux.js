function showLinkDetail(link) {

	$("#infoModal .modal-body").empty().html(nodeInfo(link.data).replace(/\n/g, "<br/>"));
	$("#infoModal").modal("show");
}

function goToSchemaLog(node) {
	var name_app = node.data.text;
	$.ajax({
		type : "POST",
		url : "functions/checkAppExist.php",
		async : false,
		data : {
			app : name_app
		},
		success : function(result) {

			if (result == true) {
				window.location.href = "index.php?name_application=" + name_app;
			} else {
				alert("Il n'y a pas de schéma pour cette appli");
			}
		}
	});
}

function init() {
	var GO = go.GraphObject.make;
	myDiagram = GO(go.Diagram, "myDiagram", // create a Diagram for the DIV HTML element
	{
		initialContentAlignment : go.Spot.Center, // center the content
		"toolManager.mouseWheelBehavior" : go.ToolManager.WheelZoom,
		initialAutoScale : go.Diagram.Uniform,
		layout : GO(go.ForceDirectedLayout, {
			defaultSpringLength : 50,
			defaultElectricalCharge : 100, // currently best config for layout
			maxIterations : 1000,
			arrangementSpacing : new go.Size(20, 20)
		})

	});

	// define a simple Node template
	myDiagram.nodeTemplate = GO(go.Node, "Auto", {
		locationSpot : go.Spot.Center,
		doubleClick : function(e, node) {
			goToSchemaLog(node);
		}
	}, GO(go.Shape, "RoundedRectangle", {
		fill : "white",
		name : "OBJSHAPE"
	}, new go.Binding("fill", "color")), GO(go.TextBlock, {
		margin : 4, // make some extra space for the shape around the text
		isMultiline : false, // don't allow newlines in text
		editable : false,
		font:"bold 20pt serif"
	}, // allow in-place editing by user
	new go.Binding("text", "text")));

	myDiagram.linkTemplate = GO(go.Link, {
		curve : go.Link.Bezier,
		doubleClick : function(e, link) {
			showLinkDetail(link);
		}
	}, GO(go.Shape, new go.Binding("strokeWidth", "thick"), {
		name : "OBJSHAPE",
		isPanelMain : true
	}), GO(go.Shape, new go.Binding("strokeWidth", "thick"), {
		name : "ARWSHAPE",
		toArrow : "Standard"
	}));

	// create the model data that will be represented by Nodes and Links
	$.ajax({
		url : "functions/getFlux.php",
		async : false,
		success : function(result) {
			//console.log(result);
			var tmp = result.split("#*@+-");
			var dataArray = tmp[0];
			var linkArray = tmp[1];
			myDiagram.model = new go.GraphLinksModel($.parseJSON(dataArray), $.parseJSON(linkArray));
		}
	});

	myDiagram.addDiagramListener("ChangedSelection", function() {
		updateHighlights();
	});

}

function updateHighlights() {
	myDiagram.nodes.each(function(node) {
		node.highlight = 0;
	});
	myDiagram.links.each(function(link) {
		link.highlight = 0;
	});

	var sel = myDiagram.selection.first();
	if ( sel instanceof go.Node) {
		console.log("its a node");
		linksTo(sel, 1);
		linksFrom(sel, 2);
	}
	myDiagram.links.each(function(link) {
		var hl = link.highlight;
		var shp = link.findObject("OBJSHAPE");
		var arw = link.findObject("ARWSHAPE");
		highlight(shp, arw, hl);
	});
}

function highlight(shp, obj2, hl) {
	var color;

	if (hl === 0) {
		color = "black";
	} else if (hl === 1) {
		color = "blue";
	} else if (hl === 2) {
		color = "green";
	}
	shp.stroke = color;
	if (obj2 !== null) {
		obj2.stroke = color;
		obj2.fill = color;
	}

}

function linksTo(x, i) {
	if ( x instanceof go.Node) {
		x.findLinksInto().each(function(link) {
			link.highlight = i;
		});
	}
}

// if the link comes from this node, highlight it
function linksFrom(x, i) {
	if ( x instanceof go.Node) {
		x.findLinksOutOf().each(function(link) {
			link.highlight = i;
		});
	}
}

function nodeInfo(d) {// Tooltip info for a node data object

	var str = "";
	for (key in d) {
		if (key !== '__gohashid' && key !== 'id')
			str += key + ' : ' + d[key] + '\n';
	}
	return str;
}