<?php

class WmsOrder {
    private $config;

    public function __construct($config) {
        $this->config = $config['wms'];
    }

    public function getAccessToken() {
        $auth = base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']);
        $headers = ['Authorization: Basic ' . $auth];

        $response = httpPost($this->config['token_url'], $headers, [
            'grant_type' => 'client_credentials'
        ], true);

        if ($response['code'] !== 200) return null;

        $data = json_decode($response['body'], true);
        return $data['access_token'] ?? null;
    }

    public function getOrders($token) {
        $headers = ['Authorization: Bearer ' . $token];
        $query = [
            'pgsiz' => 10,
            'pgnum' => 1,
            'detail' => 'All',
            'itemdetail' => 'All'
        ];

        $response = httpGet($this->config['order_url'], $headers, $query);

        if ($response['code'] !== 200) return null;

        return json_decode($response['body'], true);
    }
}
