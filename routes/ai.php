<?php

use App\Mcp\Servers\CrmServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 discovery and dynamic client registration routes, used by MCP
// clients such as Claude Desktop when connecting to the server below.
Mcp::oauthRoutes();

Mcp::web('/mcp', CrmServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);
