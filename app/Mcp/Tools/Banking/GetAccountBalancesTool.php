<?php

namespace App\Mcp\Tools\Banking;

use App\Banking\Services\BalanceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Retrieve current balances for all active bank accounts. Returns cached data (up to 4 hours old) with last-fetched timestamp.')]
class GetAccountBalancesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'accounts' => $schema->array(
                $schema->object([
                    'id' => $schema->integer()->required(),
                    'name' => $schema->string()->required(),
                    'type' => $schema->string(),
                    'balance' => $schema->number(),
                    'currency' => $schema->string(),
                    'formatted_balance' => $schema->string(),
                    'last_fetched_at' => $schema->string(),
                    'last_fetched_relative' => $schema->string(),
                ]),
            ),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $accounts = app(BalanceService::class)->getBalances();

        if ($accounts->isEmpty()) {
            return Response::structured([
                'status' => 'completed',
                'message' => 'No bank accounts are linked yet.',
                'accounts' => [],
            ]);
        }

        $accountData = $accounts->map(function ($account) {
            $fetchedAt = $account->balance_fetched_at;

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $account->balance_pence !== null
                    ? round($account->balance_pence / 100, 2)
                    : null,
                'currency' => strtoupper($account->currency ?? 'GBP'),
                'formatted_balance' => $this->formatBalance($account),
                'last_fetched_at' => $fetchedAt?->toIso8601String(),
                'last_fetched_relative' => $fetchedAt?->diffForHumans() ?? 'never',
            ];
        });

        $lines = $accountData->map(fn ($a) => "{$a['name']}: {$a['formatted_balance']} (updated {$a['last_fetched_relative']})");

        return Response::structured([
            'status' => 'completed',
            'message' => $lines->implode("\n"),
            'accounts' => $accountData->toArray(),
        ]);
    }

    private function formatBalance($account): string
    {
        if ($account->balance_pence === null) {
            return 'Unknown';
        }

        $major = number_format($account->balance_pence / 100, 2);
        $currency = strtoupper($account->currency ?? 'GBP');

        return match ($currency) {
            'GBP' => "\u{00A3}{$major}",
            'EUR' => "\u{20AC}{$major}",
            'USD' => "\${$major}",
            default => "{$currency} {$major}",
        };
    }
}
