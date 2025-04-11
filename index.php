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

/**
 * Get Extencive Orders
 */
function getExtensivOrders()
{
    $accessToken = getWmsToken();
    if (!$accessToken) {
        error_log("No WMS access token available.");
        return null;
    }

    $url = 'https://secure-wms.com/orders';
    $params = http_build_query([
        'pgsiz' => 100,
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

/**
 * Get Percel Platform access token
 * Token Validity only 1hrs
 */
function getParcelPerformAccessToken()
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.parcelperform.com/auth/oauth/token/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic N3pwMzM2SzlnbVRyQVBkcm1xd0ZMZ1lhdU1rcWlocHVkYU1EcElmaTp0NTZYWGZiNElaTXJVTFBaRG01aWQ3a2c5ejNUTlljWGV5aFlFOWtqbkdBVjlUVkM2WGNsdjNQUXp3OWZPa21KQ0VWejBH'
    ),
    ));

    $response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($status !== 200) {
        error_log("ParcelPerform Token Error — HTTP $status: $response");
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Send Data to ParcelPerform & create shipping
 */
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
                'tracking_number' => $package['TrackingNumber'] ?? $routing['TrackingNumber'] ?? 'no-tracking',
                'carrier_reference' => $routing['Carrier'] ?? 'unknown',
                'carrier_code' => strtolower($routing['ScacCode'] ?? 'unknown'),
                'shipment_date' => $readOnly['ShipDate'] ?? $package['CreateDate'] ?? date("Y-m-d"),
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
                'length' => $package['Length'] ?? "",
                'width' => $package['Width'] ?? "",
                'height' => $package['Height'] ?? "",
                'weight' => $package['Weight'] ?? "",
            ];

            // Replace with your actual access token
            $accessToken = getParcelPerformAccessToken();
            // echo $accessToken;
            // die;

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
sendShipmentsToParcelPerform();
// echo "Access Token:- ". getParcelPerformAccessToken();