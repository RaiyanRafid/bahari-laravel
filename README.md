# bahari/laravel

Sign in with Bahari Accounts in a Laravel app: customers and platform admins, session checks on every request,
back-channel logout, the platform switcher and wallet links.

You configure the issuer and your client credentials. Everything else, including the admin host and all API URLs,
is read from `https://accounts.baharione.com/.well-known/bahari-configuration`. When Bahari moves a domain, your app
follows it without code or `.env` changes.

## Install

```bash
composer require bahari/laravel
php artisan vendor:publish --tag=bahari-config
```

```dotenv
BAHARI_ACCOUNTS_ISSUER=https://accounts.baharione.com
BAHARI_CLIENT_ID=...
BAHARI_CLIENT_SECRET=...
BAHARI_PLATFORM_KEY=your-platform
```

Connect with the one-time code from Bahari admin › Connected apps. It registers the package's URLs and writes the
credentials to `.env` without printing the secret:

```bash
php artisan bahari:connect bsc_xxxxxxxx [--admin-url=https://admin.example.com] [--billing-webhook=https://...] [--no-admin]
php artisan config:clear
```

The URLs it registers:

| Purpose | URL |
| --- | --- |
| Customer callback | `https://your-app/auth/client/callback` |
| Admin callback | `https://your-app/auth/admin/callback` |
| Back-channel logout | `https://your-app/auth/backchannel-logout` |

## Store sessions

Either use the bundled table and tell the package how to find your users:

```bash
php artisan vendor:publish --tag=bahari-migrations && php artisan migrate
```

```php
// config/bahari.php
'user_resolver' => App\Support\BahariUsers::class,

// app/Support/BahariUsers.php
class BahariUsers implements \Bahari\Laravel\Contracts\UserResolver
{
    public function resolve(string $kind, array $profile): \Illuminate\Contracts\Auth\Authenticatable
    {
        return User::updateOrCreate(['bahari_id' => $profile['id'], 'type' => $kind], ['name' => $profile['name'], 'email' => $profile['email']]);
    }
}
```

or point `session_repository` at your own `Bahari\Laravel\Contracts\SessionRepository`.

## Protect routes

```php
Route::middleware('bahari.auth')->group(...);          // kind from the path (config bahari.kinds)
Route::middleware('bahari.auth:admin')->group(...);    // force a kind
// Filament: ->authMiddleware([\Bahari\Laravel\Http\Middleware\Authenticate::class], isPersistent: true)
```

Links: one **Sign in** button `route('bahari.signin')`, an optional administrator link
`route('bahari.login', ['kind' => 'admin'])`, and a POST form to `route('bahari.logout', ['kind' => 'client'])`.

`bahari.signin` signs everyone in as a customer. When Bahari reports that the account is linked to an administrator of
this platform (`admin_access` in the profile), the person continues to admin sign-in: Bahari asks for the admin
password and 2FA (or sets up 2FA first) and they land in the admin area. The admin session is only created when the
admin account belongs to the same person. Turn this off with `'auto_admin' => false`.

## Use Bahari

```php
use Bahari\Laravel\Facades\Bahari;

Bahari::session();                   // the checked session of this request
Bahari::session()->getBahariProfile();
Bahari::url('client.profile_url');   // any URL from discovery
Bahari::url('admin.panel_url');
Bahari::walletUrl('/dashboard');
Bahari::navigation(Bahari::session());
Bahari::api('GET', 'platform-wallet-balances');
```

## Roles, maintenance and branding (0.5.0)

Bahari decides who is an administrator of your app (Connected apps › Access & RBAC) and whether it is in maintenance.

```php
Bahari::role();      // 'SUPER_ADMIN', 'ADMIN' or 'USER' for the current session
Bahari::isAdmin();   // true only for an admin session of this app
Bahari::maintenance(); // ['enabled' => bool, 'message' => ?string]
Bahari::branding();  // name, logo_light_url, logo_dark_url, favicon_url, brand_color, access_mode, support
```

Add the `bahari.branding` middleware to your web group to share `$bahariBranding` and `$bahariMaintenance` with every
view, then show a banner:

```blade
@if ($bahariMaintenance['enabled'])
    <div class="banner">{{ $bahariMaintenance['message'] ?? 'We are doing maintenance. Some features may be unavailable.' }}</div>
@endif
```

Branding is cached for `BAHARI_BRANDING_TTL` seconds (default 60); the last good copy is used if Bahari is unreachable.

**Losing access ends sessions at once.** When someone loses access (removed from the app's administrators, admin
revoked or suspended, account suspended, app switched to staff only), Bahari calls your back-channel logout endpoint.
The package ends their customer sessions and, from 0.5.0, their admin sessions too. A custom session repository
should implement `Bahari\Laravel\Contracts\RevokesLinkedSessions` (end admin sessions whose profile `user_id` is the
given account); without it, admin sessions still end at their next check with Bahari.

## Updates

The package sends `X-Bahari-SDK: bahari/laravel/<version>`. Bahari shows the version per app and flags outdated or
unsupported versions; the package also logs a warning once a day. Update with
`composer update bahari/laravel bahari/sso-php`.


## Billing (features "Invoices" and "Subscriptions")

Your app sets its prices; Bahari issues the invoices, takes the payment and renews subscriptions. Use your own UUID
as `reference` (repeating a call returns the same record).

```php
$sub = Bahari::createSubscription([
    'user_id' => $user->central_id,           // the customer's Bahari ID
    'reference' => (string) Str::uuid(),       // store it with your service
    'plan_name' => 'PBX Starter', 'cycle' => 'monthly', 'amount' => '999.00',
    'external_id' => (string) $tenant->id, 'return_to' => '/dashboard/services',
]);
return redirect()->away($sub['data']['open_invoice']['checkout_url']);   // customer pays on Bahari, comes back
```

`Bahari::createInvoice([...])` works the same for one-off purchases (`items` with `description`, `quantity`,
`unit_price`). Provision on the billing webhook events (`subscription.activated`, `invoice.paid`, ...) or by checking
`Bahari::subscription($reference)`. With "Charge without confirmation" turned on, pass `'pay' => 'wallet'` to charge
the wallet directly (`payment.status` tells you if it worked). Also: `updateSubscription`, `cancelSubscription`
(`'period_end'` or `'now'`), `resumeSubscription`, `customerSubscriptions`, `invoice`, `voidInvoice`.

## No local authentication

The app has no sign-in of its own. Bahari Accounts does sign-in, registration, passwords, password reset,
verification, 2FA and administrator sign-in. Remove any login or registration forms, password fields and local admin
logins, and store no passwords: local users keep only the Bahari ID and copies of name and email.

| Button | Route |
| --- | --- |
| Sign in | `GET /auth/login` |
| Create account | `GET /auth/register` |
| Administrator sign-in (optional) | `GET /auth/admin/login` |
| Sign out | `POST /auth/{client\|admin}/logout` |

Clicking Sign in opens Bahari ("Sign in to continue to *your app*"). After sign-in or registration Bahari sends the
person straight back to the page they wanted in your app; connected apps see no consent screen. Sign out ends the app
session and Bahari's, then returns to your home page without a confirmation page (the package sends the ID token as
`id_token_hint`).
