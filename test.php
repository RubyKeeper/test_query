<?php

use FpDbTest\Database;
use FpDbTest\DatabaseTest;

try {
    spl_autoload_register(
        function ($class) {
            $a = array_slice(explode('\\', $class), 1);
            if (!$a) {
                throw new Exception('Ошибка подключения класса');
            }
            $filename = implode('/', [__DIR__, ...$a]) . '.php';
            require_once $filename;
        },
    );

    $mysqli = @new mysqli('db', 'root', 'password', 'database', 3306);
    if ($mysqli->connect_errno) {
        throw new Exception($mysqli->connect_error);
    }
} catch (Exception $exception) {
    echo $exception->getMessage();
    die();
}

try {
    $db = new Database($mysqli);
    $test = new DatabaseTest($db);
    $test->testBuildQuery();
} catch (Exception $exception) {
    echo $exception->getMessage();
    die();
}

exit("OK\n");
