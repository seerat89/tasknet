<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDemoWorkspace
{
    /**
     * Characters used in a Demo ID. Visually ambiguous characters (0, O, 1, I, L) are left out.
     */
    private const DEMO_ID_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const DEMO_ID_LENGTH = 6;

    private const MAX_ATTEMPTS = 5;

    /**
     * Create an isolated demo workspace with a generated Demo ID and password.
     *
     * The plaintext password is returned once for the credential handoff; only its hash is stored.
     *
     * @return array{user: User, password: string}
     */
    public function create(): array
    {
        $password = Str::password(20, symbols: false, spaces: false);

        for ($attempt = 1; ; $attempt++) {
            $demoId = $this->generateDemoId();

            try {
                $user = DB::transaction(fn (): User => User::create([
                    'name' => 'Demo '.Str::after($demoId, 'TN-'),
                    'email' => Str::lower($demoId).'@tasknet.local',
                    'password' => $password,
                    'demo_id' => $demoId,
                    'is_demo' => true,
                    'expires_at' => now()->addDays(config('tasknet.demo.lifetime_days')),
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }

                continue;
            }

            return ['user' => $user, 'password' => $password];
        }
    }

    /**
     * Generate a random Demo ID such as TN-7K4P2Q.
     */
    private function generateDemoId(): string
    {
        $maxIndex = strlen(self::DEMO_ID_ALPHABET) - 1;
        $suffix = '';

        for ($position = 0; $position < self::DEMO_ID_LENGTH; $position++) {
            $suffix .= self::DEMO_ID_ALPHABET[random_int(0, $maxIndex)];
        }

        return 'TN-'.$suffix;
    }
}
