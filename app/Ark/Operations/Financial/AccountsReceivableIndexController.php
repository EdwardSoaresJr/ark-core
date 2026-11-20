<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountsReceivableIndexController extends Controller
{
    public function __invoke(Request $request, AccountsReceivableOutstandingQuery $query): View
    {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        $search = trim((string) $request->query('q', ''));
        $dashboard = $query->dashboard($search !== '' ? $search : null);

        return view('operations.financials.accounts-receivable.index', [
            'dashboard' => $dashboard,
            'search' => $search,
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }
}
