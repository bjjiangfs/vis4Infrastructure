#!/bin/bash 
#
#
#
# Objet : moulinette de récupération des référentiels IT
# 
# - Cockpit
# - Hypervision
# - Control M 
# - costream 
#

######################################
# VARS
######################################


BASE=$( dirname $0 ) 
MONGOIMPORT=mongoimport
MONGO=mongo
DB=COCKPIT

DIR_TMP=/tmp/pimp
FIL_PRE=Api_

dcApp="XCTRR	BX_B2C
XCTRR	AJ_JADE
XCTRR	CL_CIRCE
XCTRR	AM_AMERISC
XCTRR	FE_FERMAT_MARCHE
XCTRR	FG_FERMAT_CONSO
"


######################################
# FUNCT
######################################

function Log {
	TIME=$( date "+%H:%M:%S" ) 
	DATE=$( date "+%Y-%m-%d" ) 
	echo "$@" | sed  's/^/'${DATE}'	'${TIME}'	/'
}

function ErrLog {
	Log $( echo $@ | sed 's/^/ERR:/') 
}

function getCockpit {
        #       print "ON AJOUTE :: NF = " NF " tf=" tf;
[[ -z $1 ]] && exit 0
wget -qO- "$1" | iconv -f ISO-8859-1 -t UTF-8 | awk ' BEGIN{ FS=";"; OFS="\t"; }
NR==1{ but=NF; tl=""; tf=0; }
{
        if ( NF != but ) {
                tl=tl$0;
                tf=NF+tf;
        }else{
                if ( tl != "" && tf != but  ) { print "ERR : houston tf="tf" tl="tl; exit 1 ; }
                tl=$0;
                tf=NF;
        }
        if ( tf >= but ) {
                gsub( "\t" , "" , tl);
                gsub( FS , OFS , tl);
                gsub( "\"" , "" , tl);
                print tl;
                tl=""; tf=0;
        }
} '
}


######################################
# MAIN
######################################

Log "Start your engine .... " 
Log "Delete DB $DB"

echo "show dbs " | $MONGO 2>&1 1>/dev/null  || { ErrLog  "connexion impossible" ; exit 1; } 
echo "db.dropDatabase();" |  $MONGO $DB || { ErrLog " impossible de droper DB"; exit 1 ; } 

Log "Processing Costream ... " 
#wget -q -O $DIR_TMP/costream.csv  "http://costream/CoStream/public/Costream_Stream/exportCsv/" 
cp data/costream/costream.csv  /tmp/pimp/costream.csv
	$MONGOIMPORT -d $DB -c costream  --type tsv --headerline --drop /tmp/pimp/costream.csv

Log "Processing cockpit..." 
for apiname in Api_Dbms Api_General Api_Server Api_Application Api_Function Api_Equipment
do
#continue
		FIL_OUTPUT_NAME=$( echo $apiname | sed 's/'$FIL_PRE'//' | tr '[A-Z]' '[a-z]' ) 
        Log "get  $apiname ..."
        getCockpit "http://cockpit.cib.net/$apiname/csv"  > $DIR_TMP/$FIL_OUTPUT_NAME 
		Log "Import $DIR_TMP/$FIL_OUTPUT_NAME" 
		$MONGOIMPORT -d $DB -c $FIL_OUTPUT_NAME --type tsv --headerline --drop $DIR_TMP/$FIL_OUTPUT_NAME

done

Log "Processing hypervision... " 
echo "$dcApp" | while read DC	APP
do 
	FIL_OUTPUT_NAME=HYPER_$DC_$APP	
	echo "$DC -> $APP" 
	wget -q -O $DIR_TMP/$FIL_OUTPUT_NAME "http://hypervision.smt.cib.net/controlm/controlm_detail.php?server=${DC}&application=${APP}&export=csv" 
	cat $DIR_TMP/$FIL_OUTPUT_NAME | iconv -f iso-8859-1 -t UTF-8 |  awk ' BEGIN{ FS=";"; OFS="\t"; }{ 
		tl=$0; 
		gsub( "\t" , "" , tl);
        gsub( FS , OFS , tl);
        gsub( "\"" , "" , tl);
        print tl;
				
			}' > $DIR_TMP/$FIL_OUTPUT_NAME.toImport
	$MONGOIMPORT -d $DB -c $FIL_OUTPUT_NAME  --type tsv --headerline --drop $DIR_TMP/$FIL_OUTPUT_NAME.toImport


done 





Log "Processing NFS..." 
echo "server;client;mountpoint" > $DIR_TMP/nfs
echo "msnfsp06
msnfsp07
msnfsp08" | cut -d ':' -f1 | while read line ; do  showmount --no-headers -a $line | sed 's/^/'$line':/' ; done | sort | uniq | sed 's/:/	/g' >> $DIR_TMP/nfs

	$MONGOIMPORT -d $DB -c nfs  --type tsv --headerline --drop $DIR_TMP/nfs

exit 0 

$MONGOIMPORT -d $DB -c serveur  --type tsv --headerline --drop /data/cockpit/getAllSrv.sql.csv 
$MONGOIMPORT -d $DB -c serveurDetail  --type tsv --headerline --drop /data/cockpit/getAllSrvFull.sql.csv 
#$MONGOIMPORT -d $DB -c serveurApi  --type csv --headerline --drop /data/cockpit/cockpit_serveurs.api.csv
#$MONGOIMPORT -d $DB -c mac  --type tsv --headerline --drop /data/cockpit/getAllMac.sql.csv
$MONGOIMPORT -d $DB -c mac  --type tsv --headerline --drop /data/cockpit/getAllMacNew.sql.csv
$MONGOIMPORT -d $DB -c zones  --type tsv --headerline --drop /data/cockpit/netzones.wget.csv
$MONGOIMPORT -d $DB -c sgbd  --type tsv --headerline --drop /data/cockpit/sgbd.wget.csv
$MONGOIMPORT -d $DB -c tmt  --type tsv --headerline --drop /data/cockpit/getAllTmt.sql.csv
$MONGOIMPORT -d $DB -c app  --type tsv --headerline --drop /data/cockpit/getAppSrv.sql.csv
$MONGOIMPORT -d $DB -c wwn  --type tsv --headerline --drop /data/cockpit/getAllWwn.sql.csv
#$MONGOIMPORT -d $DB -c drp_clusters  --type tsv --headerline --drop /data/cockpit/drpgpfs.csv
#$MONGOIMPORT -d $DB -c erratas  --type tsv --headerline --drop /data/cockpit/erratas.csv




	echo 'db.serveurDetail.ensureIndex( { "$**": "text" },  { name: "TextIndex" }  );' |  $MONGO $DB || { print -u2 " Err construction index "; exit 1 ; } 

