<?php
function osgb36ToWgs84($easting, $northing) {
    // Simplified conversion using OSTN02 approximation
    // For accurate results, use a geospatial library like proj4php or GDAL

    // Constants for Airy 1830 ellipsoid
    $a = 6377563.396;
    $b = 6356256.909;
    $F0 = 0.9996012717;
    $lat0 = deg2rad(49);
    $lon0 = deg2rad(-2);
    $N0 = -100000;
    $E0 = 400000;
    $e2 = 1 - ($b * $b) / ($a * $a);
    $n = ($a - $b) / ($a + $b);

    $lat = $lat0;
    $M = 0;
    $maxIter = 100;
    $i = 0;

    do {
        $lat = ($northing - $N0 - $M) / ($a * $F0) + $lat;
        $Ma = (1 + $n + (5/4)*$n**2 + (5/4)*$n**3) * ($lat - $lat0);
        $Mb = (3*$n + 3*$n**2 + (21/8)*$n**3) * sin($lat - $lat0) * cos($lat + $lat0);
        $Mc = ((15/8)*$n**2 + (15/8)*$n**3) * sin(2*($lat - $lat0)) * cos(2*($lat + $lat0));
        $Md = (35/24)*$n**3 * sin(3*($lat - $lat0)) * cos(3*($lat + $lat0));
        $M = $b * $F0 * ($Ma - $Mb + $Mc - $Md);
        $i++;
    } while (abs($northing - $N0 - $M) >= 0.00001 && $i < $maxIter);

    $sinLat = sin($lat);
    $cosLat = cos($lat);
    $tanLat = tan($lat);
    $nu = $a * $F0 / sqrt(1 - $e2 * $sinLat * $sinLat);
    $rho = $a * $F0 * (1 - $e2) / pow(1 - $e2 * $sinLat * $sinLat, 1.5);
    $eta2 = $nu / $rho - 1;

    $VII = $tanLat / (2 * $rho * $nu);
    $VIII = $tanLat / (24 * $rho * pow($nu, 3)) * (5 + 3 * $tanLat**2 + $eta2 - 9 * $tanLat**2 * $eta2);
    $IX = $tanLat / (720 * $rho * pow($nu, 5)) * (61 + 90 * $tanLat**2 + 45 * $tanLat**4);
    $X = 1 / ($cosLat * $nu);
    $XI = 1 / (6 * $cosLat * pow($nu, 3)) * ($nu / $rho + 2 * $tanLat**2);
    $XII = 1 / (120 * $cosLat * pow($nu, 5)) * (5 + 28 * $tanLat**2 + 24 * $tanLat**4);
    $XIIA = 1 / (5040 * $cosLat * pow($nu, 7)) * (61 + 662 * $tanLat**2 + 1320 * $tanLat**4 + 720 * $tanLat**6);

    $dE = $easting - $E0;
    $lat = $lat - $VII * $dE**2 + $VIII * $dE**4 - $IX * $dE**6;
    $lon = $lon0 + $X * $dE - $XI * $dE**3 + $XII * $dE**5 - $XIIA * $dE**7;

    return [rad2deg($lat), rad2deg($lon)];
}


function setW3WWords(float $lat, float $lng, string $apiKey, ?string $language = null, int $timeout = 10): bool
{
    // Build base URL
    $base = 'https://api.what3words.com/v3/convert-to-3wa';

    // Build query parameters
    $params = [
        'coordinates' => sprintf('%.8f,%.8f', $lat, $lng),
        'key'         => $apiKey,
    ];
    if (!empty($language)) {
        $params['language'] = $language;
    }

    $url = $base . '?' . http_build_query($params);

    // Perform HTTP GET with cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FAILONERROR    => false,   // we’ll check HTTP code ourselves
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // Basic transport check
    if ($response === false) {
        error_log("What3words request failed: $curlErr");
        return false;
    }
    if ($httpCode !== 200) {
        error_log("What3words API returned HTTP $httpCode: $response");
        return false;
    }

    // Parse JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('What3words JSON decode error: ' . json_last_error_msg());
        return false;
    }

    // Extract "words"
    if (!isset($data['words'])) {
        // Optionally log API error details (e.g., $data['error']['message'])
        $msg = isset($data['error']['message']) ? $data['error']['message'] : 'No words field in response';
        error_log("What3words logical error: $msg");
        return false;
    }

    // Set $w3w in the caller's scope
    $GLOBALS['w3w'] = $data['words'];
    return true;
}



// Get easting and northing from URL
if (isset($_GET['easting']) && isset($_GET['northing'])) {
    $easting = floatval($_GET['easting']);
    $northing = floatval($_GET['northing']);

    list($lat, $lon) = osgb36ToWgs84($easting, $northing);

    $bingUrl = "https://www.bing.com/maps?rtp=pos.mypos~pos.{$lat}_{$lon}";
    $googleUrl = "https://www.google.com/maps/dir/?api=1&origin=My+Location&destination={$lat},{$lon}";
    $wazeUrl = "https://waze.com/ul?ll={$lat},{$lon}&navigate=yes";
 //   $SygicUrl = "com.sygic.aura://coordinate|{$lon}|{$lat}|drive";
 // following supor from sygic they have suggetsed URL encoding and setting the resolution   
    $SygicUrl = "com.sygic.aura://coordinate%7C{$lon}%7C{$lat}%7Cdrive&&&-r1920x1200";

    $apiKey = 'SDUO84SS';
    $ok     = setW3WWords($lat, $lng, $apiKey, 'en');

    echo "NavLink v1.0<br>";
    echo "Latitude: $lat<br>";
    echo "Longitude: $lon<br>";
//    echo "<a href='$bingUrl' target='_blank'>View on Bing Maps</a><br>";
//    echo "<a href='$googleUrl' target='_blank'>View on Google Maps</a><br>";
    echo "<a href='$SygicUrl'>Open Sygic and Navigate</a>";


    if ($ok) {
    // $w3w is now populated globally
    echo "What Three Words : '$w3w'";
    } else {
       http_response_code(500);
       echo "Failed to obtain What3words address.";
    }
    
} else {
    echo "Please provide 'easting' and 'northing' parameters in the URL.";
}
?>
