<?php

namespace App\Banking\Webhooks;

use App\Banking\Services\BankingProviderManager;
use App\Banking\Services\TransactionImportService;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MonzoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TransactionImportService $importService,
        BankingProviderManager $providerManager,
    ) {
        $payload = $request->all();

        if (($payload['type'] ?? null) !== 'transaction.created') {
            return response('OK', 200);
        }

        $txnData = $payload['data'] ?? [];

        if (empty($txnData['id'])) {
            return response('OK', 200);
        }

        $account = BankAccount::where('provider', 'monzo')
            ->where('provider_account_id', $txnData['account_id'] ?? '')
            ->where('is_active', true)
            ->first();

        if (! $account) {
            return response('OK', 200);
        }

        if (! empty($txnData['is_pending']) || ! empty($txnData['is_load'])) {
            return response('OK', 200);
        }

        $result = $importService->importSingle($account, $txnData);

        if ($result !== 'imported') {
            return response('OK', 200);
        }

        return response('OK', 200);
    }
}
