<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
//require_once('/srv/htdocs/wp-load.php');
require_once('/home/t2yw8bwz0d8e/public_html/wp-load.php');
// Function to make GET requests to the external API
function fetch_external_api($url) {
    $args = [
        'timeout' => 60, // Extend the timeout to 60 seconds
    ];
    $response = wp_remote_get($url, $args);

    // Check for errors
    if (is_wp_error($response)) {
        // Get detailed error information
        $error_message = $response->get_error_message();
        return json_encode(['error' => 'Failed to fetch data', 'details' => $error_message]);
    } else {
        // Retrieve and return the response body
        return wp_remote_retrieve_body($response);
    }
}

// Route to get states
if ($_GET['route'] == 'getStates') {
    echo fetch_external_api('https://public-rma.fpac.usda.gov/apps/PrfWebApi/PrfExternalStates/GetStates');
}

// Route to get counties by stateCode
if ($_GET['route'] == 'getCountiesByState') {
    $stateCode = sanitize_text_field($_GET['stateCode']);
    if (!$stateCode) {
        echo json_encode(['error' => 'State code is required']);
    } else {
        echo fetch_external_api("https://public-rma.fpac.usda.gov/apps/PrfWebApi/PrfExternalStates/GetCountiesByState?stateCode={$stateCode}");
    }
}

// Route to get sub-counties (grids) by stateCode and countyCode
if ($_GET['route'] == 'getSubCountiesByCountyAndState') {
    $stateCode = sanitize_text_field($_GET['stateCode']);
    $countyCode = sanitize_text_field($_GET['countyCode']);

    if (!$stateCode || !$countyCode) {
        echo json_encode(['error' => 'State code and county code are required']);
    } else {
        echo fetch_external_api("https://public-rma.fpac.usda.gov/apps/PrfWebApi/PrfExternalStates/GetSubCountiesByCountyAndState?stateCode={$stateCode}&countyCode={$countyCode}");
    }
}

// Route to get index values by gridId
if ($_GET['route'] == 'getIndexValues') {
    $gridId = sanitize_text_field($_GET['gridId']);
    
    if (!$gridId) {
        echo json_encode(['error' => 'Grid ID is required']);
    } else {
        echo fetch_external_api("https://public-rma.fpac.usda.gov/apps/PrfWebApi/PrfExternalIndexes/GetIndexValues?intervalType=BiMonthly&sampleYearMinimum=1948&sampleYearMaximum=2024&gridId={$gridId}");
    }
}

// Route to get pricing rates
if ($_GET['route'] == 'getPricingRates') {
    $gridId = sanitize_text_field($_GET['gridId']);
    $stateCode = sanitize_text_field($_GET['stateCode']);
    $countyCode = sanitize_text_field($_GET['countyCode']);
    $coverage = sanitize_text_field($_GET['coverageLevelPercent']);
    $prodFactor = sanitize_text_field($_GET['productivityFactor']);
    $acres = sanitize_text_field($_GET['insuredAcres']);
    $intervals = sanitize_text_field($_GET['intervalPercentOfValues']);
    $intendedUseCode = sanitize_text_field($_GET['intendedUseCode']);
    $irrigationPracticeCode = sanitize_text_field($_GET['irrigationPracticeCode']);

    if (!$coverage || !$prodFactor || !$acres || !$intervals) {
        echo json_encode(['error' => 'Missing required parameters']);
    } else {
        $url = "https://public-rma.fpac.usda.gov/apps/PrfWebApi/PrfExternalPricingRates/GetPricingRates?intervalType=BiMonthly&irrigationPracticeCode={$irrigationPracticeCode}&organicPracticeCode=997&intendedUseCode={$intendedUseCode}&stateCode={$stateCode}&countyCode={$countyCode}&productivityFactor={$prodFactor}&insurableInterest=100&insuredAcres={$acres}&sampleYear=2024&intervalPercentOfValues={$intervals}&coverageLevelPercent={$coverage}&gridId={$gridId}&gridName={$gridId}";
        
        echo fetch_external_api($url);
    }
}
?>