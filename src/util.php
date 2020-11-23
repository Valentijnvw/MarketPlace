<?php
// GENERAL

function startsWith($haystack, $needle) {
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function adminTaak($title,$msg,$num) {
  global $conn;
  $stmt = $conn->prepare("INSERT INTO admin_taken (datum_toevoeging,type,titel,beschrijving) VALUES (NOW(),?,?,?)");
  $stmt->bind_param("iss", $num,$title,$msg);
  $stmt->execute();
  $stmt->close();
}

// EMAILS

class Mail {
	private $mailer;
	private $mail;
	private $email;
	public $subject;
	public $message;

	function __construct($email, $name) {
		global $conf;
		$this->email = $email;
		$cred = $conf["credentials"]["info@hulpstation.nl"];
		$transport = (new Swift_SmtpTransport($cred["host"], 25))
		->setUsername($cred["username"])
		->setPassword($cred["password"])
		;
		$this->mailer = new Swift_Mailer($transport);
		$this->email = $email;
		$this->mail = (new Swift_Message())
		  ->setFrom(['info@hulpstation.nl' => 'Hulpstation'])
		  ->setTo([$email => $name])
		  ;
	}

	function klant_bevestiging($datumStart, $datumEind, $contantBetalen, $externePartij) {
		global $conf;

		$this->subject = 'Hulpstation.nl | Afspraakbevestiging en belangrijke informatie';
		$msg = file_get_contents('src/mails/hulpaanvraag-bevestiging.html');
		$msg = str_replace('%DATUM%', $datumStart->format('d-m-Y'), $msg);
		$msg = str_replace('%BEGIN-TIJD%', $datumStart->format('H:i'), $msg);
		$eindTijd = $datumEind->format('H:i');
		if($contantBetalen) {
			$eindTijd .= "<br>Betaalwijze: contant";
		}
		if($externePartij) {
			$eindTijd .= "<br>Externe partij";
		}
		$msg = str_replace('%EIND-TIJD%', $eindTijd, $msg);

		$msg = str_replace("%TARIEF_PER_KWARTIER%", str_replace(".", ",", $conf["prijzen"]["tarief_per_kwartier"]), $msg);
		$msg = str_replace("%TE_LAAT_GEANNULEERD%", str_replace(".", ",", $conf["prijzen"]["te_laat_geannuleerd"]), $msg);
		$msg = str_replace("%KORTING_PER_KWARTIER%", $conf["prijzen"]["korting_per_kwartier"] * 4, $msg);

		$this->message = $msg;
	}

	function student_bevestiging($categorie, $naamKlant, $probleemKort, $probleem, $datumStart, $datumEind, $notities, $afspraakId, $adres, $code, $telefoon, $telefoonVast) {
		$this->subject = "Hulpstation - Nieuwe afspraak";
		// general info
		$msg = file_get_contents('src/mails/afspraak-info-student.html');
		$msg = str_replace('%CATEGORY_NAME%', $categorie, $msg);
		$msg = str_replace('%NAAM_KLANT%', $naamKlant, $msg);
		$msg = str_replace('%PROBLEEM_OMSCHRIJVING_KORT%', $probleemKort, $msg);
		$msg = str_replace('%PROBLEEM_OMSCHRIJVING%', $probleem, $msg);
		$msg = str_replace('%DATUM_TIJD_AFSPRAAK%', $datumStart->format('d-m-Y \t\u\s\s\e\n H:i \e\n ') . $datumEind->format('H:i'), $msg);
		$msg = str_replace('%AFSTAND_LOCATIE%', empty($adres) ? "Afstand" : "Locatie", $msg);
		$msg = str_replace('%NOTITIES%', $notities, $msg);
		$msg = str_replace('%AFSPRAAK_ID%', $afspraakId, $msg);
		$msg = str_replace('%HULPSESSIE_CODE%', $code, $msg);
		$msg = str_replace('%HULPSESSIE_CODE_ENCODED%', urlencode($code), $msg);

		// calendar
		$calDetails = "Probleem omschrijving kort: " . htmlspecialchars($probleemKort);
		$calDetails .= "<br>Probleem omschrijving volledig: " . htmlspecialchars($probleem);
		$calDetails .= "<br>Speciale wensen / notities van de klant: " . htmlspecialchars($notities);
		$calDetails .= "<br>Hulpsessie verslag code: $code";
		$calDetails .= "<br>Hulpsessie verslag URL: https://dashboard.hulpstation.nl/verslag?code=" . urlencode($code);

		$calStartDate = clone $datumStart;
		$calStartDate->sub(new DateInterval("PT2H"));
		$calEndDate = clone $datumEind;
		$calEndDate->sub(new DateInterval("PT2H"));

		$calStart = $calStartDate->format("Ymd\THi00\Z");
		$calEnd = $calEndDate->format("Ymd\THi00\Z");
		$calUrl = "https://calendar.google.com/calendar/r/eventedit?text=" . urlencode("Hulpstation afspraak: $naamKlant") . "&ctz=Europe%2FAmsterdam&dates=$calStart/$calEnd&details=" . urlencode($calDetails);

		$vastTelStr = isset($telefoonVast) ? "<li>Vast telefoonnummer klant: $telefoonVast</li>" : "";

		if(!empty($adres)) {
			$calUrl .= "&location=" . urlencode($adres);
			$msg = str_replace('%ADRES_KLANT%', "<li>Adres klant: $adres</li><li>Telefoonnummer klant: $telefoon</li>$vastTelStr", $msg);
		} else {
			$msg = str_replace('%ADRES_KLANT%', "<li>Telefoonnummer klant: $telefoon</li>$vastTelStr", $msg);
		}
		$msg = str_replace('%CALENDAR_URL%', $calUrl, $msg);

		$this->message = $msg;
	}

	function student_geannuleerd($naamKlant, $datumStart, $datumEind) {
		$this->subject = "Hulpstation - Afspraak geannuleerd";
		$msg = file_get_contents('src/mails/afspraak-geannuleerd-student.html');
		$msg = str_replace('%NAAM_KLANT%', $naamKlant, $msg);
		$msg = str_replace('%DATUM_TIJD_AFSPRAAK%', $datumStart->format('d-m-Y \t\u\s\s\e\n H:i \e\n ') . $datumEind->format('H:i'), $msg);
		$this->message = $msg;
	}

	function student_gewijzigd($naamKlant, $afspraakId) {
		$this->subject = "Hulpstation - Afspraak gewijzigd!";
		$msg = file_get_contents('src/mails/afspraak-gewijzigd-student.html');
		$msg = str_replace('%NAAM_KLANT%', $naamKlant, $msg);
		$msg = str_replace('%AFSPRAAK_ID%', $afspraakId, $msg);
		$this->message = $msg;
	}

	function nieuwe_taak($titel, $beschrijving, $prioriteit) {
		$this->subject = "Hulpstation - Nieuwe taak";
		$msg = file_get_contents('src/mails/nieuwe-taak.html');
		$msg = str_replace('%TITEL%', $titel, $msg);
		$msg = str_replace('%BESCHRIJVING%', $beschrijving, $msg);
		$prioriteitStr = "Laag";
		if($prioriteit == 1) {
			$prioriteitStr = "Gemiddeld";
		} else if($prioriteit == 2) {
			$prioriteitStr = "Hoog";
		}
		$msg = str_replace('%PRIORITEIT%', $prioriteitStr, $msg);
		$this->message = $msg;
	}

	function survey($surveyToken) {
		$this->subject = "We horen graag uw mening!";
		$msg = file_get_contents('src/mails/survey-na-afspraak.html');
		$msg = str_replace('%SURVEY_URL%', "https://survey.hulpstation.nl/hulpsessie?token=" . $surveyToken, $msg);
		$this->message = $msg;
	}

	function log($klantnummer) {
		global $conn;
		$stmt = $conn->prepare("INSERT INTO emails (klantnummer,email,subject,message) VALUES (?,?,?,?)");
		$stmt->bind_param("ssss",$klantnummer,$this->email,$this->subject,$this->message);
		$stmt->execute();
		$stmt->close();
	}

	function send() {
		if(empty($this->subject) || empty($this->message)) {
			die("Trying to send empty mail (message not constructed properly)");
		} else {
			$this->mail->setSubject($this->subject);
			$this->mail->setBody($this->message, 'text/html');
			$result = $this->mailer->send($this->mail);
			if($result < 0) {
				die('Mailer Error');
			}
		}
	}

}

function survey_mail($afspraakId) {
	global $conn;
	$stmt = $conn->prepare("SELECT klantnummer,naam_klant,survey_token FROM afspraken WHERE id=?");
	$stmt->bind_param("i", $afspraakId);
	$stmt->execute();
	$result = $stmt->get_result();
	$stmt->close();

	$afspraakInfo;
	while($row = $result->fetch_assoc()) {
		$afspraakInfo = $row;
	}
	$contact = moneybirdApi("/contacts.json?query=" . $afspraakInfo["klantnummer"]);
	$klantInfo = $contact[0];

	$mail = new Mail($klantInfo->email, $afspraakInfo["naam_klant"]);
	$mail->survey($afspraakInfo["survey_token"]);
	$mail->log($afspraakInfo["klantnummer"]);
	$mail->send();
}

// MONEYBIRD

function moneybirdApi($path) {
	global $conf;
	$companyId = $conf["credentials"]["moneybird"]["company_id"];
	$token = $conf["credentials"]["moneybird"]["access_token"];
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://moneybird.com/api/v2/" . $companyId . $path);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$res = curl_exec($ch);
	$json = json_decode($res);
	curl_close($ch);
	return $json;
}

class Factuur {
	private $data = array(
		"document_style_id" => 166385950014833777,
		"currency" => "EUR",
		"details_attributes" => array()
  );

	function __construct($klantNummer, $inclTax, $afspraakId) {
		$contacts = moneybirdApi("/contacts.json?query=" . $klantNummer);
		if(sizeof($contacts) < 1) {
			if($_SESSION["admin"]) {
				header("Location: /admin/afspraken/ongeldig-klantnummer-verslag?afspraakId=$afspraakId");
				die();
			} else {
				die("Ongeldig klantnummer. Vraag aan de administratie om het korte hulpsessie verslag in te vullen.");
			}
		}
		$this->data["contact_id"] = $contacts[0]->id;
		$this->data["prices_are_incl_tax"] = $inclTax;
	}

	function hulpsessie($kwartieren, $kortingPerKwartier, $externePartij, $afstand) {
		global $conf;
		$lid = $kortingPerKwartier > 0;
		$minimaleAfname = 3;
		if($lid && $afstand) {
			$minimaleAfname = 2;
		}
		if($externePartij) {
			$minimaleAfname = 4;
		}
		$kwartierenCorr = $kwartieren;
		if($kwartieren < $minimaleAfname) {
			$kwartierenCorr = $minimaleAfname;
		}
		$minuten = $kwartieren * 15;
		$hulpsessie = array(
			"tax_rate_id" => 166041031534445957,
			"price" => $kwartierenCorr * $conf["prijzen"]["tarief_per_kwartier"],
			"description" => "Hulpsessie ($minuten minuten)",
			"ledger_account_id" => 166471153818273144 // omzet categorie "Computerhulp diensten"
		);
		array_push($this->data["details_attributes"], $hulpsessie);
		if($lid) {
			$korting = array(
				"tax_rate_id" => 166041031534445957,
				"price" => 0 - $kwartierenCorr * $kortingPerKwartier,
				"description" => "Lidmaatschap korting",
				"ledger_account_id" => 166470034793694891 // omzet categorie "Contributie leden (lidmaatschap)"
			);
			array_push($this->data["details_attributes"], $korting);
		}
	}

	function annulering() {
		global $conf;
		$hulpsessie = array(
			"tax_rate_id" => 166041031534445957,
			"price" => $conf["prijzen"]["te_laat_geannuleerd"],
			"description" => "Hulpsessie te laat geannuleerd",
			"ledger_account_id" => 166471153818273144 // omzet categorie "Computerhulp diensten"
		);
		array_push($this->data["details_attributes"], $hulpsessie);
	}

	function create() {
		global $conf;
		if($conf["production"]) {
			$companyId = $conf["credentials"]["moneybird"]["company_id"];
			$token = $conf["credentials"]["moneybird"]["access_token"];
			$ch = curl_init("https://moneybird.com/api/v2/" . $companyId . "/sales_invoices.json");
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("sales_invoice"=>$this->data)));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			curl_close($ch);
			$obj = json_decode($res);
			return $obj->id;
		} else {
			return 0;
		}
	}

}

