<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\QuoteAssistantPrompt;
use App\Mcp\Prompts\WeeklyReportGeneratorPrompt;
use App\Mcp\Resources\CustomerProfileResource;
use App\Mcp\Resources\OrderDetailsResource;
use App\Mcp\Resources\QuoteDetailsResource;
use App\Mcp\Tools\AddQuoteLineItemTool;
use App\Mcp\Tools\AiConfig\CreateAiModelConfigTool;
use App\Mcp\Tools\AiConfig\DeleteAiModelConfigTool;
use App\Mcp\Tools\AiConfig\GetAiAssistantConfigSettingsTool;
use App\Mcp\Tools\AiConfig\GetAiModelConfigTool;
use App\Mcp\Tools\AiConfig\ListAiModelConfigsTool;
use App\Mcp\Tools\AiConfig\UpdateAiAssistantConfigSettingsTool;
use App\Mcp\Tools\AiConfig\UpdateAiModelConfigTool;
use App\Mcp\Tools\CreateCustomerTool;
use App\Mcp\Tools\CreateEnquiryReplyTool;
use App\Mcp\Tools\CreateEnquiryTool;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\CreateQuoteFromEnquiryTool;
use App\Mcp\Tools\CreateQuoteFromTemplateTool;
use App\Mcp\Tools\CreateQuoteTool;
use App\Mcp\Tools\DeleteCustomerTool;
use App\Mcp\Tools\DownloadQuotePdfTool;
use App\Mcp\Tools\GenerateEnquiryDraftTool;
use App\Mcp\Tools\GetCustomerTool;
use App\Mcp\Tools\GetDashboardStatsTool;
use App\Mcp\Tools\GetEmailSentTool;
use App\Mcp\Tools\GetEnquiryTool;
use App\Mcp\Tools\GetInboundReplyTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetProductTool;
use App\Mcp\Tools\GetQuoteActivityTool;
use App\Mcp\Tools\GetWeeklySummaryTool;
use App\Mcp\Tools\LinkEnquiryToCustomerTool;
use App\Mcp\Tools\ListCustomersTool;
use App\Mcp\Tools\ListEmailSentTool;
use App\Mcp\Tools\ListEnquiriesTool;
use App\Mcp\Tools\ListEnquiryRepliesTool;
use App\Mcp\Tools\ListInboundRepliesTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\RecordPaymentTool;
use App\Mcp\Tools\RespondToEnquiryTool;
use App\Mcp\Tools\SaveEnquiryDraftTool;
use App\Mcp\Tools\ScheduleInstallationTool;
use App\Mcp\Tools\SearchCustomersTool;
use App\Mcp\Tools\SearchProductsTool;
use App\Mcp\Tools\SendQuoteEmailTool;
use App\Mcp\Tools\SyncNetlifySubmissionsTool;
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
        RecordPaymentTool::class,

        // Enquiry tools
        ListEnquiriesTool::class,
        GetEnquiryTool::class,
        CreateEnquiryTool::class,
        LinkEnquiryToCustomerTool::class,
        RespondToEnquiryTool::class,
        CreateEnquiryReplyTool::class,
        ListEnquiryRepliesTool::class,
        ListInboundRepliesTool::class,
        GetInboundReplyTool::class,
        CreateQuoteFromEnquiryTool::class,
        GenerateEnquiryDraftTool::class,
        SaveEnquiryDraftTool::class,

        // Communication tools
        SendQuoteEmailTool::class,
        DownloadQuotePdfTool::class,
        ListEmailSentTool::class,
        GetEmailSentTool::class,

        // Dashboard & reporting tools
        GetDashboardStatsTool::class,
        GetQuoteActivityTool::class,
        GetWeeklySummaryTool::class,

        // Integration tools
        SyncNetlifySubmissionsTool::class,

        // AI Config tools
        ListAiModelConfigsTool::class,
        GetAiModelConfigTool::class,
        CreateAiModelConfigTool::class,
        UpdateAiModelConfigTool::class,
        DeleteAiModelConfigTool::class,
        GetAiAssistantConfigSettingsTool::class,
        UpdateAiAssistantConfigSettingsTool::class,
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [
        CustomerProfileResource::class,
        QuoteDetailsResource::class,
        OrderDetailsResource::class,
    ];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [
        QuoteAssistantPrompt::class,
        WeeklyReportGeneratorPrompt::class,
    ];

    /** @return array<int, class-string<Tool>> */
    public static function toolClasses(): array
    {
        return [
            ListCustomersTool::class,
            GetCustomerTool::class,
            SearchCustomersTool::class,
            CreateCustomerTool::class,
            UpdateCustomerTool::class,
            DeleteCustomerTool::class,
            ListProductsTool::class,
            GetProductTool::class,
            SearchProductsTool::class,
            CreateQuoteTool::class,
            CreateQuoteFromTemplateTool::class,
            AddQuoteLineItemTool::class,
            UpdateQuoteStatusTool::class,
            ListOrdersTool::class,
            GetOrderTool::class,
            CreateOrderTool::class,
            UpdateOrderStatusTool::class,
            UpdateDepositTool::class,
            ScheduleInstallationTool::class,
            RecordPaymentTool::class,
            ListEnquiriesTool::class,
            GetEnquiryTool::class,
            CreateEnquiryTool::class,
            LinkEnquiryToCustomerTool::class,
            RespondToEnquiryTool::class,
            CreateEnquiryReplyTool::class,
            ListEnquiryRepliesTool::class,
            ListInboundRepliesTool::class,
            GetInboundReplyTool::class,
            CreateQuoteFromEnquiryTool::class,
            GenerateEnquiryDraftTool::class,
            SaveEnquiryDraftTool::class,
            SendQuoteEmailTool::class,
            DownloadQuotePdfTool::class,
            ListEmailSentTool::class,
            GetEmailSentTool::class,
            GetDashboardStatsTool::class,
            GetQuoteActivityTool::class,
            GetWeeklySummaryTool::class,
            SyncNetlifySubmissionsTool::class,
            ListAiModelConfigsTool::class,
            GetAiModelConfigTool::class,
            CreateAiModelConfigTool::class,
            UpdateAiModelConfigTool::class,
            DeleteAiModelConfigTool::class,
            GetAiAssistantConfigSettingsTool::class,
            UpdateAiAssistantConfigSettingsTool::class,
        ];
    }
}
