<?php

include "config.php";
include "functions.php";

$mysqli = db_connect($db);

FTP::init($config);

//log_mess('Соединение установлено... ' . $mysqli->host_info);
db_dumps($mysqli, $config, $db);

$mysqli->close();
FTP::close();