function lidmaatschapFactuur($moneybirdId,$naamLidmaatschap,$aantalEset,$aantalOffice,$voordelenLijst,$bedragPerJaar,$particulier,$workflowId,$gelijkVerzenden) {
	global $conf;
	$beschrijving = "$naamLidmaatschap (1 jaar)\n";
	$bedragPerMaand = $bedragPerJaar / 12;
	$beschrijving .= "€" . str_replace(".",",",$bedragPerMaand) . " per maand - " . "€" . str_replace(".",",",$bedragPerJaar) . " per jaar\n";
	$beschrijving .= "(Verlenging) Antivirus/Beveiliging pakket\n\n";
	$beschrijving .= "Voordelen lidmaatschap:\n";
	if($aantalEset == 1) {
		$beschrijving .= "- Gratis antivirus pakket  (t.w.v. €55)\n";
	} else if($aantalEset > 1) {
		$beschrijving .= "- " . $aantalEset . "x gratis antivirus pakket voor 1 jaar (t.w.v. €" . $aantalEset * 55 . ")\n";
	}
	if($aantalOffice == 1) {
		$beschrijving .= "- Gratis office 365 (word, outlook, enz.)\n";
	} else if($aantalOffice > 1) {
		$beschrijving .= "- Office pakket voor $aantalOffice apparaten (t.w.v. €" . $aantalOffice * 44 . ")\n";
	}
	$beschrijving .= nl2br($voordelenLijst);

	$companyId = $conf["credentials"]["moneybird"]["company_id"];
	$token = $conf["credentials"]["moneybird"]["access_token"];

	$data = array(
		"document_style_id" => 166385950014833777,
		"contact_id" => $moneybirdId,
		"currency" => "EUR",
		"workflow_id" => 301016534352922471,
		"prices_are_incl_tax" => true,
		"details_attributes" => array(
			array(
				"tax_rate_id" => 166041031534445957,
				"price" => $bedragPerJaar / 12,
				"amount" => "12",
				"description" => $beschrijving,
				"ledger_account_id" => 166470034793694891 // omzet categorie "Contributie leden (lidmaatschap)"
			)
		)
	);

	$ch = curl_init("https://moneybird.com/api/v2/" . $companyId . "/sales_invoices.json");
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("sales_invoice"=>$data)));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$res = curl_exec($ch);
	curl_close($ch);
	$obj = json_decode($res);

	if($gelijkVerzenden && $conf["production"]) {
		$factuurId = $obj->id;
		$ch = curl_init("https://moneybird.com/api/v2/$companyId/sales_invoices/$factuurId/send_invoice.json");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);

		$nu = new DateTime();
		$data = array(
			"payment_date" => $nu->format("d-m-Y"),
			"price" => $bedragPerJaar
		);

		$ch = curl_init("https://moneybird.com/api/v2/$companyId/sales_invoices/$factuurId/payments.json");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("payment"=>$data)));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);
	}
}

