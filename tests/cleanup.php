<?php
// Only removes databases created by tests/run.php.
declare(strict_types=1);
$name=$argv[1]??'';
if(!preg_match('/^mowallet_test_[a-f0-9]{10}$/D',$name)){fwrite(STDERR,"Invalid test database name.\n");exit(1);}
require dirname(__DIR__).'/bootstrap/app.php';
\Mini\Database::connection(true)->exec("DROP DATABASE IF EXISTS `$name`");
