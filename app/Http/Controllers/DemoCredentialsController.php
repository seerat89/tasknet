<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class DemoCredentialsController extends Controller
{
    private const CACHE_CONTROL = 'no-store, private';

    /**
     * Show the credentials once, until the user continues.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $credentials = $this->credentials($request);

        if ($credentials === null) {
            return redirect()->route('dashboard');
        }

        return response()->view('demo.credentials', [
            'credentials' => $credentials,
            'credentialsText' => $this->credentialsText($credentials),
            'expiryLabel' => $this->expiryLabel($credentials['expires_at']),
        ])->header('Cache-Control', self::CACHE_CONTROL);
    }

    /**
     * Download the credentials as a plain text file without writing anything to storage.
     */
    public function download(Request $request): Response|RedirectResponse
    {
        $credentials = $this->credentials($request);

        if ($credentials === null) {
            return redirect()->route('dashboard');
        }

        return response($this->credentialsText($credentials), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tasknet-demo-login.txt"',
            'Cache-Control' => self::CACHE_CONTROL,
        ]);
    }

    /**
     * Discard the plaintext credentials and continue into the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('demo_credentials');

        return redirect()->route('dashboard');
    }

    /**
     * Read the credentials stored in the session by the demo start flow.
     *
     * @return array{demo_id: string, password: string, expires_at: Carbon}|null
     */
    private function credentials(Request $request): ?array
    {
        $stored = $request->session()->get('demo_credentials');

        if (! is_array($stored)) {
            return null;
        }

        return [
            'demo_id' => $stored['demo_id'],
            'password' => Crypt::decryptString($stored['password']),
            'expires_at' => Carbon::parse($stored['expires_at']),
        ];
    }

    /**
     * Format an expiry moment for display.
     */
    private function expiryLabel(Carbon $expiresAt): string
    {
        return $expiresAt->copy()->utc()->format('F j, Y \a\t H:i').' UTC';
    }

    /**
     * Build the plain text login details used for copying and downloading.
     *
     * @param  array{demo_id: string, password: string, expires_at: Carbon}  $credentials
     */
    private function credentialsText(array $credentials): string
    {
        return implode("\n", [
            'TaskNet Demo Workspace',
            '',
            'URL: '.url('/'),
            'Demo ID: '.$credentials['demo_id'],
            'Password: '.$credentials['password'],
            'Expires: '.$this->expiryLabel($credentials['expires_at']),
            '',
            'This workspace is temporary and will be automatically deleted after '.config('tasknet.demo.lifetime_days').' days.',
            'Do not store confidential, personal, sensitive, or important data in it.',
            '',
        ]);
    }
}
