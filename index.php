<?php

// =================== Secure WMS =====================
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

function getParcelPerformAccessToken()
{
    $clientId = '7zp336K9gmTrAPdrmqwFLgYauMkqihpudaMDpIfi';
    $clientSecret = 't56XXfb4IZMrULPZDm5id7kg9z3TNYcXeyhYE9kjnGAV9TVC6Xclv3PQzw9fOkmJCEVz0G';

    $basicAuth = base64_encode("{$clientId}:{$clientSecret}");
    // return $basicAuth;

    $ch = curl_init('https://api.parcelperform.com/auth/oauth/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic {$basicAuth}",
        "Content-Type: application/x-www-form-urlencoded",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
    ]));

    $response = curl_exec($ch);
    return $response;

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        error_log("❌ ParcelPerform Token Error — HTTP $status: $response");
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// =================== ParcelPerform =====================
function sendShipmentsToParcelPerform()
{
    $extensivOrders = getExtensivOrders();

    if (!$extensivOrders || !isset($extensivOrders['ResourceList'])) {
        echo "No orders to process.<br>";
        return;
    }

    $orders = $extensivOrders['ResourceList'];

    foreach ($orders as $order) {
        try {
            $readOnly = $order['ReadOnly'];
            $package = $readOnly['Packages'][0] ?? [];
            $contents = $package['PackageContents'] ?? [];
            $itemsData = $order['OrderItems'] ?? [];
            $shipTo = $order['ShipTo'] ?? [];
            $routing = $order['RoutingInfo'] ?? [];

            // Map order items
            $itemsMap = [];
            foreach ($itemsData as $item) {
                $itemsMap[$item['ReadOnly']['OrderItemId']] = $item;
            }

            $lineItems = [];
            foreach ($contents as $content) {
                $orderItemId = $content['OrderItemId'];
                if (!isset($itemsMap[$orderItemId])) continue;

                $item = $itemsMap[$orderItemId];

                $weightLb = floatval($item['WeightImperial'] ?? 0);
                $weightG = $weightLb > 0 ? round($weightLb * 453.592) : 0;

                $lineItems[] = [
                    'product_name' => $item['ItemIdentifier']['Sku'],
                    'product_id' => $item['ItemIdentifier']['Sku'],
                    'product_reference' => $item['ItemIdentifier']['Sku'],
                    'product_description' => $item['ItemIdentifier']['Sku'],
                    'product_weight' => $weightG,
                    'product_weight_unit' => 'g',
                    'quantity' => (int)$content['Qty'],
                    'currency_code' => 'USD',
                    'product_cost' => 0.00,
                    'subtotal_cost' => 0.00,
                ];
            }

            $payload = [
                'shipment_id' => $order['ReferenceNum'],
                'tracking_number' => $package['TrackingNumber'] ?? 'no-tracking',
                'carrier_reference' => $routing['Carrier'] ?? 'unknown',
                'carrier_code' => strtolower($routing['ScacCode'] ?? 'unknown'),
                'shipment_date' => $readOnly['ShipDate'] ?? date("Y-m-d"),
                'origin_country_code' => 'US',
                'destination_country_code' => $shipTo['Country'] ?? '',
                'status' => 'created',
                'description_of_goods' => 'General merchandise',
                'handling_instructions' => 'fragile',
                'line_items' => $lineItems,
                'recipient_address' => [
                    'first_name' => $shipTo['Name'] ?? "",
                    'line1' => $shipTo['Address1'] ?? "",
                    'city' => $shipTo['City'] ?? "",
                    'state_or_province' => $shipTo['State'] ?? "",
                    'postal_code' => $shipTo['Zip'] ?? "",
                    'country_code' => $shipTo['Country'] ?? "",
                    'email' => $shipTo['EmailAddress'] ?? "",
                    'phone' => $shipTo['PhoneNumber'] ?? "",
                    'location_type' => 'residential',
                ],
                'payment_type' => 'Card',
                'item_count' => count($lineItems),
                'length' => '30 cm',
                'width' => '30 cm',
                'height' => '30 cm',
                'weight' => '1 kg',
            ];

            // Replace with your actual access token
            // $accessToken = 'eyJhbGciOiJSUzI1NiIsImtpZCI6ImM0bEd3OUR1MGx5bTdVTzl6OXZrQ1Rma1hLeW0tSy1NUHVBLWVsandLOFUifQ.eyJhdWQiOiI3enAzMzZLOWdtVHJBUGRybXF3RkxnWWF1TWtxaWhwdWRhTURwSWZpIiwiZXhwIjoxNzQ0MjA2MTUzLCJpYXQiOjE3NDQyMDI1NTMsImlzcyI6ImNvZ25pdG8tbWlncmF0ZSIsInNjb3BlIjoiUE9TVDphdXRoL29hdXRoL3Rva2VuLyBQT1NUOnBhcmNlbC92Mi8gR0VUOnBhcmNlbC92Mi8qLyBHRVQ6L2F1dGgvb2F1dGgvdGVzdC10b2tlbi8gUE9TVDovdjUvc2hpcG1lbnQvIFBPU1Q6L3Y1L2V2ZW50cy9jcmVhdGUvIEdFVDovdjUvc2hpcG1lbnQvbGlzdC8gR0VUOi92NS9zaGlwbWVudC9kZXRhaWxzLyBQT1NUOnY1L3NoaXBtZW50L3VwZGF0ZS8gUE9TVDp2NS9zaGlwbWVudC90cmlnZ2VyLWFkaG9jLXVwZGF0ZS8gUE9TVDphdXRoL29hdXRoL3Rlc3Qtc2VudHJ5LyBQT1NUOnY1L2Jvb2tpbmcvIFBPU1Q6djUvcmV0dXJuLyBQT1NUOnY1L3JldHVybi91cGRhdGUgR0VUOnY1L25vdGlmaWNhdGlvbi9mYWlsZWQtd2ViaG9vay1jb3VudCBQT1NUOnY1L25vdGlmaWNhdGlvbi9yZXNlbmQtZmFpbGVkLXdlYmhvb2tzIFBPU1Q6djUtMi0wL3NoaXBtZW50L3VwZGF0ZS8gR0VUOnY1LTItMC9zaGlwbWVudC9kZXRhaWxzLyBHRVQ6djUtMi0xL3NoaXBtZW50L2RldGFpbHMvIFBPU1Q6L3YxL2VkZC9jaGVja291dC8gR0VUOnY1L3NoaXBtZW50cy9kb2N1bWVudHMvIEdFVDp2NS9zaGlwbWVudHMvZG9jdW1lbnRzL2xhYmVscy8gR0VUOnY1L3NoaXBtZW50cy9kb2N1bWVudHMvKi8gR0VUOnY1LTItMy9zaGlwbWVudC9kZXRhaWxzLyBHRVQ6djUtMi00L3NoaXBtZW50L2RldGFpbHMvIFBPU1Q6djUtMi0wL3NoaXBtZW50L3RyYWNrLyBHRVQ6djUtMy0wL3NoaXBtZW50L2RldGFpbHMvIEdFVDp2NS9jYXJyaWVyLWNvbmZpZ3MvIiwic3ViIjoiN3pwMzM2SzlnbVRyQVBkcm1xd0ZMZ1lhdU1rcWlocHVkYU1EcElmaSJ9.RD82IlOq8EBb2lAJHHnOmLsd6LCZGjzeedd7GDCTUFhkGg_ldnpPMGOwD8Xh_cx_SQiChU0S9pgnGQlPQdNlQ4rkn9AmC8Ojh-IEOlmq1uMspHFjb1WIsm9GhuPEy0VHbvZmoZa1MCxpmaEsu23KYGIU6IQqUu_qRFSM8RbbpPiCc1hcjXZOuuSTmqMY5JM7jHGPaVorxxF-IIc_odIeEXRl6od_yDnT1MLdWqe8fMy2t1YbQnDOZYiYgC1hj3644tg39_bot7U9GRx25r4uHBjKwrK4frrVW9JWMsUEC2H9BtQWJNi2sHueuwPTH9jvVrXfGsP4t5DjtNqGvc_Zkw';
            $accessToken = getParcelPerformAccessToken();

            $ch = curl_init('https://api.parcelperform.com/v5/shipment/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json",
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 || $status === 201) {
                echo "✅ Sent shipment: " . $order['ReferenceNum'] . "<br>";
            } else {
                echo "❌ Failed to send shipment: " . $order['ReferenceNum'] . " (Status: $status)<br>";
            }

        } catch (Throwable $e) {
            echo "Error processing order: " . ($order['ReferenceNum'] ?? 'unknown') . " - " . $e->getMessage() . "<br>";
        }
    }

    echo "Done sending all shipments.<br>";
}

// =================== Run it =====================
// sendShipmentsToParcelPerform();
echo "Access Token:- ". getParcelPerformAccessToken();