<?php

function log_mess($message) {
    $fp = fopen ("./logs/" . date("Y_m_d") . ".log", "a");
    fputs($fp,$message);
    fclose($fp);
}

function db_connect($db, $connection='default') {
    
    $mysqli = new mysqli($db[$connection]['hostname'], $db[$connection]['username'], $db[$connection]['password'], $db[$connection]['database']);
    
    /*
    * Это "официальный" объектно-ориентированный способ сделать это
    * однако $connect_error не работал вплоть до версий PHP 5.2.9 и 5.3.0.
    */
    if ($mysqli->connect_error) {
        $err = 'Connection error (' . $mysqli->connect_errno . ') '
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
function store($config, $archive) {
    FTP::upload($archive);
}

function db_dumps($mysqli, $config, $db, $connection='default') {
    $skipDBs = ['information_schema', 'mysql', 'performance_schema', 'phpmyadmin'];
    $sql = 'SHOW DATABASES';
    $res = $mysqli->query($sql);
    // заипемся не архивы отправлять
    $isZip = true; // isset($config['zip']) && $config['zip'];
    
    $mysqldump = 'mysqldump';
    if (isset($config['mysqldump'])) $mysqldump = $config['mysqldump'];
    
    $passwordoption = '';
    if ($db[$connection]['password']) 
        $passwordoption = ' -p'.$db[$connection]['password'];

    chdir('./tmp');
    while ($row = $res->fetch_assoc()) {
        //echo "  Database = " . $row['Database'] . "\n";
        $dbname = $row['Database'];
        if (in_array($dbname, $skipDBs)) continue;
        
        $postfix = ' > ./' . $dbname . '.sql';
        //if ($isZip) $postfix = ' | zip > ./' . $dbname . '.sql.zip';
        
        exec($mysqldump . ' -u ' . $db[$connection]['username'] . ' -h ' . $db[$connection]['hostname'] . $passwordoption . ' ' . $dbname . $postfix);
        if ($isZip)
        {
            exec('zip -r ' . $dbname . '.sql.zip ' . $dbname . '.sql');
            @unlink($dbname . '.sql');
            store($config, $dbname . '.sql.zip');
        }
    }
    chdir('..');
}

class FTP {
    private static $conn_id = false;
    private static $config;

    public static function init($config) {
        self::$config = $config;
        FTP::connect();
    }

    public static function connect () {
        //if ($this->conn_id) return;
        $ftp_server = self::$config['ftp']['server'];
        $ftp_user_name = self::$config['ftp']['user'];
        $ftp_user_pass = self::$config['ftp']['pwd'];

        self::$conn_id = ftp_connect($ftp_server);

        // login with username and password
        $login_result = ftp_login(self::$conn_id, $ftp_user_name, $ftp_user_pass);

        if ((!self::$conn_id) || (!$login_result)) { 
            $err = "FTP connection has failed! Attempted to connect to $ftp_server for user $ftp_user_name";
            log_mess($err);
            die($err);
        } else {
            //echo "Connected to $ftp_server, for user $ftp_user_name\n";
        }

        ftp_pasv(self::$conn_id, true) or die("Cannot switch to passive mode");

        if (!ftp_chdir(self::$conn_id, self::$config['ftp']['folder']))
            die("Couldn't chdir " . self::$config['ftp']['folder']);

        $dir = date("Y_m_d_H_M");

        @ftp_mkdir(self::$conn_id, $dir);

        if (!ftp_chdir(self::$conn_id,  $dir))
            die("Couldn't chdir " . $dir);
    }

    public static function close() {
        ftp_close(self::$conn_id);
    }

    public static function upload($file) {
        $upload = ftp_put(self::$conn_id, $file, $file, FTP_BINARY); 

        // check upload status
        if (!$upload) { 
            echo "FTP upload $file has failed!\n";
        } else {
            echo "Uploaded $file\n";
        }
    }

}
