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
			console.log(apps);
		}
	});
	//var apps = ["AMERISC", "B2C", "Base des Operations", "Book Manager", "Book Transfert", "CIRCE", "COMITE", "Calcul de P&L", "Dossier crédit", "Dossier de Marché", "E-PEL", "E-ris - Demande d'accord", "E-ris - Dépassement", "E-ris - Limites", "E-ris - Position", "EEPE/Base Index", "ERisk", "Fermat GEM", "Fermat GEM Conso", "Fond Documentaire", "GRID DR", "GUI Analyze PL", "Hermes", "JADE - Riskpro", "Jade", "Jupiter", "MADONNE", "MATRISK", "Market Data Manager", "Maverick", "Notations internes - N.I.E.", "OSIRISK", "PETERS", "POWERAMC SDR", "Phoenix", "Pnl Hypothétique", "Portail Risques", "Portail crédit", "R2M", "RAVEL", "RM", "RMS", "Reporting Risques", "RiskShed", "SAS Server", "SCENARISK", "Sensirisk", "Simulateur ROE", "Site Intranet Métier Ratios Prudentiels", "Surveillance des défauts", "TRR", "TRR - BREF", "Trailer"];

	$('#addApp .typeahead').typeahead({
		hint : true,
		highlight : true,
		minLength : 1
	}, {
		name : 'apps',
		displayKey : 'value',
		source : substringMatcher(apps)
	});

});
function checkEnter(e) {
	if (e.keyCode == 13) {
		addApp();
	}
}

function addApp() {
	var appName = $("#inputAddApp").val();
	if ($.trim(appName) !== "") {
		$.ajax({
			url : "functions/saveExpress.php",
			async : false,
			data : "category=architecture&app=" + appName,
			success : function(result) {
				console.log("success");
				location.reload();
			}
		});
	}

}

function removeApp(str) {

	var app = str.split("_");
	appName = app[1];
	console.log(appName);
	$.ajax({
		url : "functions/removeExpress.php",
		async : false,
		data : "category=architecture&app=" + appName,
		success : function(result) {
			console.log("success");
			location.reload();
		}
	});
}
