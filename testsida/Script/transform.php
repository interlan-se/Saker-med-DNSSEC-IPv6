<?php

	$datum = $argv[1];

	/*
		***********************
		* Soekvaegar till filer *
		***********************
	*/
	// innehaaller domaener i en lista. Varje rad innehaaller hostnamn och kontaktinfo samt om den aer rekursiv samt lista av dnser till den och antal fel och warnings
	$resultatPath = "results/" . $datum . ".txt";	//  serversoekvaeg: /home/kommunermeddnssec/resultat

	// Haer finns output fraan dnscheck
	$errorPath = "history/" . $datum . "/"; 			// konkatenera med host. Innehaaller ERROR och WARNINGs samt INFO.. maska ut raett..


	// finns hostnamnet i denna fil saa har den ipv6 paa dns
	$ipDns6Path = "kipv6/script/truedns6"; 		// 	/kipv6/script/history/[datum]/dns6 = textfil med domaener som har v6

	// finns hostnamnet i denna fil saa har den ipv6 paa mx
	$ipMx6Path = "kipv6/script/truemx6"; 		// 	/kipv6/script/history/[datum]/mx6 = textfil med domaener som har v6

	// finns hostnamnet i denna fil saa har den ipv6 paa www
	$ipWww6Path = "kipv6/script/truewww6"; 		// 	/kipv6/script/history/[datum]/www6 = textfil med domaener som har v6

	/*
		***********
		* Helpers *
		***********
	*/
	function formatEmail($email){
		if($email == "") return "";
		$email = str_replace("\\.", "�", $email);

		if( strpos($email, '\\@') > 0){
			$email = str_replace("\\@", " [at] ", $email);
		}
		else{
			$firstDot = strpos($email, '.');
			$email = substr($email, 0, $firstDot) . " [at] " . substr($email, $firstDot + 1);
		}

		return str_replace("�", ".", $email);
	}

	function removeEndingDot($str){
		if( strlen($str) > 0 && $str[ strlen($str)-1 ] == '.'){
			return substr($str, 0, -1);
		}
		return $str;
	}

	function lineIsInFile( $filePath, $ln){
		if( file_exists( $filePath ) ){
			$fileContent = str_getcsv( file_get_contents($filePath), "\n" );
			$rowCount = count($fileContent);
			for($i=0; $i<$rowCount; $i++){
				if( trim($fileContent[$i]) == $ln ) return true;
			}
		}
		return false;
	}

	/*
		*****************
		* Main methods  *
		*****************
	*/

	function createOutput($dateOfIntrest){
		$nl = "\n";

		// header
		echo '{' . $nl;
		echo '	"municipalities": {' . $nl;
		echo '		"municipality": [' . $nl;


		$csv =  file_get_contents("komdom.csv");
		$rows = str_getcsv( $csv, "\n");
		$lastRow = count($rows);

		// content
		for( $i=0; $i<$lastRow; $i++){

			$row = str_getcsv( $rows[$i], ";" );

			$kommunkod = $row[0];
			$namn = htmlentities($row[1], ENT_QUOTES | ENT_IGNORE, "UTF-8");
			$url = $row[2];

			echo '			{' . $nl;
			echo formatSingleKommun($kommunkod, $namn, $url, $dateOfIntrest);
			echo '			}';

			if( $i < ($lastRow - 1) ) echo ",";
			echo $nl;
		}

		// footer
		echo '		]' . $nl;
		echo '	}' . $nl;
		echo '}' . $nl;

	}


	function formatSingleKommun($kod, $namn, $host, $dateOfIntrest){
		$nl = "\n";

		$tab1 = "\t\t\t\t";
		$tab2 = "\t\t\t\t\t";
		$back = '';

		$back .= $tab1 . '"knnr": "' . $kod . '",' . $nl;
		$back .= $tab1 . '"name": "' . $namn . '",' . $nl;
		$back .= $tab1 . '"url": "' . $host . '",' . $nl;

		$back .= getKommunDataFromFiles($host, $dateOfIntrest);

		return $back;
	}


	function getKommunDataFromFiles($host, $dateOfIntrest){

		/*
			VARIABLER
		*/
		global $errorPath;
		global $resultatPath;
		global $ipWww6Path;
		global $ipDns6Path;
		global $ipMx6Path;

		$back = "";

		$nl = "\n";
		$tab1 = "\t\t\t\t";
		$tab2 = "\t\t\t\t\t";

		$fileContent = array(); // mellanlagring av filinnehaall
		$rowCount = 0; // antal rader i filer.

		$contactEmail = "FEL";	// Kaella: resultatPath
		$dnsSecSigned = false;	// Kaella: resultatPath
		$isRecursice = false;	// Kaella: resultatPath

		$ipWww = false;			// Kaella: ipWww6Path
		$ipDns = false;			// Kaella: ipDns6Path
		$ipMail = false;		// Kaella: ipMx6Path

		$arrDns = array();		// Kaella: resultatPath
		$arrErr = array();		// Kaella: errorPath
		$arrWarn = array();		// Kaella: errorPath

		/*
			GE VARIABLER VAERDEN
		*/

		// 1. resultat som kaella
		$fileContent = str_getcsv( file_get_contents($resultatPath), "\n" );
		$rowCount = count($fileContent);

		for($i=0; $i<$rowCount; $i++){
			if( strpos( $fileContent[$i], $host) === 0){ // rad funnen
				$items = str_getcsv( $fileContent[$i], ",");

				$dnsSecSigned = $items[1] == "yes";
				$isRecursice = $items[2] == "yes";
				$contactEmail = formatEmail(removeEndingDot($items[5]));

				for($j=6; $j<count($items); $j++){
					if( trim($items[$j]) != "" ){
						array_push( $arrDns, removeEndingDot($items[$j]));
					}
				}

				break;
			}
		}

		// 2. ip-info som kaella
		$ipWWW = lineIsInFile( $ipWww6Path, $host );
		$ipDns = lineIsInFile( $ipDns6Path, $host );
		$ipMail = lineIsInFile( $ipMx6Path, $host );

		if( !file_exists($ipWww6Path) || !file_exists($ipDns6Path) || !file_exists($ipMx6Path) ){
			$contactEmail .= '<br/><br/><strong>IPv6 data could not be retrived</strong><br/>';
		}


		// 3. error som kaella.
		$fileContent = str_getcsv( file_get_contents($errorPath . $host), "\n" );
		$rowCount = count($fileContent);
		for($i=0; $i<$rowCount; $i++){
			$ln = trim( $fileContent[$i] );

			$warnPos = strstr($ln, "WARNING");
			$errPos = strstr($ln,  "ERROR");

			if( $warnPos !== false){
				array_push( $arrWarn, substr($warnPos, 8));
			}
			if( $errPos !== false){
				array_push( $arrErr, substr($errPos, 6));
			}
		}

		/*
			SKAPA UTDATA
		*/

		// lista av vaerden
		$back .= $tab1 . '"contact": "' . $contactEmail . '",' . $nl;
		$back .= $tab1 . '"dnsSecSigned": ' . ($dnsSecSigned?"true":"false") . ',' . $nl;
		$back .= $tab1 . '"isRecursive": ' 	. ($isRecursice?"true":"false") . ',' . $nl;

		$back .= $tab1 . '"ipWww": ' 		. ($ipWWW?"true":"false") . ',' . $nl;
		$back .= $tab1 . '"ipDns": ' 		. ($ipDns?"true":"false") . ',' . $nl;
		$back .= $tab1 . '"ipMail": ' 		. ($ipMail?"true":"false") . ',' . $nl;

		// lista av dns, error och warnings
		$back .= $tab1 . '"dnsList": [' . $nl;
		$maxRows = count($arrDns);
		for($i=0; $i<$maxRows; $i++){
			$back .= $tab2 . '{"name": "' . $arrDns[$i] . '"}';
			if( $i < $maxRows - 1) $back .= ',';
			$back .= $nl;
		}
		$back .= $tab1 . '],' . $nl;

		$back .= $tab1 . '"errors": [' . $nl;
		$maxRows = count($arrErr);
		for($i=0; $i<$maxRows; $i++){
			$back .= $tab2 . '{"description": "' . str_replace("\\", "\\\\", $arrErr[$i]) . '"}';
			if( $i < $maxRows - 1) $back .= ',';
			$back .= $nl;
		}
		$back .= $tab1 . '],' . $nl;

		$back .= $tab1 . '"warnings": [' . $nl;
		$maxRows = count($arrWarn);
		for($i=0; $i<$maxRows; $i++){
			$back .= $tab2 . '{"description": "' . str_replace("\\", "\\\\", $arrWarn[$i]) . '"}';
			if( $i < $maxRows - 1) $back .= ',';
			$back .= $nl;
		}
		$back .= $tab1 . ']' . $nl;

		/*
			GE TILLBAKA RESULTAT
		*/
		return $back;
	}

	// START METHOD:
	createOutput($datum);

?>
