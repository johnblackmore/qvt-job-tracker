<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetCustomerTool;
use App\Mcp\Tools\GetProductTool;
use App\Mcp\Tools\ListCustomersTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\SearchCustomersTool;
use App\Mcp\Tools\SearchProductsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('QVT Job Tracker')]
#[Version('1.0.0')]
#[Instructions(
    'Quantock Van Tech staff admin interface. '.
    'All tools require staff admin role authentication. '.
    'Write operations use a preview/confirmed pattern: '.
    'call with preview=true to see what will happen, '.
    'then call again with confirmed=true to execute. '.
    'Trade prices are internal-only and never appear in customer-facing output. '.
    'Every completed action returns a link to view the record in the staff admin area.'
)]
class QvtServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListCustomersTool::class,
        GetCustomerTool::class,
        SearchCustomersTool::class,
        ListProductsTool::class,
        GetProductTool::class,
        SearchProductsTool::class,
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [];
}
