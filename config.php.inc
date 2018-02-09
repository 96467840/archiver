<?php

	$db['default']['hostname'] = 'localhost';
	$db['default']['username'] = 'root';
	$db['default']['password'] = '';
	$db['default']['database'] = ''; // '' - all DBs

	$config = [];
	//$config['zip'] = true;
	//$config['mysqldump'] = 'D:\\xampp\\mysql\\bin\\mysqldump';

	$config['workdir'] = '/var/www/a/archiver';

	$config['sites'] = './';

	$config['store'] = 'ftp';
	$config['ftp']['server'] = '2garin.com';
	$config['ftp']['user'] = 'arch';
	$config['ftp']['pwd'] = '???';
	$config['ftp']['folder'] = './';
	$config['ftp']['max'] = 5; // max archives on server. the oldest will be remove
