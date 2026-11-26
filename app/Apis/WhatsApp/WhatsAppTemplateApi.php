<?php

namespace App\Apis\WhatsApp;

use App\Configs\WhatsAppConfig;
use App\Models\WhatsAppConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppTemplateApi
{
    public function __construct(public WhatsAppConfig $configuration) {}

    /** @return list<array<string, mixed>> */
    public function listTemplates(WhatsAppConnection $connection): array
    {
        $templates = [];
        $after = null;

        for ($page = 0; $page < 100; $page++) {
            $query = ['fields' => 'id,name,language,status,category,components', 'limit' => 100];
            if ($after !== null) {
                $query['after'] = $after;
            }

            $response = $this->request($connection)
                ->get('/'.$connection->business_account_id.'/message_templates', $query)
                ->throw()
                ->json();

            if (! is_array($response) || ! is_array($response['data'] ?? null)) {
                throw new RuntimeException('Meta returned an invalid message template list response.');
            }

            foreach ($response['data'] as $template) {
                if (is_array($template)) {
                    $templates[] = $template;
                }
            }

            $nextAfter = data_get($response, 'paging.cursors.after');
            if (! is_string($nextAfter) || $nextAfter === '' || $nextAfter === $after) {
                return $templates;
            }

            $after = $nextAfter;
        }

        throw new RuntimeException('Meta template pagination exceeded the supported reconciliation limit.');
    }

    /** @param list<array<string, mixed>> $components @return array<string, mixed> */
    public function createTemplate(
        WhatsAppConnection $connection,
        string $name,
        string $language,
        string $category,
        array $components,
    ): array {
        $response = $this->request($connection)
            ->post('/'.$connection->business_account_id.'/message_templates', [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'components' => $components,
            ])
            ->throw()
            ->json();

        if (! is_array($response) || ! is_string($response['id'] ?? null) || ! is_string($response['status'] ?? null)) {
            throw new RuntimeException('Meta returned an incomplete message template submission response.');
        }

        return $response;
    }

    private function request(WhatsAppConnection $connection): PendingRequest
    {
        $baseUrl = $this->configuration->graphApiBaseUrl();
        if (! is_string($connection->access_token) || $connection->access_token === ''
            || ! is_string($connection->business_account_id) || $connection->business_account_id === ''
            || $baseUrl === null) {
            throw new RuntimeException('The WhatsApp Cloud API template connection is incomplete.');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($connection->access_token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
