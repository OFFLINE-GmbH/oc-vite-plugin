<?php

return [
    'manifest' => env('VITE_MANIFEST', ''),
    'manifest_filename' => env('VITE_MANIFEST_FILENAME', 'manifest.json'),
    'devEnvs' => explode(',', env('VITE_DEV_ENVS', 'dev,local')),
    'host' => env('VITE_HOST', 'http://localhost:5173'),
];
