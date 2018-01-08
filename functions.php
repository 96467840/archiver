<?php

function log_mess($message){
    $fp = fopen ("./logs/".date("Y_m_d").".log", "a");
    fputs($fp,$message);
    fclose($fp);
}

function db_connect($db, $connection='default'){
    
    $mysqli = new mysqli($db[$connection]['hostname'], $db[$connection]['username'], $db[$connection]['password'], $db[$connection]['database']);
    
    /*
    * Это "официальный" объектно-ориентированный способ сделать это
    * однако $connect_error не работал вплоть до версий PHP 5.2.9 и 5.3.0.
    */
    if ($mysqli->connect_error) {
        $err = 'Ошибка подключения (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error;
        log_mess($err);
        die($err);
    }
    
    /*
    * Если нужно быть уверенным в совместимости с версиями до 5.2.9,
    * лучше использовать такой код
    */
    /*if (mysqli_connect_error()) {
    die('Ошибка подключения (' . mysqli_connect_errno() . ') '
    . mysqli_connect_error());
    }/**/
    
    return $mysqli;
}

// отправить файл или папку на другой сервер
function store($config, $item){

}

function db_dumps(){


}
