<?php

namespace XLaravel\Listmonk\Http;

use Illuminate\Support\Facades\Http;

class Client
{
    protected string $authHeader;

    public function __construct(
        protected string $baseUrl,
        protected string $user,
        protected string $token
    )
    {
        $this->authHeader = 'token ' . $this->user. ':' . $this->token;
    }

    public function request(string $method, string $endpoint, array $data = [])
    {
        $response = Http::withHeaders([
            'Authorization' => $this->authHeader,
            'Accept' => 'application/json',
        ])
            ->baseUrl($this->baseUrl)
            ->send($method, $endpoint, [
                'json' => $data,
            ]);

        return $response;
    }
}
