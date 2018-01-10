<?php

include "config.php";
include "functions.php";

$mysqli = db_connect($db);

FTP::init($config);

//log_mess('Соединение установлено... ' . $mysqli->host_info);
//dbs_dump($mysqli, $config, $db);
sites_dump($config);

$mysqli->close();
FTP::close();