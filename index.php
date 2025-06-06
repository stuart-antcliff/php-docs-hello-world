<?php
// Get x and y from the URL query string
if (isset($_GET['x']) && isset($_GET['y'])) {
    $x = floatval($_GET['x']);
    $y = floatval($_GET['y']);

    // Convert Mercator X/Y to Longitude/Latitude
    $longitude = rad2deg($x / 6378137);
    $latitude = rad2deg(atan(sinh($y / 6378137)));

    // Build Bing Maps URL
    $bingUrl = "https://www.bing.com/maps?q={$latitude},{$longitude}";

    echo "Latitude: $latitude<br>";
    echo "Longitude: $longitude<br>";
    echo "<a href='$bingUrl' target='_blank'>View on Bing Maps</a>";
} else {
    echo "Please provide 'x' and 'y' parameters in the URL.";
}
?>