function lidmaatschapFactuurPeriodiek($moneybirdId,$naamLidmaatschap,$aantalEset,$aantalOffice,$voordelenLijst,$bedragPerJaar,$particulier,$workflow,$dagenVanTevoren) {
	global $conf;
	$beschrijving = "$naamLidmaatschap (1 jaar)\n";
	$bedragPerMaand = $bedragPerJaar / 12;
	$beschrijving .= "€" . str_replace(".",",",$bedragPerMaand) . " per maand - " . "€" . str_replace(".",",",$bedragPerJaar) . " per jaar\n";
	$beschrijving .= "(Verlenging) Antivirus/Beveiliging pakket\n\n";
	$beschrijving .= "Voordelen lidmaatschap:\n";
	if($aantalEset == 1) {
		$beschrijving .= "- Gratis antivirus pakket  (t.w.v. €55)\n";
	} else if($aantalEset > 1) {
		$beschrijving .= "- " . $aantalEset . "x gratis antivirus pakket voor 1 jaar (t.w.v. €" . $aantalEset * 55 . ")\n";
	}
	if($aantalOffice == 1) {
		$beschrijving .= "- Gratis office 365 (word, outlook, enz.)\n";
	} else if($aantalOffice > 1) {
		$beschrijving .= "- Office pakket voor $aantalOffice apparaten (t.w.v. €" . $aantalOffice * 44 . ")\n";
	}
	$beschrijving .= nl2br($voordelenLijst);
	$nu = new DateTime();
	$invoiceDate = $nu->add(new DateInterval('P' . $dagenVanTevoren . 'D'));

	$companyId = $conf["credentials"]["moneybird"]["company_id"];
	$token = $conf["credentials"]["moneybird"]["access_token"];

	$data = array(
		"document_style_id" => 166385950014833777,
		"contact_id" => $moneybirdId,
		"currency" => "EUR",
		"workflow_id" => 166389081013487570,
		"prices_are_incl_tax" => $particulier,
		"frequency_type" => "year",
		"invoice_date" => $invoiceDate->format("d-m-Y"),
		"details_attributes" => array(
			array(
				"tax_rate_id" => 166041031534445957,
				"price" => $bedragPerJaar / 12,
				"amount" => "12",
				"description" => $beschrijving,
				"ledger_account_id" => 166470034793694891 // omzet categorie "Contributie leden (lidmaatschap)"
			)
		)
	);

	$ch = curl_init("https://moneybird.com/api/v2/" . $companyId . "/recurring_sales_invoices.json");
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("recurring_sales_invoice"=>$data)));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$res = curl_exec($ch);
	curl_close($ch);
	$obj = json_decode($res);

}

