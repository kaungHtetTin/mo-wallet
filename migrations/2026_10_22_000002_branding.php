<?php
return [
    'up' => [
        "CREATE TABLE app_branding (id TINYINT UNSIGNED PRIMARY KEY, logo MEDIUMBLOB NULL, favicon MEDIUMBLOB NULL, revision CHAR(32) NOT NULL, CHECK (id = 1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "INSERT INTO app_branding (id,revision) VALUES (1,'00000000000000000000000000000000')",
    ],
    'down' => ['DROP TABLE app_branding'],
];
