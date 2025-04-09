<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', '/unassign-task'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:3000'], // Replace with your frontend URL
    'allowed_headers' => ['*'],
    'supports_credentials' => true,
];