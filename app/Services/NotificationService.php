<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\User;
use Google\Client as GoogleClient;

class NotificationService
{
    private string $projectId;
    private string $credentialsPath;

    public function __construct()
    {
        $this->projectId       = config('services.firebase.project_id');
        $this->credentialsPath = config('services.firebase.credentials');
    }

    private function getAccessToken(): string
    {
        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (!$user->fcm_token) return;

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token'        => $user->fcm_token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'android' => [
                        'notification' => [
                            'sound'       => 'default',
                            'click_action'=> 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                    'data' => array_map('strval', $data),
                ],
            ]);


        } catch (\Exception $e) {
            \Log::error('FCM Error: ' . $e->getMessage());
        }
    }
}