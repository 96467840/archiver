<?php

include "config.php";
include "functions.php";

$mysqli = db_connect($db);

log_mess('Соединение установлено... ' . $mysqli->host_info);

$mysqli->close();