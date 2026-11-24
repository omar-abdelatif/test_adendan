<?php

namespace App\Modules\Finance\Http\Middleware;

use App\Modules\Finance\Domain\Enums\Fund;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The fund pages serve the sponsorship and new-tombs funds only (the
 * association's money keeps its own screens), and each needs that fund's
 * permission: "donations" for donors and donations, "accounts" for the
 * safe, the bank and transfers.
 */
class FundAccess
{
    public function handle(Request $request, Closure $next, string $area = 'donations'): Response
    {
        $value = $request->route('fund');
        $fund = $value instanceof Fund ? $value : Fund::tryFrom((string) $value);
        abort_unless($fund !== null && in_array($fund, Fund::separate(), true), 404);

        $permission = $area === 'accounts' ? $fund->accountsPermission() : $fund->permission();
        abort_unless($request->user()?->can((string) $permission), 403);

        return $next($request);
    }
}
