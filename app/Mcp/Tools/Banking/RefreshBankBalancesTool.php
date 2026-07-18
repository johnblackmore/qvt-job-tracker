<?php

namespace App\Mcp\Tools\Banking;

use App\Banking\Services\BalanceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Refresh bank account balances by fetching the latest data from the banking provider. Use preview mode first to confirm.')]
class RefreshBankBalancesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'preview' => $schema->boolean()
                ->description('Preview the action without executing. Defaults to true.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set to true to confirm and execute the refresh. Defaults to false.')
                ->default(false),
        ];
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
                    'balance' => $schema->number(),
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
        $preview = $request->boolean('preview', true);
        $confirmed = $request->boolean('confirmed', false);

        $service = app(BalanceService::class);
        $accounts = $service->getBalances();

        if ($accounts->isEmpty()) {
            return Response::structured([
                'status' => 'completed',
                'message' => 'No active bank accounts to refresh.',
                'accounts' => [],
            ]);
        }

        $count = $accounts->count();
        $names = $accounts->pluck('name')->implode(', ');

        if ($preview && ! $confirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "This will refresh the balance for {$count} account(s): {$names}. Current data is from cache. Call again with confirmed=true to execute.",
                'accounts' => $this->formatAccounts($accounts),
            ]);
        }

        if ($confirmed) {
            $refreshed = $service->refreshAllBalances();

            return Response::structured([
                'status' => 'completed',
                'message' => "Balances refreshed for {$count} account(s): {$names}.",
                'accounts' => $this->formatAccounts($refreshed),
            ]);
        }

        return Response::structured([
            'status' => 'cancelled',
            'message' => 'Refresh was not confirmed. No changes made.',
            'accounts' => $this->formatAccounts($accounts),
        ]);
    }

    private function formatAccounts($accounts): array
    {
        return $accounts->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'balance' => $a->balance_pence !== null
                ? round($a->balance_pence / 100, 2)
                : null,
            'formatted_balance' => $this->formatBalance($a),
            'last_fetched_at' => $a->balance_fetched_at?->toIso8601String(),
            'last_fetched_relative' => $a->balance_fetched_at?->diffForHumans() ?? 'never',
        ])->toArray();
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