// lidmaatschap

class Afspraak {
	private $id;
	public $info;

	function __construct() {

	}

	function fetchData($afspraakId) {
		global $conn;
		$this->id = $afspraakId;

		$strings = array("klantnummer", "naam_klant", "woonplaats_klant", "omschrijving_kort",
		"omschrijving", "category_id", "aantekeningen", "student_id", "beschrijving_werkzaamheden",
		"handtekening_klant", "handtekening_student", "student_bonus", "aantal_km","antivirus","antivirus_afloop",
		"teamviewer_reden","niet_volledig_reden","ipv4_adres","factuur_id","survey_token","afspraak_duur","username");
		$booleans = array("externe_partij", "contant_betalen", "factuur_gestuurd");

		$q = "SELECT ";
		$q .= implode(",", $strings);
		$q .= "," . implode(",", $booleans);
		$q .= ",datum_plaatsing,start_datum,eind_datum,afstand,spoed,externe_partij,contant_betalen,factuur_gestuurd";
		$q .= " FROM afspraken";
		$q .= " LEFT JOIN users ON users.id=afspraken.student_id";
		$q .= " WHERE afspraken.id=?";
		$stmt = $conn->prepare($q);
		$stmt->bind_param("i", $afspraakId);
		$stmt->execute();
		$result = $stmt->get_result();
		$stmt->close();

		if($result->num_rows > 0) {
			$data = array();
			while($row = $result->fetch_assoc()) {

				foreach($strings as $str) {
					$data[$str] = $row[$str];
				}
				foreach($booleans as $str) {
					$data[$str] = $row[$str] == 1 ? true : false;
				}
				$data["datum_plaatsing"] = DateTime::createFromFormat('Y-m-d', $row["datum_plaatsing"]);
				$data["start_datum"] = DateTime::createFromFormat('Y-m-d H:i:s', $row["start_datum"]);
				$data["eind_datum"] = DateTime::createFromFormat('Y-m-d H:i:s', $row["eind_datum"]);
				$data["afstand"] = $row["afstand"] == 1 ? true : false;
				$data["spoed"] = $row["spoed"] == 1 ? true : false;

				$this->info = $data;

			}
		} else {
			return -1;
		}
	}

