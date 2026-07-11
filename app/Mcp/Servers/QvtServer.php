<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddQuoteLineItemTool;
use App\Mcp\Tools\CreateCustomerTool;
use App\Mcp\Tools\CreateEnquiryTool;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\CreateQuoteFromTemplateTool;
use App\Mcp\Tools\CreateQuoteTool;
use App\Mcp\Tools\DeleteCustomerTool;
use App\Mcp\Tools\DownloadQuotePdfTool;
use App\Mcp\Tools\GetCustomerTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetProductTool;
use App\Mcp\Tools\LinkEnquiryToCustomerTool;
use App\Mcp\Tools\ListCustomersTool;
use App\Mcp\Tools\ListEnquiriesTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\RespondToEnquiryTool;
use App\Mcp\Tools\ScheduleInstallationTool;
use App\Mcp\Tools\SearchCustomersTool;
use App\Mcp\Tools\SearchProductsTool;
use App\Mcp\Tools\SendQuoteEmailTool;
use App\Mcp\Tools\UpdateCustomerTool;
use App\Mcp\Tools\UpdateDepositTool;
use App\Mcp\Tools\UpdateOrderStatusTool;
use App\Mcp\Tools\UpdateQuoteStatusTool;
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
        // Customer tools
        ListCustomersTool::class,
        GetCustomerTool::class,
        SearchCustomersTool::class,
        CreateCustomerTool::class,
        UpdateCustomerTool::class,
        DeleteCustomerTool::class,

        // Product tools
        ListProductsTool::class,
        GetProductTool::class,
        SearchProductsTool::class,

        // Quote tools
        CreateQuoteTool::class,
        CreateQuoteFromTemplateTool::class,
        AddQuoteLineItemTool::class,
        UpdateQuoteStatusTool::class,

        // Order tools
        ListOrdersTool::class,
        GetOrderTool::class,
        CreateOrderTool::class,
        UpdateOrderStatusTool::class,
        UpdateDepositTool::class,
        ScheduleInstallationTool::class,

        // Enquiry tools
        ListEnquiriesTool::class,
        CreateEnquiryTool::class,
        LinkEnquiryToCustomerTool::class,
        RespondToEnquiryTool::class,

        // Communication tools
        SendQuoteEmailTool::class,
        DownloadQuotePdfTool::class,
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [];
}
