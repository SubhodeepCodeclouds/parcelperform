<?php

function getWmsToken()
{
    $clientId = '58fd6f42-d233-4295-ac91-fc67ffdf2865';
    $clientSecret = 'Sf5wLYHEG38OVOK4qEoHBwjS7cHqHlcy';

    $basicAuth = base64_encode("{$clientId}:{$clientSecret}");

    $ch = curl_init('https://secure-wms.com/AuthServer/api/Token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic {$basicAuth}",
        "Content-Type: application/x-www-form-urlencoded"
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
    ]));

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);

    if ($status !== 200) {
        error_log("WMS Auth failed. Status: $status, Response: $response");
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function getExtensivOrders()
{
    $accessToken = getWmsToken();
    if (!$accessToken) {
        error_log("No WMS access token available.");
        return null;
    }

    $url = 'https://secure-wms.com/orders';
    $params = http_build_query([
        'pgsiz' => 10,
        'pgnum' => 1,
        'detail' => 'All',
        'itemdetail' => 'All',
    ]);

    $ch = curl_init("{$url}?{$params}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        error_log("Failed to fetch orders from Secure WMS. Status: $status, Response: $response");
        return null;
    }

    return json_decode($response, true);
}
