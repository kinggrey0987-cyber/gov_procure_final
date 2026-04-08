<?php

namespace App\Http\Controllers\AdminTech;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailDebugController extends Controller
{
    public function logRuntimeConfig(Request $request): RedirectResponse
    {
        $context = $this->mailContext($request);

        Log::info('debug.mail.runtime_config', $context);

        return redirect()->route('admin-tech.dashboard')
            ->with('success', 'Temporary mail runtime config logged (secrets excluded).');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $context = $this->mailContext($request);
        $recipient = (string) ($request->user()?->email ?? '');
        $startedAt = microtime(true);

        try {
            Mail::raw(
                'GovSupply Connect temporary mail debug check.'.PHP_EOL.'Timestamp: '.now()->toIso8601String(),
                static function ($message) use ($recipient): void {
                    $message
                        ->to($recipient)
                        ->subject('GovSupply Connect Mail Debug Check');
                }
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::info('debug.mail.test_send.success', array_merge($context, [
                'recipient' => $recipient,
                'duration_ms' => $durationMs,
            ]));

            return redirect()->route('admin-tech.dashboard')
                ->with('success', 'Temporary mail test sent. Check logs for details.');
        } catch (Throwable $throwable) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::warning('debug.mail.test_send.failure', array_merge($context, [
                'recipient' => $recipient,
                'duration_ms' => $durationMs,
                'exception_class' => $throwable::class,
                'exception_message' => $throwable->getMessage(),
            ]));

            report($throwable);

            return redirect()->route('admin-tech.dashboard')
                ->with('error', 'Temporary mail test failed. Check logs for details.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mailContext(Request $request): array
    {
        return [
            'triggered_by_user_id' => (int) ($request->user()?->id ?? 0),
            'mail_default' => config('mail.default'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'smtp_encryption' => config('mail.mailers.smtp.encryption'),
            'smtp_username' => config('mail.mailers.smtp.username'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ];
    }
}
