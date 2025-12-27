<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\UserFcmToken;
use App\Models\User;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        // env('FIREBASE_CREDENTIALS') contoh: storage/app/firebase/firebase-service-account.json
        $relative = env('FIREBASE_CREDENTIALS');

        if (!$relative) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS belum di-set di .env');
        }

        // kalau sudah absolute path, pakai langsung. kalau belum, jadikan absolute dari base_path()
        $credentialsPath = str_starts_with($relative, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:\\\\/i', $relative)
            ? $relative
            : base_path($relative);

        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException("Firebase credentials tidak ditemukan: {$credentialsPath}");
        }

        $this->messaging = (new Factory)
            ->withServiceAccount($credentialsPath)
            ->createMessaging();
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) return;

        // FCM data harus string
        $dataStr = [];
        foreach ($data as $k => $v) {
            $dataStr[(string) $k] = is_string($v) ? $v : json_encode($v);
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($dataStr);

        // kirim banyak token sekaligus
        $this->messaging->sendMulticast($message, $tokens);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = UserFcmToken::where('user_id', $user->id)->pluck('token')->toArray();
        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToRole(string $role, string $title, string $body, array $data = []): void
    {
        $tokens = UserFcmToken::whereHas('user', function ($q) use ($role) {
                $q->where('role', $role);
            })
            ->pluck('token')
            ->toArray();

        $this->sendToTokens($tokens, $title, $body, $data);
    }
}
