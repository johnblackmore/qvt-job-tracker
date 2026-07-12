<?php

namespace App\Mcp\Prompts;

use Carbon\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class WeeklyReportGeneratorPrompt extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'week_starting',
                description: 'Monday of the target week (YYYY-MM-DD). Defaults to last Monday.',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $weekStarting = $request->get('week_starting')
            ? Carbon::parse($request->get('week_starting'))->startOfDay()
            : now()->startOfWeek(Carbon::MONDAY);

        $weekEnding = $weekStarting->copy()->endOfWeek(Carbon::SUNDAY);

        $template = 'You are generating a weekly business report for Quantock Van Tech. '.
            "The report covers the week starting Monday {$weekStarting->format('d F Y')} ".
            "and ending Sunday {$weekEnding->format('d F Y')}. ".
            "\n\n".
            "Steps to generate the report:\n".
            "1. Call the get-weekly-summary tool to retrieve the current week's statistics.\n".
            "2. If the staff user requests a different week, re-call the tool with the week_starting parameter.\n".
            "3. Present the results in a narrative format suitable for a Monday morning staff meeting:\n".
            "   - Lead with the headline numbers (new customers, new quotes, accepted quotes value, deposit collected)\n".
            "   - Highlight the top 3 customers by accepted quote value (if any)\n".
            "   - Surface any pending follow-ups (quotes awaiting response > 3 days)\n".
            "   - End with a brief outlook for the week ahead\n".
            "\n\n".
            "Formatting guidance:\n".
            "- Use British English (e.g. '£' for currency, not 'S')\n".
            "- Keep the report to 3-4 short paragraphs\n".
            "- Use natural prose, not bullet points\n".
            '- Always include the date range in the opening line';

        return Response::text($template);
    }
}
