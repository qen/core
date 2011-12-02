<?php

echo "<h1>development.php is not configured</h1>"; exit;

return <<<CONFIG_JSON
{
    "db" : {
        "dsn"       : "mysql:dbname=DATABSENAME;host=HOST",
        "usr"       : "USER",
        "pwd"       : "PASS",
        "schemas"   : {
        }
    }

}
CONFIG_JSON;