	function create() {


	}

	function update() {

	}
}

function findOrCreateCustomer($arr) {
	global $conf;
	$contact = moneybirdApi("/contacts.json?query=" . urlencode($arr["email"]));
	// zoeken op email
	if(!empty($contact)) {
		return $contact[0];
	}
	// zoeken op naam + postcode
	$contact = moneybirdApi("/contacts.json?query=" . urlencode($arr["voornaam"] . " " . $arr["achternaam"] . " " . $arr["postcode"]));
	if(!empty($contact)) { // als de email in Moneybird staat
		return $contact[0];
	}
	// zoeken op postcode + adres
	$contact = moneybirdApi("/contacts.json?query=" . urlencode($arr["postcode"] . " " . $arr["adres"]));
	if(!empty($contact)) { // als de email in Moneybird staat
		return $contact[0];
	}
	// als de klant NIET in Moneybird gevonden is
	// postcode opzoeken en naar plaats omzetten
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://json.api-postcode.nl");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("token: " . $conf["credentials"]["postcode_api_token"]));
	curl_setopt($ch, CURLOPT_POSTFIELDS, 'postcode=' . urlencode($arr["postcode"]));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$res = curl_exec($ch);
	$json = json_decode($res, true);
	curl_close($ch);
	if(is_array($json) && isset($json["postcode"]) && isset($json["city"])) {
		$postcode = $json["postcode"];
		$postcode = substr($postcode, 0, 4) . " " . substr($postcode, 4);
		$plaats = $json["city"];
		// contact in Moneybird zetten
		$companyId = $conf["credentials"]["moneybird"]["company_id"];
		$token = $conf["credentials"]["moneybird"]["access_token"];
		$ch = curl_init("https://moneybird.com/api/v2/" . $companyId . "/contacts.json");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("contact" => array(
			"firstname" => $arr["voornaam"],
			"lastname" => $arr["achternaam"],
			"company_name" => $arr["bedrijfsnaam"],
			"address1" => $arr["adres"],
			"zipcode" => $postcode,
			"city" => $plaats,
			"send_invoices_to_email" => $arr["email"],
			"send_estimates_to_email" => $arr["email"],
			"phone" => $arr["telefoonnummer"]
		))));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , "Authorization: Bearer $token"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);
		$contact = json_decode($res);
		if(isset($contact->customer_id)) {
			return $contact;
		} else {
			return -1;
			// verkeerd email mogelijk, kan niet in MB geplaatst worden!!
		}
	} else {
		// ongeldige postcode
		return -2;
	}
}

