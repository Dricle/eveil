<?php

use App\Mcp\Servers\EveilServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * The MCP server takes two kinds of bearer token (`token.scope`), and each
 * tool call names the project it acts on:
 *
 * - an API token from Settings → API (`sanctum`), reaching every project of
 *   the organization that owns it;
 * - an OAuth token (`api`, Passport), for an assistant that connects by
 *   signing in. It acts as its user, and reaches what they can open. The
 *   consent screen is Passport's flow with the view in `AppServiceProvider`.
 *
 * Same rate limit bucket as `routes/api.php`, so a token cannot double its
 * budget by using both.
 */
Mcp::oauthRoutes();

Mcp::web('/mcp', EveilServer::class)
    ->middleware(['auth:sanctum,api', 'token.scope', 'throttle:api']);
