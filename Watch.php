<?php
// Check if cURL is enabled on the server
if (!function_exists('curl_init')) {
    echo json_encode(['ok' => false, 'error' => 'cURL is not enabled on the server.']);
    exit;
}

// Function to make cURL requests
function makeCurlRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        echo json_encode(['ok' => false, 'error' => 'Curl error: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);
    return $response;
}

// Get and sanitize URL parameter
$url = isset($_GET['url']) ? filter_var($_GET['url'], FILTER_SANITIZE_URL) : null;

if ($url) {
    // Extract ID using regex
    preg_match('/\/s\/([a-zA-Z0-9_-]+)(?:[?&].*)?$/', $url, $matches);
    $id = $matches[1] ?? null;

    if ($id) {
        // API URL for first request
        $apiUrl = "https://terabox.hnn.workers.dev/api/get-info?shorturl={$id}&pwd=";

        // First API Request
        $response = makeCurlRequest($apiUrl);
        $jsonResult = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['ok' => false, 'error' => 'Error decoding JSON response: ' . json_last_error_msg()]);
            exit;
        }

        // Prepare data for second request
        if (isset($jsonResult['shareid'], $jsonResult['uk'], $jsonResult['sign'], $jsonResult['timestamp'], $jsonResult['list'][0]['fs_id'])) {
            $postData = json_encode([
                'shareid' => $jsonResult['shareid'],
                'uk' => $jsonResult['uk'],
                'sign' => $jsonResult['sign'],
                'timestamp' => $jsonResult['timestamp'],
                'fs_id' => $jsonResult['list'][0]['fs_id']
            ]);

            // API URL for second request
            $apiUrl2 = "https://terabox.hnn.workers.dev/api/get-download";
            $response2 = makeCurlRequest($apiUrl2, 'POST', $postData);
            $jsonResult2 = json_decode($response2, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['ok' => false, 'error' => 'Error decoding JSON response: ' . json_last_error_msg()]);
                exit;
            }

            // Return download link or error
            if (isset($jsonResult2['downloadLink'])) {
                header("Location: " . $jsonResult2['downloadLink']);
                exit;
            } else {
                echo json_encode(['ok' => false, 'error' => 'Download link not found or invalid.']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Required fields missing in response from the first API.']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid URL format. Could not extract ID.']);
    }
} else {
    echo json_encode(['ok' => false, 'error' => 'No URL provided in the request.']);
}
?>
