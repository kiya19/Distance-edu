<?php

// Values fall back to the original local defaults, so nothing changes for
// local/XAMPP use. In Docker/Render, these are supplied as environment
// variables (see render.yaml).
return [
    'host'        => getenv('DB_HOST') ?: 'localhost',
    'database'    => getenv('DB_NAME') ?: 'dems',
    'username'    => getenv('DB_USER') ?: 'root',
    'password'    => getenv('DB_PASS') ?: '',
    'charset'     => getenv('DB_CHARSET') ?: 'utf8mb4',
    'base_url'    => getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/dems/public',
    'upload_path' => getenv('UPLOAD_PATH') ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'),
];

