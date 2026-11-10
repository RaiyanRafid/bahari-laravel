<?php

namespace Bahari\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bahari\Sso\Client client()
 * @method static string url(string $name, array $query = [])
 * @method static string walletUrl(?string $returnTo = null, ?string $amount = null)
 * @method static \Bahari\Laravel\Contracts\StoredSession|null session(?\Illuminate\Http\Request $request = null)
 * @method static array navigation(\Bahari\Laravel\Contracts\StoredSession $session)
 * @method static \Bahari\Sso\Http\Response updatePinned(\Bahari\Laravel\Contracts\StoredSession $session, array $keys)
 * @method static array api(string $method, string $path, array $data = [], string $scope = 'identity:lookup')
 * @method static array checkoutOptions(string $externalUserId)
 * @method static array startTopUp(string $externalUserId, array $topUp)
 * @method static array topUp(string $externalUserId, string $reference)
 * @method static array createInvoice(array $invoice)
 * @method static array invoice(string $reference)
 * @method static array voidInvoice(string $reference)
 * @method static array createSubscription(array $subscription)
 * @method static array subscription(string $reference)
 * @method static array updateSubscription(string $reference, array $changes)
 * @method static array cancelSubscription(string $reference, string $when = 'period_end')
 * @method static array resumeSubscription(string $reference)
 * @method static array customerSubscriptions(string $userId)
 *
 * @see \Bahari\Laravel\BahariManager
 */
class Bahari extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Bahari\Laravel\BahariManager::class;
    }
}
