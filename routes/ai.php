<?php

use App\Mcp\Servers\QvtServer;
use Laravel\Mcp\Facades\Mcp;

// Local server for Claude Desktop / stdio-based clients
// Requires --user=email flag to identify the staff user
Mcp::local('qvt', QvtServer::class);

// Web server for remote / HTTP-based clients (OpenCode, Cursor, custom agents)
Mcp::web('/mcp/qvt', QvtServer::class)
    ->middleware([
        'mcp.sanctum',
        'role:admin',
        'throttle:mcp',
    ]);
