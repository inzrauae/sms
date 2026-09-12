<?php

namespace App\Services;

use App\Exceptions\TextLkException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Text.lk v3 client.
 *
 * One upstream account funds the whole portal, so this token never leaves the
 * server. Per-tenant isolation is enforced in our own database, not here.
 */
class TextLk
{
    private function baseUrl(): string
    {
        return rtrim(config('services.textlk.base_url'), '/');
    }

    private function token(): string
    {
        $value = config('services.textlk.token');
        if (!$value) {
            throw new TextLkException('TEXTLK_API_TOKEN is not set. Add it to your .env file.');
        }

        return $value;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->timeout((int) (config('services.textlk.timeout') / 1000));
    }

    private function request(string $method, string $endpoint, array $body = [], array $query = [], int $retries = 1): array
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        try {
            $request = $this->client();
            if ($query) {
                $request = $request->withQueryParameters($query);
            }

            /** @var Response $response */
            $response = match ($method) {
                'GET' => $request->get($endpoint),
                'POST' => $request->post($endpoint, $body),
                'PATCH' => $request->patch($endpoint, $body),
                'DELETE' => $request->delete($endpoint, $body),
            };
        } catch (ConnectionException $e) {
            throw new TextLkException("Could not reach Text.lk: {$e->getMessage()}");
        }

        $payload = $response->json() ?? ['raw' => $response->body()];

        if ($response->failed()) {
            // Retry once on upstream 5xx — but never on a send, because a
            // timeout does not prove the message was not accepted.
            if ($response->serverError() && $retries > 0 && $method === 'GET') {
                return $this->request($method, $endpoint, $body, $query, $retries - 1);
            }

            throw new TextLkException(
                $payload['message'] ?? "Text.lk responded with {$response->status()}",
                $response->status(),
                $payload
            );
        }

        if (($payload['status'] ?? null) === 'error') {
            throw new TextLkException($payload['message'] ?? 'Text.lk rejected the request.', $response->status(), $payload);
        }

        return $payload;
    }

    /* ---------------------------------------------------------------- profile */

    public function getBalance(): array
    {
        return $this->request('GET', '/balance');
    }

    public function getProfile(): array
    {
        return $this->request('GET', '/me');
    }

    /* ------------------------------------------------------------------- sms */

    public function sendSms(array $p): array
    {
        $body = [
            'recipient' => $p['recipient'],
            'sender_id' => $p['sender_id'],
            'type' => $p['type'] ?? 'plain',
            'message' => $p['message'],
        ];
        if (!empty($p['schedule_time'])) {
            $body['schedule_time'] = $p['schedule_time'];
        }
        if (!empty($p['dlt_template_id'])) {
            $body['dlt_template_id'] = $p['dlt_template_id'];
        }

        return $this->request('POST', '/sms/send', $body, retries: 0);
    }

    /**
     * Campaign send against a stored contact group.
     * The published docs list `contact_list_id` in the parameter table but
     * use `recipient` in the sample payload, so both keys go out.
     */
    public function sendCampaign(array $p): array
    {
        $body = [
            'contact_list_id' => $p['contact_list_id'],
            'recipient' => $p['contact_list_id'],
            'sender_id' => $p['sender_id'],
            'type' => $p['type'] ?? 'plain',
            'message' => $p['message'],
        ];
        if (!empty($p['schedule_time'])) {
            $body['schedule_time'] = $p['schedule_time'];
        }
        if (!empty($p['dlt_template_id'])) {
            $body['dlt_template_id'] = $p['dlt_template_id'];
        }

        return $this->request('POST', '/sms/campaign', $body, retries: 0);
    }

    public function viewSms(string $uid): array
    {
        return $this->request('GET', '/sms/' . rawurlencode($uid));
    }

    public function viewCampaign(string $uid): array
    {
        return $this->request('GET', '/campaign/' . rawurlencode($uid) . '/view');
    }

    /* -------------------------------------------------------- contact groups */

    public function listGroups(): array
    {
        return $this->request('GET', '/contacts');
    }

    public function createGroup(string $name): array
    {
        return $this->request('POST', '/contacts', ['name' => $name]);
    }

    public function updateGroup(string $groupId, string $name): array
    {
        return $this->request('PATCH', '/contacts/' . rawurlencode($groupId), ['name' => $name]);
    }

    public function deleteGroup(string $groupId): array
    {
        return $this->request('DELETE', '/contacts/' . rawurlencode($groupId));
    }

    /* --------------------------------------------------------------- contacts */

    public function listContacts(string $groupId, int $page = 1): array
    {
        return $this->request('POST', '/contacts/' . rawurlencode($groupId) . '/all', query: ['page' => $page]);
    }

    public function createContact(string $groupId, array $fields): array
    {
        return $this->request('POST', '/contacts/' . rawurlencode($groupId) . '/store', $fields);
    }

    public function deleteContact(string $groupId, string $contactUid): array
    {
        return $this->request('DELETE', '/contacts/' . rawurlencode($groupId) . '/delete/' . rawurlencode($contactUid));
    }

    /** Pull the first `uid`-looking value out of a loosely typed response. */
    public function extractUid(array $payload): ?string
    {
        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }
        if (!empty($data['uid'])) {
            return $data['uid'];
        }
        if (!empty($data['data']['uid'])) {
            return $data['data']['uid'];
        }
        if (isset($data[0]['uid'])) {
            return $data[0]['uid'];
        }

        return null;
    }
}