// LEDEN

class Lid {
	private $klantnummer;
	function __construct($klantnummer) {
		$this->klantnummer = $klantnummer;
	}
	function officeZoeken($officeAantal) {
		global $conn;
		$stmt = $conn->prepare("SELECT office_licenties.id,email,wachtwoord FROM office_licenties LEFT JOIN leden ON office_licenties.id=office_id GROUP BY office_licenties.id HAVING (SUM(aantal_keer_geldig) - SUM(IFNULL(office_aantal, 0))) >= ?");
	  $stmt->bind_param("i", $officeAantal);
	  $stmt->execute();
	  $result = $stmt->get_result();
	  $stmt->close();
	  $arr = $result->fetch_all();
	  if(isset($arr[0])) {
	    $officeId = $arr[0][0];
	    $email = $arr[0][1];
	    $wachtwoord = $arr[0][2];
	    $stmt = $conn->prepare("UPDATE leden SET office_id=?,datum_oude_office_afloop=NULL WHERE klantnummer=?");
	    $stmt->bind_param("is", $officeId, $this->klantnummer);
	    $stmt->execute();
	    $stmt->close();
			return array(
				"id" => $officeId,
				"email" => $email,
				"wachtwoord" => $wachtwoord
			);
	  } else {
			return -1;
	  }
	}

	function esetZoeken($esetAantal) {
		global $conn;
		$stmt = $conn->prepare("SELECT eset_licenties.id,code FROM eset_licenties LEFT JOIN leden ON eset_licenties.id=eset_id WHERE ongeldig=0 GROUP BY eset_licenties.id HAVING (SUM(aantal_keer_geldig) - SUM(IFNULL(eset_aantal, 0))) > ? ORDER BY eset_licenties.bestel_datum ASC");
		$stmt->bind_param("i", $esetAantal);
		$stmt->execute();
		$result = $stmt->get_result();
		$stmt->close();
		$arr = $result->fetch_all();
		if(isset($arr[0])) {
			$esetId = $arr[0][0];
			$esetCode = $arr[0][1];
			$stmt = $conn->prepare("UPDATE leden SET eset_id=? WHERE klantnummer=?");
	    $stmt->bind_param("is", $esetId, $this->klantnummer);
	    $stmt->execute();
	    $stmt->close();
			return array(
				"id" => $esetId,
				"code" => $esetCode
			);
		} else {
			return -1;
		}
	}
}

// Other

function categorySelector() {
	global $conn;
	$str = "";
	$stmt = $conn->prepare("SELECT * FROM categorieen");
	$stmt->execute();
	$result = $stmt->get_result();
	$stmt->close();

	$str .= '<select name="categorie" class="form-control" required>';
	$str .= '<option value="">Kies een categorie...</option>';
	while($row = $result->fetch_assoc()) {
		$naam = htmlspecialchars($row["naam"]);
		$parent = htmlspecialchars($row["parent"]);
		$id = htmlspecialchars($row["id"]);
		$str .= "<option value='$id'>$parent - $naam</option>";
	}
	$str .= '</select>';
	return $str;
}

function categoryName($id) {
	global $conn;
	$stmt = $conn->prepare("SELECT naam,parent FROM categorieen WHERE id=?");
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$result = $stmt->get_result();
	$stmt->close();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row["parent"] . " - " . $row["naam"];
		}
	} else {
		return "Onbekend";
	}
}

?>
