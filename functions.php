<?php

function log_mess($message) {
    $fp = fopen ("./logs/" . date("Y_m_d") . ".log", "a");
    fputs($fp, $message . "\n");
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

function dbs_dump($mysqli, $config, $db, $connection='default') {
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
            @unlink($dbname . '.sql.zip');
        }
    }
    chdir('..');
}

function sites_dump($config) {
    $currdir = getcwd();
    $tmpdir = $currdir . '/tmp/';
    //echo 'currdir = ' . $currdir . "\n";
    if ($handle = opendir($config['sites']))
	{
        //echo "Directory handle: $handle\n";
        //echo "Entries:\n";

        /* This is the correct way to loop over the directory. */
        while (false !== ($entry = readdir($handle)))
        {
            if (substr($entry, 0, 1) == '.') continue;

    		// чутка костыльно
	    	if (isset($config['check_NOT-HERE_file']) && $config['check_NOT-HERE_file'])
    		{
    			if (is_file($config['sites'] . 'admin-utils/' . $entry . '/.NOT-HERE' ) )
    			{
	    			echo "skip $entry\n";
    				continue;
    			}
    		}

            chdir($currdir); // $config['sites'] может быть относительным
            chdir($config['sites']);

            if (!is_dir($entry)) continue;
            echo "$entry\n";

            exec('zip -r ' . $tmpdir . $entry . '.zip ' . $entry);
 
            chdir($tmpdir);
            store($config, $entry . '.zip');
            @unlink($entry . '.zip');
        }
    
        closedir($handle);
        chdir($currdir);
    }
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

        //ftp_pasv(self::$conn_id, true) or die("Cannot switch to passive mode");

        // login with username and password
        $login_result = ftp_login(self::$conn_id, $ftp_user_name, $ftp_user_pass);

        if ((!self::$conn_id) || (!$login_result)) { 
            $err = "FTP connection has failed! Attempted to connect to $ftp_server for user $ftp_user_name";
            log_mess($err);
            die($err);
        } else {
            //echo "Connected to $ftp_server, for user $ftp_user_name\n";
        }

        if (!ftp_chdir(self::$conn_id, self::$config['ftp']['folder']))
            die("Couldn't chdir " . self::$config['ftp']['folder']);

        if (isset(self::$config['ftp']['removeold']) && self::$config['ftp']['removeold'])
        {
            // remove old folders
            FTP::removeold();
        }

        $dir = date("Y_m_d_H_i_s" . (isset(self::$config['fPostfix']) ? self::$config['fPostfix'] : ''));

        //$tmp = ftp_nlist(self::$conn_id, "."); echo "\n".implode(',', $tmp)."\n\n";
		sleep(5);
        if (!ftp_mkdir(self::$conn_id, $dir)) die("Couldn't mkdir " . $dir);

		if (!ftp_chdir(self::$conn_id,  $dir)) die("Couldn't chdir " . $dir);
    }

	// рекурсии делать не будем считаем что у нас архив это строго папка с файлами (папка с папками уже удалятся не будет)
    public static function removeold() {
		$ftp_max = self::$config['ftp']['max'];
		$dirs = ftp_nlist(self::$conn_id, ".");
		$curcount = count($dirs);
		//echo "arhcives: $curcount max:$ftp_max\n";

		if ($curcount < $ftp_max) return; // если уже максимум значит надо удалять (удаление старых делаем перед загрузкой нового архива)
		//echo "removing " . ($curcount + 1 - $ftp_max) . " archives...\n";

		sort($dirs, SORT_STRING);
		for ($i = 0; $i < ($curcount + 1 - $ftp_max); $i++) 
		{
			$dir = $dirs[$i];
			echo "removing $dir ...\n";
			if (!ftp_chdir(self::$conn_id,  $dir)) die("Couldn't chdir " . $dir);

			$files = ftp_nlist(self::$conn_id, ".");
			foreach ($files as $file)
			{
				if (!ftp_delete(self::$conn_id, $file)) die("Couldn't delete " . $dir . '/' . $file);
			}

			if (!ftp_chdir(self::$conn_id,  '..')) die("Couldn't chdir .." );

			if (!ftp_rmdir(self::$conn_id, $dir)) die("Couldn't rmdir " . $dir);
		}
    }

    public static function close() {
        ftp_close(self::$conn_id);
    }

    public static function upload($file) {
        $upload = ftp_put(self::$conn_id, $file, $file, FTP_BINARY); 

        // check upload status
        if (!$upload) {
            echo "FTP upload $file has failed!\n";
            log_mess("FTP upload $file has failed!\n");
        } else {
            echo "Uploaded $file\n";

        }
    }

}
