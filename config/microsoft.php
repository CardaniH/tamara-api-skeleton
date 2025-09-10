<?php

return [
    'tenant_id' => env('MS_TENANT_ID'),
    'client_id' => env('MS_CLIENT_ID'),
    'client_secret' => env('MS_CLIENT_SECRET'),
    'graph_version' => env('MS_GRAPH_API_VERSION', 'v1.0'),
    'sharepoint_site_id' => env('MS_SHAREPOINT_SITE_ID'),
    
    'scopes' => [
        'https://graph.microsoft.com/Files.Read.All',
        'https://graph.microsoft.com/Files.ReadWrite.All',
        'https://graph.microsoft.com/Sites.Read.All',
    ],
];
