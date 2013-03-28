<?php

global $project;
$project = 'mysite';

global $databaseConfig;
switch(getenv('DB')) {
case "PGSQL";
	$databaseConfig = array(
		"type" => "PostgreSQLDatabase",
		"server" => 'localhost', 
		"username" => 'postgres', 
		"password" => '', 
		"database" => 'SS_travis'
	);
	break;
case "MYSQL":
	$databaseConfig = array(
		"type" => "MySQLDatabase",
		"server" => 'localhost', 
		"username" => 'root', 
		"password" => '', 
		"database" => 'SS_travis'
	);
	break;
default:
	$databaseConfig = array(
		"type" => "SQLitePDODatabase",
		"server" => 'localhost', 
		"memory" => true, 
		"database" => 'SS_travis',
		'path' => dirname(dirname(__FILE__)) .'/assets/'
	);
}

echo $databaseConfig['type'];

Security::setDefaultAdmin('username', 'password');

// Fake hostname on CLI to stop framework from complaining
$_SERVER['HTTP_HOST'] = 'http://localhost';

// Version-specific configuration
$version = getenv('CORE_RELEASE');
if($version != 'master' && version_compare('3.1', $version) == -1) {
	MySQLDatabase::set_connection_charset('utf8');
	SSViewer::set_theme('simple');
} else {
	Config::inst()->update('SSViewer', 'theme', 'simple');	
}