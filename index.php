<?php
	session_start();
	// error_reporting(0);

	require_once("config.php");

	require "vendor/autoload.php";

	// Force HTTPS
	// if ($conf["production"] && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off")) {
  //   $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	// 	header('HTTP/1.1 301 Moved Permanently');
	// 	header('Location: ' . $location);
	// 	exit;
	// }

	date_default_timezone_set('Europe/Amsterdam');

	$creds = $conf["credentials"]["mysql"];
	$conn = new mysqli($creds["host"], $creds["user"], $creds["passwd"], $creds["database"]);
	if ($conn->connect_error) {
		include "pages/errors/db_conn.php";
    die();
	}

	require 'src/util.php';

	if(isset($_GET["path"])) {
		$path = $_GET["path"];
	}

	if(startsWith($path, "public")) {
		if(file_exists($path)) {
			include $path;
			exit;
		} else {
			echo "Public file doesn't exist!";
		}
	}

	if(!isset($path)) {
		header("location: /home");
	} else if(file_exists("pages/" . $path . ".php")) {
		include "pages/" . $path . ".php";
	} else {
		include "pages/errors/404.php";
	}


?>
