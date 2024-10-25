<?php
/*
Plugin Name: PRF Quoting Tool
Description: A quoting tool that quotes Pasture, Rainfall, and Forestry insurance for farmers.
Version: 4.5
Author: Jordan Heaps
*/


function prf_quoting_tool_enqueue_scripts()
{
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);
    wp_enqueue_script('jspdf-js', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), null, true);
    wp_enqueue_script('autotable-js', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'prf_quoting_tool_enqueue_scripts');
add_action('wp_ajax_fetch_state_and_county', 'fetch_state_and_county_callback');
add_action('wp_ajax_nopriv_fetch_state_and_county', 'fetch_state_and_county_callback');

function bigquery_form()
{
    ob_start();
    ?>

    <head>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let stateSelect = document.getElementById('state');
                let countySelect = document.getElementById('county');
                let gridSelect = document.getElementById('grid_id');
                let useSelect = document.getElementById('theUse');
                let acreSelect = document.getElementById('acres');
                let coverageSelect = document.getElementById('coverage');
                let prodFactorSelect = document.getElementById('prod_factor');
                let submitSelect = document.getElementById('bq_submit');
                let acre = document.getElementById('acres').value;
                let pricingDataGlobal;
                let data2 = [];
                let baseCountyRates = [];
                let allIntervalsPerf = [];
                let selectedIntervalsPerf = [];
                let indemnityArr = [];
                let netArr = [];
                let theRate;
                let subsidy;
                let prodPerAcre;
                let prodTotal;
                let insuredAcre;
                let insuredTotal;
                let countyTotal;
                let selectProdTot;
                let produceTotal;
                let selectPremTot;
                let premiumTotal;
                let selectSubsidyTot;
                let subsidyTotal;
                let premPerAcreArr;
                let premPerAcreTot;
                let prodPremArr;
                let prodPremTot;
                let allocationIntervals;
                let selectPremPerAcre;
                let selectProdPrem;
                let selectSubsidy;
                let allocationValues
                let endrow;
                let errorMessage;

                const intervalMap = {
                    "625": "JanFeb",
                    "626": "FebMar",
                    "627": "MarApr",
                    "628": "AprMay",
                    "629": "MayJun",
                    "630": "JunJul",
                    "631": "JulAug",
                    "632": "AugSep",
                    "633": "SepOct",
                    "634": "OctNov",
                    "635": "NovDec"
                };

                const formatter = new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                });


                // Allocation table --------------------------------------------------------------------------------//
                let intervalInputs = document.querySelectorAll('.interval-input');
                let allocationTotalElement = document.getElementById('allocationTotal');
                let allocationForm = document.getElementById('allocationForm');
                let previousValues = Array.from(intervalInputs).map(() => 0);

                intervalInputs.forEach((input, index) => {
                    var storedValue = localStorage.getItem('interval' + index);
                    if (storedValue !== null) {
                        input.value = storedValue;
                        previousValues[index] = parseInt(storedValue, 10);
                    }
                });

                updateTotalAllocation();

                intervalInputs.forEach((input, index) => {
                    input.addEventListener('change', () => {
                        if (updateIntervals(input)) {
                            if (updateTotalAllocation(index)) {
                                previousValues[index] = parseInt(input.value, 10) || 0;
                            }
                        }
                    });
                });
                allocationForm.addEventListener('submit', (event) => {
                    intervalInputs.forEach((input, index) => {
                        localStorage.setItem('interval' + index, input.value);
                    });
                });
                function validateIntervals() {
                    let hasConsecutiveNonZeros = false;
                    let previousValueWasNonZero = false;

                    intervalInputs.forEach(input => {
                        var value = parseInt(input.value, 10);

                        if (!isNaN(value) && value > 0) {
                            if (previousValueWasNonZero) {
                                hasConsecutiveNonZeros = true; // Found consecutive non-zero values
                            }
                            previousValueWasNonZero = true;
                        } else {
                            previousValueWasNonZero = false; // Reset the check if we find a zero
                        }
                    });

                    if (hasConsecutiveNonZeros) {
                        alert("Intervals cannot overlap! There must be at least one '0' between non-zero intervals.");
                        return false;
                    }

                    return true;
                }
                function updateIntervals(input) {
                    var interval = parseInt(input.value, 10);
                    if (isNaN(interval) || interval < 10 || interval > 50) {
                        alert("Value must be between 10% and 50%");
                        input.value = '0';
                        return false;
                    }
                    return true;
                }

                function updateTotalAllocation(changedIndex) {
                    if (!validateIntervals()) {
                        return false; // Prevent further action if validation fails
                    }

                    var total = 0;
                    intervalInputs.forEach(input => {
                        var value = parseInt(input.value, 10);
                        if (!isNaN(value)) {
                            total += value;
                        }
                    });

                    allocationTotalElement.textContent = total + '%';
                    return true;
                }
                function getAllocationIntervals() {
                    return Array.from(intervalInputs).map(input => {
                        var value = parseInt(input.value, 10) / 100;
                        return isNaN(value) ? 0 : value;
                    });
                }
                // Fetch and populate the states
                fetch('https://upservu.com/wp-content/plugins/bigquery-data/proxy.php?route=getStates')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        var statesArray = data.states || [];

                        statesArray.forEach(state => {
                            var option = document.createElement('option');
                            option.value = state.Code;
                            option.textContent = state.Name;
                            stateSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching states:', error);
                    });

                // Event listener for when a state is selected
                stateSelect.addEventListener('change', function () {
                    var selectedStateCode = stateSelect.value;

                    countySelect.innerHTML = '<option value="">Select a County</option>';

                    if (selectedStateCode) {
                        // Make the API call to fetch counties via the proxy server
                        fetch(`https://upservu.com/wp-content/plugins/bigquery-data/proxy.php?route=getCountiesByState&stateCode=${selectedStateCode}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.statusText);
                                }
                                return response.json();
                            })
                            .then(data => {
                                var countiesArray = data.counties || [];  // Adjust 'counties' to match the actual property

                                if (Array.isArray(countiesArray)) {
                                    countiesArray.forEach(county => {
                                        var option = document.createElement('option');
                                        option.value = county.Code;
                                        option.textContent = county.Name;
                                        countySelect.appendChild(option);
                                    });
                                } else {
                                    console.error('Expected an array, but received:', countiesArray);
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching counties:', error);
                            });
                    }
                });

                // Event listener for when a county is selected
                countySelect.addEventListener('change', function () {
                    var selectedStateCode = stateSelect.value;
                    var selectedCountyCode = countySelect.value;

                    gridSelect.innerHTML = '<option value="">Select a Grid</option>';

                    if (selectedCountyCode) {
                        // Make the API call to fetch grids via the proxy server
                        fetch(`https://upservu.com/wp-content/plugins/bigquery-data/proxy.php?route=getSubCountiesByCountyAndState&stateCode=${selectedStateCode}&countyCode=${selectedCountyCode}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.statusText);
                                }
                                return response.json();
                            })
                            .then(data => {

                                var gridsArray = data.subCounties || [];

                                if (Array.isArray(gridsArray)) {
                                    gridsArray.forEach(grid => {
                                        var option = document.createElement('option');
                                        option.value = grid.NoaaGridId;
                                        option.textContent = grid.NoaaGridName;
                                        gridSelect.appendChild(option);
                                    });
                                } else {
                                    console.error('Expected an array, but received:', countiesArray);
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching Grids:', error);
                            });
                    }
                });
                // Event listener for when user clicks submit
                updateQuote.addEventListener('click', function (event) {
                    event.preventDefault();

                    baseCountyRates = [];
                    allIntervalsPerf = [];
                    selectedIntervalsPerf = [];
                    netArr = [];
                    indemnityArr = [];

                    document.getElementById('loadingIndicator').style.display = 'block';

                    var selectedStateCode = stateSelect.value;
                    var selectedCountyCode = countySelect.value;
                    var selectedGridCode = gridSelect.value;
                    var selectedCoverage = coverageSelect.value;
                    var selectedProdFactor = Number(prodFactorSelect.value) * 100;
                    var selectedAcres = acreSelect.value;
                    var intervalInputs = document.querySelectorAll('.interval-input');
                    var coverage = selectedCoverage;

                    ['display_county_acre', 'display_county_total', 'display_base_acre', 'display_base_total',
                        'display_insured_acre', 'display_insured_total', 'prod_acre', 'prod_total',
                        'totalPrem_acre', 'totalPrem_total', 'sub_acre', 'sub_total'].forEach(function (id) {
                            document.getElementById(id).innerHTML = ''; // Resetting displayed values
                        });

                    function selectedAllocationIntervals() {
                        return Array.from(intervalInputs).map(input => {
                            var value = parseInt(input.value, 10);
                            return isNaN(value) ? 0 : value;
                        });
                    }
                    var intervalsSelect = selectedAllocationIntervals();
                    var selectedIntervals = intervalsSelect.join(',').trim();
                    var selectedUse = useSelect.value;

                    var irrigationPracticeCode = selectedUse === "030" ? "003" : "997";

                    if (selectedCountyCode) {
                        // Make the API call with headers
                        fetch(`https://upservu.com/wp-content/plugins/bigquery-data/proxy.php?route=getPricingRates&gridId=${selectedGridCode}&stateCode=${selectedStateCode}&countyCode=${selectedCountyCode}&coverageLevelPercent=${selectedCoverage}&productivityFactor=${selectedProdFactor}&insuredAcres=${selectedAcres}&intervalPercentOfValues=${selectedIntervals}&intendedUseCode=${selectedUse}&irrigationPracticeCode=${irrigationPracticeCode}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.statusText);
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (typeof data === 'string') {
                                    console.log('Received HTML content:', data);
                                } else {
                                    console.log('Response data:', data);  // Log the entire response to inspect its structure
                                    function showErrorModal(message) {
                                        document.getElementById('errorMessage').textContent = message;
                                        new bootstrap.Modal(document.getElementById('errorModal')).show();
                                    }

                                    // Check if the response contains an error message
                                    if (data[0] && data[0].Message) {
                                        showErrorModal('Error: ' + data[0].Message);
                                    }
                                    console.log('Parsed JSON data:', data);
                                    pricingDataGlobal = data.returnData || [];

                                    if (Array.isArray(pricingDataGlobal)) {
                                        pricingDataGlobal.forEach(function (row) {
                                            let intervalData = {};

                                            if (Array.isArray(row.PricingRateRows)) {
                                                row.PricingRateRows.forEach(function (col) {
                                                    intervalData[col.IntervalCode] = col.PremiumRate;
                                                });
                                            }

                                            let baseRatesForRow = {};
                                            for (const code of Object.keys(intervalMap)) {
                                                if (intervalData[code] !== undefined) {
                                                    const monthName = intervalMap[code];
                                                    baseRatesForRow[monthName] = intervalData[code];
                                                }
                                            }
                                            baseCountyRates.push(baseRatesForRow);
                                        });
                                    } else if (typeof pricingDataGlobal === 'object' && pricingDataGlobal !== null) {
                                        let intervalData = {};

                                        if (Array.isArray(pricingDataGlobal.PricingRateRows)) {
                                            pricingDataGlobal.PricingRateRows.forEach(function (col) {
                                                intervalData[col.IntervalCode] = col.PremiumRate;
                                            });
                                        }
                                        let baseRatesForRow = {};
                                        for (const code of Object.keys(intervalMap)) {
                                            if (intervalData[code] !== undefined) {
                                                const monthName = intervalMap[code];
                                                baseRatesForRow[monthName] = intervalData[code];
                                            }
                                        }
                                        baseCountyRates.push(baseRatesForRow);
                                    } else {
                                        console.error('Unexpected data format for pricingDataGlobal:', pricingDataGlobal);
                                    }

                                    var coverage = document.getElementById("coverage").value;
                                    var coveragePercentage = coverage / 100;


                                    var interval1 = Number(baseCountyRates[0].JanFeb);
                                    var interval2 = Number(baseCountyRates[0].FebMar);
                                    var interval3 = Number(baseCountyRates[0].MarApr);
                                    var interval4 = Number(baseCountyRates[0].AprMay);
                                    var interval5 = Number(baseCountyRates[0].MayJun);
                                    var interval6 = Number(baseCountyRates[0].JunJul);
                                    var interval7 = Number(baseCountyRates[0].JulAug);
                                    var interval8 = Number(baseCountyRates[0].AugSep);
                                    var interval9 = Number(baseCountyRates[0].SepOct);
                                    var interval10 = Number(baseCountyRates[0].OctNov);
                                    var interval11 = Number(baseCountyRates[0].NovDec);

                                    var countyRates = [interval1, interval2, interval3, interval4, interval5, interval6, interval7, interval8, interval9, interval10, interval11];
                                    // Log the baseCountyRates array to check the result
                                    console.log('Base County Rates:', baseCountyRates);
                                    subsidy = pricingDataGlobal.PricingRateSummary.SubsidyLevel;

                                    theRate = parseFloat(pricingDataGlobal.PricingRateSummary.CountyBaseValue);
                                    document.getElementById('display_county_acre').innerHTML = formatter.format(theRate);

                                    countyTotal = theRate * selectedAcres;
                                    document.getElementById('display_county_total').innerHTML = formatter.format(countyTotal);

                                    prodPerAcre = theRate * prodFactorSelect.value;
                                    document.getElementById('display_base_acre').innerHTML = formatter.format(prodPerAcre);

                                    prodTotal = prodPerAcre * selectedAcres;
                                    document.getElementById('display_base_total').innerHTML = formatter.format(prodTotal);

                                    insuredAcre = pricingDataGlobal.PricingRateSummary.ProtectionPerAcre;
                                    document.getElementById('display_insured_acre').innerHTML = formatter.format(insuredAcre);

                                    insuredTotal = pricingDataGlobal.PricingRateSummary.TotalPolicyProtection;
                                    document.getElementById('display_insured_total').innerHTML = formatter.format(insuredTotal);

                                    selectProdTot = pricingDataGlobal.PricingRateRowPerAcreSummary.ProducerPremium;
                                    document.getElementById('prod_acre').innerHTML = formatter.format(selectProdTot);

                                    produceTotal = pricingDataGlobal.PricingRateRowTotalSummary.ProducerPremium;
                                    document.getElementById('prod_total').innerHTML = formatter.format(produceTotal);

                                    selectPremTot = pricingDataGlobal.PricingRateRowPerAcreSummary.TotalPremium;
                                    document.getElementById('totalPrem_acre').innerHTML = formatter.format(selectPremTot);

                                    premiumTotal = pricingDataGlobal.PricingRateRowTotalSummary.TotalPremium;
                                    document.getElementById('totalPrem_total').innerHTML = formatter.format(premiumTotal);

                                    selectSubsidyTot = pricingDataGlobal.PricingRateRowPerAcreSummary.PremiumSubsidy;
                                    document.getElementById('sub_acre').innerHTML = "-" + formatter.format(selectSubsidyTot);

                                    subsidyTotal = pricingDataGlobal.PricingRateRowTotalSummary.PremiumSubsidy;
                                    document.getElementById('sub_total').innerHTML = "-" + formatter.format(subsidyTotal);

                                    // All intervals -------------------------------------------//
                                    premPerAcreArr = countyRates.map(function (interval) {
                                        return Number((interval * insuredAcre).toFixed(2));
                                    });
                                    premPerAcreTot = premPerAcreArr.reduce(function (accumulator, currentValue) {
                                        return accumulator + currentValue;
                                    }, 0).toFixed(2);
                                    prodPremArr = countyRates.map(function (interval) {
                                        return Number((interval * insuredAcre * subsidy).toFixed(2));
                                    });
                                    prodPremTot = prodPremArr.reduce(function (accumulator, currentValue) {
                                        return accumulator + currentValue;
                                    }, 0).toFixed(2);
                                }
                                return fetch(`https://upservu.com/wp-content/plugins/bigquery-data/proxy.php?route=getIndexValues&intervalType=BiMonthly&sampleYearMinimum=1948&sampleYearMaximum=2024&gridId=${selectedGridCode}`);
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.statusText);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Response data:', data);
                                function showErrorModal(message) {
                                    document.getElementById('errorMessage').textContent = message;
                                    new bootstrap.Modal(document.getElementById('errorModal')).show();
                                }

                                // Check if the response contains an error message (since data is an object)
                                if (data[0] && data[0].Message) {
                                    showErrorModal('Error: ' + data[0].Message);
                                }
                                console.log('Rainfall API response:', data);
                                secondData = data.HistoricalIndexRows || [];
                                if (secondData.length > 0) {

                                    data2 = data.HistoricalIndexRows.map(row => {
                                        const yearData = { Year: row.Year };

                                        // Loop through the HistoricalIndexDataColumns for each year
                                        row.HistoricalIndexDataColumns.forEach(col => {
                                            const intervalName = intervalMap[col.IntervalCode];
                                            if (intervalName) {
                                                if (col.PercentOfNormal > 0.0) {
                                                    yearData[intervalName] = parseFloat((col.PercentOfNormal * 100).toFixed(1));
                                                } else {
                                                    yearData[intervalName] = null;
                                                }

                                            }
                                        });
                                        return yearData;
                                    });
                                    data2.sort((a, b) => b.Year - a.Year);

                                    // Totals table ------------------------------------------------//
                                    let totalPremArr = [];
                                    var yearsPaid = document.getElementById('yearsPaid').value;
                                    switch (yearsPaid) {
                                        case "all":
                                            endRow = allIntervalsPerf.length;
                                            break;
                                        case "20":
                                            endRow = 21;
                                            break;
                                        case "15":
                                            endRow = 16;
                                            break;
                                        case "10":
                                            endRow = 11;
                                            break;
                                        default:
                                            endRow = allIntervalsPerf.length;
                                            break;
                                    }
                                    var TotalPrem = prodPremArr.map(function (interval) {
                                        if (yearsPaid === "all") {
                                            yearsPaid = data2.length;
                                        }
                                        var totalPremReal = interval * yearsPaid;
                                        totalPremArr.push(totalPremReal);
                                        return Math.round(interval * yearsPaid);
                                    });
                                    for (var i = 0; i <= 10; i++) {
                                        document.getElementById('TotalPrem' + i).innerHTML = formatter.format(TotalPrem[i]);
                                    }
                                    // All Intervals Performance table-------------//
                                    data2.forEach((row, index) => {
                                        var newRow = {};

                                        if (row.Year !== undefined) {
                                            newRow.Year = row.Year;
                                        }
                                        // Current year processed differently than the rest
                                        if (index === 0) {
                                            Object.keys(row).forEach(key => {
                                                if (key === 'Year' || row[key] === null || row[key] >= coverage) {
                                                    newRow[key] = 0;
                                                } else {
                                                    newRow[key] = ((coverage - row[key]) / coverage) * insuredAcre;
                                                }
                                            });
                                        } else {
                                            // Process all other rows with the normal logic
                                            Object.keys(row).forEach(key => {
                                                if (key === 'Year' || row[key] >= coverage) {
                                                    newRow[key] = 0;
                                                } else {
                                                    newRow[key] = ((coverage - row[key]) / coverage) * insuredAcre;
                                                }
                                            });
                                        }
                                        allIntervalsPerf.push(newRow);
                                    });

                                    allocationIntervals = getAllocationIntervals();

                                    // Selected Intervals --------------------------------------//
                                    selectPremPerAcre = allocationIntervals.map((value, index) => {
                                        var selectPremPerAcreVal = value * premPerAcreArr[index];
                                        return parseFloat(selectPremPerAcreVal.toFixed(2));
                                    });
                                    selectPremTot = selectPremPerAcre.reduce(function (accumulator, currentValue) {
                                        return parseFloat((accumulator + currentValue).toFixed(2));
                                    }, 0);

                                    selectProdPrem = allocationIntervals.map((value, index) => {
                                        var selectProdPremVal = value * prodPremArr[index];
                                        return parseFloat(selectProdPremVal.toFixed(2));
                                    });
                                    selectProdTot = selectProdPrem.reduce(function (accumulator, currentValue) {
                                        return parseFloat((accumulator + currentValue).toFixed(2));
                                    }, 0);

                                    selectSubsidy = selectProdPrem.map((value, index) => {
                                        var selectSubsidyVal = value - selectPremPerAcre[index];
                                        return parseFloat(selectSubsidyVal.toFixed(2));
                                    });
                                    // Selected Intervals Performance table-------------//
                                    allocationValues = allocationIntervals || [];

                                    var acre = document.getElementById('acres').value;


                                    allIntervalsPerf.forEach(rowWithYear => {
                                        var newRow = { Year: rowWithYear.Year };
                                        var keys = Object.keys(rowWithYear).filter(key => key !== 'Year');
                                        var indemnity = 0;
                                        keys.forEach((key, index) => {
                                            var allocationValue = allocationValues[index] || 0;
                                            var rowValue = rowWithYear[key] || 0;
                                            newRow[key] = acre * allocationValue * rowValue;
                                            newRow[key] = Math.max(0, newRow[key]);
                                            indemnity += newRow[key];
                                        });
                                        newRow['Indemnity'] = indemnity;
                                        selectedIntervalsPerf.push(newRow);
                                        var net = Math.round(indemnity - produceTotal);
                                        indemnityArr.push(indemnity);
                                        netArr.push(net);
                                    });

                                    // Payout Rates for 10, 15, and 20 years ------------//
                                    function payoutRate(array, years) {
                                        var valuesRange = array.slice(1, years);
                                        var countGreaterThanZero = valuesRange.filter(value => value > 0).length;
                                        var result = Math.round((countGreaterThanZero / (years - 1)) * 100);
                                        return result;
                                    };
                                    var payoutRateTen = payoutRate(netArr, 11);
                                    var payoutRateFifteen = payoutRate(netArr, 16);
                                    var payoutRateTwenty = payoutRate(netArr, 21);

                                    document.getElementById('rate10').innerHTML = payoutRateTen + '%';
                                    document.getElementById('rate15').innerHTML = payoutRateFifteen + '%';
                                    document.getElementById('rate20').innerHTML = payoutRateTwenty + '%';

                                    // Avg payment for 10, 15, and 20 years ------------//
                                    function avgPayment(array, years) {
                                        var valuesRange = array.slice(1, years);
                                        var sumAvgPmt = valuesRange.reduce(function (accumulator, currentValue) {
                                            return accumulator + currentValue;
                                        }, 0);
                                        var averagePayment = (sumAvgPmt / (years - 1));
                                        return Math.round(averagePayment);
                                    }
                                    var avgPaymentTen = avgPayment(netArr, 11);
                                    var avgPaymentFifteen = avgPayment(netArr, 16);
                                    var avgPaymentTwenty = avgPayment(netArr, 21);

                                    document.getElementById('pmt10').innerHTML = formatter.format(avgPaymentTen);
                                    document.getElementById('pmt15').innerHTML = formatter.format(avgPaymentFifteen);
                                    document.getElementById('pmt20').innerHTML = formatter.format(avgPaymentTwenty);

                                    // ROI for 10, 15, and 20 years ------------//
                                    var roiTen = Math.round((avgPaymentTen / produceTotal) * 100);
                                    var roiFifteen = Math.round((avgPaymentFifteen / produceTotal) * 100);
                                    var roiTwenty = Math.round((avgPaymentTwenty / produceTotal) * 100);

                                    document.getElementById('roi10').innerHTML = roiTen + '%';
                                    document.getElementById('roi15').innerHTML = roiFifteen + '%';
                                    document.getElementById('roi20').innerHTML = roiTwenty + '%';

                                    // Total Indemnity row------------------------------//
                                    function sumColumnInRange(columnName, startRow, endRow) {
                                        var columnSum = 0;

                                        for (var i = startRow; i < endRow; i++) {
                                            var row = allIntervalsPerf[i];
                                            <!--noformat on-->
                                            if (row && row.hasOwnProperty(columnName)) {
                                                                <!--noformat off-->
                                                                var value = row[columnName];
                                                if (!isNaN(value)) {
                                                    columnSum += value;
                                                } else {
                                                    console.log(`Value in ${columnName} is not a valid float:`, row[columnName]);
                                                }
                                            } else {
                                                console.log(`Skipping row ${i + 1} - ${columnName} is undefined or row is missing`);
                                            }
                                        }
                                        return columnSum;
                                    }

                                    var months = ['JanFeb', 'FebMar', 'MarApr', 'AprMay', 'MayJun', 'JunJul', 'JulAug', 'AugSep', 'SepOct', 'OctNov', 'NovDec'];
                                    let totalIndemsArr = [];

                                    months.forEach((month, index) => {
                                        var totalIndem = sumColumnInRange(`${month}`, 1, endRow);
                                        totalIndemsArr.push(totalIndem);
                                        document.getElementById(`TotalIndem${index}`).innerHTML = formatter.format(Math.round(totalIndem));
                                    });

                                    // Net Indemnity Row --------------------------//
                                    var netIndem = ['NetIndem0', 'NetIndem1', 'NetIndem2', 'NetIndem3', 'NetIndem4', 'NetIndem5', 'NetIndem6', 'NetIndem7', 'NetIndem8', 'NetIndem9', 'NetIndem10'];
                                    netIndem.forEach((indem, index) => {
                                        var newTotIndem = document.getElementById(`TotalIndem${index}`).innerHTML.replace(/[^0-9.-]+/g, '');
                                        var newTotPrem = document.getElementById(`TotalPrem${index}`).innerHTML.replace(/[^0-9.-]+/g, '');

                                        // Check for NaN
                                        if (isNaN(newTotIndem) || isNaN(newTotPrem)) {
                                            console.error(`Invalid number detected at index ${index}:`, { newTotIndem, newTotPrem });
                                        } else {
                                            var theNetIndem = newTotIndem - newTotPrem;
                                            document.getElementById(indem).innerHTML = formatter.format(Math.round(theNetIndem));
                                        }
                                    });

                                    // Indem % of Prem Row --------------------------//
                                    var indemPrem = ['IndemPrem0', 'IndemPrem1', 'IndemPrem2', 'IndemPrem3', 'IndemPrem4', 'IndemPrem5', 'IndemPrem6', 'IndemPrem7', 'IndemPrem8', 'IndemPrem9', 'IndemPrem10'];
                                    indemPrem.forEach((prem, index) => {
                                        var newTotIndem = totalIndemsArr[index];
                                        var newTotPrem = totalPremArr[index];
                                        var theIndemPrem = Math.round((newTotIndem / newTotPrem) * 100);
                                        document.getElementById(prem).innerHTML = theIndemPrem + '%';
                                    });
                                    var yearsPaidMonths = ['yearsPaid0', 'yearsPaid1', 'yearsPaid2', 'yearsPaid3', 'yearsPaid4', 'yearsPaid5', 'yearsPaid6', 'yearsPaid7', 'yearsPaid8', 'yearsPaid9', 'yearsPaid10'];
                                    function countValuesGreaterThan90(key, rowCount) {
                                        let count = 0;
                                        const dataLength = data2.length;
                                        rowCount = Math.min(rowCount, dataLength);

                                        for (let i = 1; i < rowCount; i++) {
                                            var value = data2[i][key];
                                                            <!--noformat on-->
                                                            if (typeof value === 'number' && value < 90) {
                                                                <!--noformat off-->
                                                    count++;
                                            }
                                        }
                                        return count;
                                    }
                                    var results = {};
                                    var keys = ['JanFeb', 'FebMar', 'MarApr', 'AprMay', 'MayJun', 'JunJul', 'JulAug', 'AugSep', 'SepOct', 'OctNov', 'NovDec'];
                                    keys.forEach((key, index) => {
                                        results[key] = countValuesGreaterThan90(key, endRow);
                                        var yearsID = yearsPaidMonths[index];
                                        var monthRange = document.getElementById(yearsID);
                                        if (monthRange) {
                                            monthRange.textContent = results[key];
                                        }
                                    });
                                    displayData2(data2);

                                    generateTableWithRunningTotals(netArr, indemnityArr, produceTotal);
                                } else {
                                    console.error('No valid data found in HistoricalIndexRows');
                                    rainfallResults.append('<p>No data found</p>');
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching data:', error);  // Log the full error
                            })
                            .finally(() => {
                                document.getElementById('loadingIndicator').style.display = 'none';
                            });
                    }

                });

                // Running Total Table -----------------------//
                function calculateRunningTotal(netArr, length) {
                    let resultArr = [];
                    for (let i = length - 1; i >= 0; i--) {
                        if (i === length - 1) {
                            resultArr[i] = netArr[i];
                        } else {
                            if (i === 0) {
                                resultArr[i] = '';
                            } else {
                                resultArr[i] = netArr[i] + resultArr[i + 1];
                            }
                        }
                    }
                    return resultArr;
                }

                function generateTableWithRunningTotals(netArr, indemnityArr, produceTotal) {
                    var years = [11, 16, 21];
                    var displayYears = years.length;
                    var yearsPaid = document.getElementById('yearsPaid').value;

                    switch (yearsPaid) {
                        case "all":
                            endRow = allIntervalsPerf.length;
                            break;
                        case "20":
                            endRow = 21;
                            break;
                        case "15":
                            endRow = 16;
                            break;
                        case "10":
                            endRow = 11;
                            break;
                        default:
                            endRow = allIntervalsPerf.length;
                            break;
                    }

                    var runningTotals = years.map(year => calculateRunningTotal(netArr, year));

                    // Add "All Years" total if we're showing all intervals
                    if (endRow === allIntervalsPerf.length) {
                        years.push(netArr.length);
                        runningTotals.push(calculateRunningTotal(netArr, netArr.length));
                    }

                    var table = $('<table class="table table-bordered table-responsive w-100 runningTotals pdf-item"></table>');

                    // Add the headers
                    var mainHeaderRow = $('<tr></tr>');
                    mainHeaderRow.append(`<th colspan="3" style="text-align: center;">Annual Performance</th>`);
                    mainHeaderRow.append(`<th colspan="${displayYears}" style="text-align: center;">Running Totals</th>`);
                    table.append(mainHeaderRow);

                    // Add sub-headers
                    var headerRow = $('<tr></tr>');
                    headerRow.append('<th>Premium</th><th>Indemnity</th><th>Net</th>');

                    years.forEach(year => {
                        var yearLabel = (year === netArr.length) ? 'All Years' : (year - 1) + ' Year';
                        headerRow.append(`<th>${yearLabel}</th>`);
                    });
                    table.append(headerRow);

                    for (let i = 0; i < 21; i++) {
                        var dataRow = $('<tr></tr>');
                        dataRow.append(`<td>${formatter.format(produceTotal)}</td>`);
                        dataRow.append(`<td>${indemnityArr[i] !== undefined ? formatter.format(indemnityArr[i]) : ''}</td>`);
                        dataRow.append(`<td>${netArr[i] !== undefined ? formatter.format(netArr[i]) : ''}</td>`);

                        runningTotals.forEach(arr => {
                            dataRow.append(`<td>${arr[i] !== undefined ? formatter.format(arr[i]) : ''}</td>`);
                        });

                        table.append(dataRow);
                    }

                    $('#runningTotalsResults').empty().append(table);
                }

                function displayData2(data2) {
                    var yearsPaidInput = document.getElementById("yearsPaid").value.trim().toLowerCase();
                    var rainfallResults = $('#rainfallResults');
                    var selectedCountyName = countySelect.options[countySelect.selectedIndex].text;
                    rainfallResults.empty();

                    if (data2.length === 0) {
                        rainfallResults.append('<p>No data found</p>');
                    } else {
                        var table = $('<table id="rainfallTable" class="table table-bordered table-responsive w-100 rainfallTable pdf-item"></table>');
                        var titleRow = $('<tr></tr>');
                        var titleCell = $('<th colspan="12" style="text-align: center; font-size: 15px;">Rainfall-Percent of Avg</th>');
                        titleRow.append(titleCell);
                        table.append(titleRow);

                        var headerRow = $('<tr></tr>');
                        headerRow.append('<th>Year</th>');
                        var monthLabels = ['JanFeb', 'FebMar', 'MarApr', 'AprMay', 'MayJun', 'JunJul', 'JulAug', 'AugSep', 'SepOct', 'OctNov', 'NovDec'];
                        monthLabels.forEach(function (label) {
                            headerRow.append('<th>' + label + '</th>');
                        });
                        table.append(headerRow);

                        var limitedData = data2;
                        if (yearsPaidInput !== "all") {
                            var yearsPaid = 20;

                            if (!isNaN(yearsPaid)) {
                                yearsPaid += 1;
                                limitedData = data2.slice(0, yearsPaid);
                            } else {
                                console.log("Invalid input for yearsPaid.");
                            }
                        }

                        limitedData.forEach(function (row) {
                            var dataRow = $('<tr></tr>');
                            dataRow.append('<td>' + row.Year + '</td>');

                            monthLabels.forEach(function (month) {
                                var td = $('<td></td>');
                                if (row[month] !== null) {
                                    var value = parseFloat(row[month]).toFixed(1);
                                    td.text(value);

                                    // Conditional formatting based on the value
                                    var rainfallValue = parseFloat(value);
                                    if (rainfallValue < 89.9) {
                                        td.css('background-color', 'lightgreen');
                                    } else {
                                        td.css('background-color', 'transparent');
                                    }

                                } else {
                                    td.text("N/A");
                                }
                                dataRow.append(td);
                            });

                            table.append(dataRow);
                        });

                        rainfallResults.append(table);
                    }
                }
                $('#downloadPdf').click(function () {
                    downloadMultipleTablesAsPDF();
                });

                function downloadMultipleTablesAsPDF() {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF('landscape', 'pt', 'letter');

                    let yPosition = 60;

                    const imgUrl = 'https://upservu.com/wp-content/plugins/bigquery-data/AGManagement.png';
                    const imgWidth = 150;

                    const image = new Image();
                    image.src = imgUrl;
                    image.crossOrigin = "anonymous";

                    image.onload = function () {
                        const aspectRatio = image.naturalHeight / image.naturalWidth;
                        const imgHeight = imgWidth * aspectRatio;

                        doc.addImage(image, 'PNG', doc.internal.pageSize.width - imgWidth - 40, 20, imgWidth, imgHeight);

                        doc.setFontSize(25);
                        doc.text("PRF Quote", 40, yPosition);
                        yPosition += 20;

                        doc.setFontSize(12);

                        // ------------------ First Table ------------------
                        const firstTableData = [
                            [
                                document.getElementById('fullName').value || "N/A",
                                document.getElementById('state').value || "N/A",
                                document.getElementById('county').value || "N/A",
                                document.getElementById('grid_id').value || "N/A",
                                document.getElementById('theUse').value || "N/A",
                                document.getElementById('acres').value || "N/A",
                                document.getElementById('coverage').value || "N/A",
                                document.getElementById('prod_factor').value || "N/A"
                            ]
                        ];

                        const firstTableHeaders = [['Full Name', 'State', 'County', 'Grid', 'Use', 'Acres', 'Coverage (%)', 'Productivity Factor']];

                        doc.autoTable({
                            startY: yPosition,
                            head: firstTableHeaders,
                            body: firstTableData,
                            theme: 'grid',
                            styles: { cellPadding: 5 },
                            columnStyles: {
                                0: { cellWidth: 80 },
                                1: { cellWidth: 80 },
                                2: { cellWidth: 80 },
                                3: { cellWidth: 80 },
                                4: { cellWidth: 80 },
                                5: { cellWidth: 80 },
                                6: { cellWidth: 80 },
                                7: { cellWidth: 120 }
                            },
                            headStyles: {
                                fillColor: [105, 105, 105],
                                textColor: [255, 255, 255],
                            }
                        });

                        yPosition = doc.autoTable.previous.finalY + 20;

                        const elements = document.querySelectorAll('.pdf-item');

                        elements.forEach((element) => {
                            const rows = [];
                            let headers = [];
                            let isHeaderRowProcessed = false;

                            const tableRows = element.querySelectorAll('tr');

                            tableRows.forEach((row) => {
                                if (row.querySelector('th') && row.querySelector('th').getAttribute('colspan')) {
                                    return;
                                }
                                const cells = row.querySelectorAll('th, td');
                                const rowData = [];

                                cells.forEach((cell) => {
                                    if (cell.querySelector('select')) {
                                        const selectElement = cell.querySelector('select');
                                        const selectedText = selectElement.options[selectElement.selectedIndex].text;
                                        rowData.push(selectedText);
                                    }
                                    else if (cell.querySelector('input')) {
                                        const inputValue = cell.querySelector('input').value;
                                        rowData.push(inputValue);
                                    }
                                    else {
                                        rowData.push(cell.textContent.trim());
                                    }
                                });

                                if (!isHeaderRowProcessed && row.querySelector('th')) {
                                    headers.push(rowData);
                                    isHeaderRowProcessed = true;
                                } else {
                                    rows.push(rowData);
                                }
                            });

                            doc.autoTable({
                                startY: yPosition,
                                head: headers,
                                body: rows,
                                theme: 'grid',
                                headStyles: {
                                    fillColor: [105, 105, 105],
                                    textColor: [255, 255, 255],
                                }
                            });

                            yPosition = doc.autoTable.previous.finalY + 20;

                            if (yPosition > 500) {
                                doc.addPage();
                                yPosition = 40;
                            }
                        });

                        doc.save('PRFQuote.pdf');
                    };

                    image.onerror = function () {
                        console.error("Image could not be loaded from the URL");
                    };
                }
            });
        </script>
    </head>
    <div id="tableSection" class="container" style="max-width: 1300px; width:100%;">
        <form id="theForm" method="post" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            <div class="row mb-3 align-items-center justify-content-between">
                <h1 id="title" class="col-auto">PRF Quoter</h1>
                <img id="agmlogo" class="img-fluid float-end"
                    src="/srv/htdocs/wp-content/plugins/bigquery-data/AGManagement.png" alt="AGM Logo">
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <table class="table table-bordered table-responsive formTable">
                        <tr>
                            <th>Full Name</th>
                            <td><input id="fullName" type="text" name="fullName" class="form-control"
                                    placeholder="Enter Name" />
                            </td>
                            <th>State: </th>
                            <td><select id="state" name="state" class="form-control">
                                    <option value="">Select a State</option>
                                </select>
                            </td>
                            <th>County: </th>
                            <td><select id="county" name="county" class="form-control">
                                    <option value="">Select a County</option>
                                </select>
                            </td>
                            <th>Grid: </th>
                            <td><select id="grid_id" name="grid_id" class="form-control">
                                    <option value="">Select a Grid</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Use: </th>
                            <td><select id="theUse" name="theUse" class="form-control"
                                    required>
                                    <option value="" disabled selected>Select Use</option>
                                    <option value="030">Haying</option>
                                    <option value="007">Grazing</option>
                                </select>
                            </td>
                            <th>Acres: </th>
                            <td><input type="number" name="acres" id="acres" class="form-control" size="5" required />
                            </td>
                            <th>Coverage (Percentage): </th>
                            <td><select id="coverage" name="coverage" class="form-control"
                                    required>
                                    <option value="" disabled selected>Select Coverage</option>
                                    <option value="70">70</option>
                                    <option value="75">75</option>
                                    <option value="80">80</option>
                                    <option value="85">85</option>
                                    <option value="90">90</option>
                                </select>
                            </td>
                            <th>Productivity Factor: </th>
                            <td><input type="number" name="prod_factor" id="prod_factor" class="form-control" size="5"
                                    step="0.01" required />
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div id="midSection" class="row mb-3">
                <div class="col-md-4">
                    <table class="table table-bordered table-responsive formTable midTable pdf-item">
                        <tr>
                            <th>Insured Amount</th>
                            <th>Per Acre</th>
                            <th>Total</th>
                        </tr>
                        <tr>
                            <th>County Base Value</th>
                            <td id="display_county_acre">$</td>
                            <td id="display_county_total">$</td>
                        </tr>
                        <tr>
                            <th>Base + Productivity</th>
                            <td id="display_base_acre">$</td>
                            <td id="display_base_total">$</td>
                        </tr>
                        <tr>
                            <th>Insured Value</th>
                            <td id="display_insured_acre">$</td>
                            <td id="display_insured_total">$</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-4">
                    <table class="table table-bordered table-responsive formTable midTable pdf-item">
                        <tr>
                            <th>Premium</th>
                            <th>Per Acre</th>
                            <th>Total</th>
                        </tr>
                        <tr>
                            <th>Total Premium</th>
                            <td id="totalPrem_acre">$</td>
                            <td id="totalPrem_total">$</td>
                        </tr>
                        <tr>
                            <th>Subsidy</th>
                            <td id="sub_acre">$</td>
                            <td id="sub_total">$</td>
                        </tr>
                        <tr>
                            <th>Producer Premium</th>
                            <td id="prod_acre">$</td>
                            <td id="prod_total">$</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-4">
                    <table class="table table-bordered table-responsive formTable midTable pdf-item">
                        <tr>
                            <th></th>
                            <th>10 Yr</th>
                            <th>15 Yr</th>
                            <th>20 Yr</th>
                        </tr>
                        <tr>
                            <th>Payout Rate</th>
                            <td id="rate10">%</td>
                            <td id="rate15">%</td>
                            <td id="rate20">%</td>
                        </tr>
                        <tr>
                            <th>Avg Pmt</th>
                            <td id="pmt10">$</td>
                            <td id="pmt15">$</td>
                            <td id="pmt20">$</td>
                        </tr>
                        <tr>
                            <th>ROI</th>
                            <td id="roi10">%</td>
                            <td id="roi15">%</td>
                            <td id="roi20">%</td>
                        </tr>

                    </table>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <table class="table table-bordered table-responsive formTable" id="allocationForm">
                        <tr>
                            <th>Interval</th>
                            <th>Jan-Feb</th>
                            <th>Feb-Mar</th>
                            <th>Mar-Apr</th>
                            <th>Apr-May</th>
                            <th>May-Jun</th>
                            <th>Jun-Jul</th>
                            <th>Jul-Aug</th>
                            <th>Aug-Sep</th>
                            <th>Sep-Oct</th>
                            <th>Oct-Nov</th>
                            <th>Nov-Dec</th>
                            <th>Total</th>
                        </tr>
                        <tr>
                            <th>Allocation</th>
                            <td><input id="interval0" name="interval0" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval1" name="interval1" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval2" name="interval2" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval3" name="interval3" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval4" name="interval4" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval5" name="interval5" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval6" name="interval6" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval7" name="interval7" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval8" name="interval8" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval9" name="interval9" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td><input id="interval10" name="interval10" type="number" class="form-control interval-input"
                                    value="0" />
                            </td>
                            <td>
                                <p id="allocationTotal"></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <table class="table table-bordered table-responsive formTable pdf-item">
                        <tr>
                            <th>Interval</th>
                            <th></th>
                            <th>Jan-Feb</th>
                            <th>Feb-Mar</th>
                            <th>Mar-Apr</th>
                            <th>Apr-May</th>
                            <th>May-Jun</th>
                            <th>Jun-Jul</th>
                            <th>Jul-Aug</th>
                            <th>Aug-Sep</th>
                            <th>Sep-Oct</th>
                            <th>Oct-Nov</th>
                            <th>Nov-Dec</th>
                        </tr>
                        <tr>
                            <th>Total Indemnity</th>
                            <td>
                                <p></p>
                            </td>
                            <td>
                                <p id="TotalIndem0" name="TotalIndem0">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem1" name="TotalIndem1">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem2" name="TotalIndem2">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem3" name="TotalIndem3">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem4" name="TotalIndem4">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem5" name="TotalIndem5">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem6" name="TotalIndem6">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem7" name="TotalIndem7">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem8" name="TotalIndem8">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem9" name="TotalIndem9">$</p>
                            </td>
                            <td>
                                <p id="TotalIndem10" name="TotalIndem10">$</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Premium</th>
                            <td>
                                <p></p>
                            </td>
                            <td>
                                <p id="TotalPrem0">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem1">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem2">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem3">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem4">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem5">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem6">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem7">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem8">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem9">$</p>
                            </td>
                            <td>
                                <p id="TotalPrem10">$</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Net Indemnity</th>
                            <td>
                                <p></p>
                            </td>
                            <td>
                                <p id="NetIndem0" name="NetIndem0">$</p>
                            </td>
                            <td>
                                <p id="NetIndem1" name="NetIndem1">$</p>
                            </td>
                            <td>
                                <p id="NetIndem2" name="NetIndem2">$</p>
                            </td>
                            <td>
                                <p id="NetIndem3" name="NetIndem3">$</p>
                            </td>
                            <td>
                                <p id="NetIndem4" name="NetIndem4">$</p>
                            </td>
                            <td>
                                <p id="NetIndem5" name="NetIndem5">$</p>
                            </td>
                            <td>
                                <p id="NetIndem6" name="NetIndem6">$</p>
                            </td>
                            <td>
                                <p id="NetIndem7" name="NetIndem7">$</p>
                            </td>
                            <td>
                                <p id="NetIndem8" name="NetIndem8">$</p>
                            </td>
                            <td>
                                <p id="NetIndem9" name="NetIndem9">$</p>
                            </td>
                            <td>
                                <p id="NetIndem10" name="NetIndem10">$</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Years Paid of</th>
                            <td><select id="yearsPaid" name="yearsPaid" class="form-control years-paid">
                                    <option value="10">10</option>
                                    <option selected value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="all">All</option>
                                </select></td>
                            <td>
                                <p id="yearsPaid0" name="yearsPaid0"></p>
                            </td>
                            <td>
                                <p id="yearsPaid1" name="yearsPaid1"></p>
                            </td>
                            <td>
                                <p id="yearsPaid2" name="yearsPaid2"></p>
                            </td>
                            <td>
                                <p id="yearsPaid3" name="yearsPaid3"></p>
                            </td>
                            <td>
                                <p id="yearsPaid4" name="yearsPaid4"></p>
                            </td>
                            <td>
                                <p id="yearsPaid5" name="yearsPaid5"></p>
                            </td>
                            <td>
                                <p id="yearsPaid6" name="yearsPaid6"></p>
                            </td>
                            <td>
                                <p id="yearsPaid7" name="yearsPaid7"></p>
                            </td>
                            <td>
                                <p id="yearsPaid8" name="yearsPaid8"></p>
                            </td>
                            <td>
                                <p id="yearsPaid9" name="yearsPaid9"></p>
                            </td>
                            <td>
                                <p id="yearsPaid10" name="yearsPaid10"></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Indem % of Prem</th>
                            <td>
                                <p></p>
                            </td>
                            <td>
                                <p id="IndemPrem0" name="IndemPrem0">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem1" name="IndemPrem1">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem2" name="IndemPrem2">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem3" name="IndemPrem3">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem4" name="IndemPrem4">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem5" name="IndemPrem5">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem6" name="IndemPrem6">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem7" name="IndemPrem7">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem8" name="IndemPrem8">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem9" name="IndemPrem9">%</p>
                            </td>
                            <td>
                                <p id="IndemPrem10" name="IndemPrem10">%</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <table class="submit">
                <tr>
                    <td><input type="submit" id="updateQuote" name="updateQuote" value="Update Quote"
                            class="btn btn-secondary" />
                    </td>
                    <td><input type="button" id="downloadPdf" name="downloadPdf" value="Download quote"
                            class="btn btn-primary" /></td>
                    <td><button type="button" data-bs-toggle="modal" data-bs-target="#releaseNotes"
                            class="btn btn-info">Show release Notes</button>
                    </td>
                    <td><button type="button" data-bs-toggle="modal" data-bs-target="#howToUse" class="btn btn-info">How
                            to Use Quoting Tool</button>
                    </td>
                </tr>
            </table>
            <div class="row results2Div mb-5">
                <div class="col-lg results2">
                    <div id="rainfallResults"></div>
                </div>
                <div class="col-lg-5 results">
                    <div id="runningTotalsResults"></div>
                </div>
            </div>
            <div class="modal" id="releaseNotes">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Currect Release Notes</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="releaseNotes">
                                <h3>Version 4.5</h3>
                                <ul>
                                    <li>Added company logo to PDF download.</li>
                                    <li>Modified Running Totals table by adding 10, 15, and 20 years running totals.</li>
                                    <li>Separated years running totals and Annual Performance numbers.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal" id="howToUse">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="howToUseContent">
                                <h3>How to Use Tool</h3>
                                <ul>
                                    <li>Enter fields at the top of the page.</li>
                                    <li>Enter allocation fields. Total of allocations must equal 100%. If left blank,
                                        data will not be fetched of '0' values.</li>
                                    <li>Select 'Years Paid' field. Default is 10.</li>
                                    <li>Once all fields are entered, click 'Update Quote' to fill in quote data.</li>
                                    <li>After data has load, click 'Update Running Totals' at the bottom to load Rainfall
                                        and Running
                                        Totals tables.</li>
                                </ul>
                                <h3>How to Enter New Values for New Quote</h3>
                                <ul>
                                    <li>To generate another quote, enter in corresponding values into fields.</li>
                                    <li>Once all new values entered, click 'Update Quote' to generate new quote data.
                                    </li>
                                    <li>Make sure to also click on 'Update Running Totals' button to generate new
                                        rainfall/running totals table data.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal" tabindex="-1" id="errorModal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Error</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p id="errorMessage">An error occurred.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div id="loadingIndicator" style="display:none;">
            <div class="spinner"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        window.onload = function () {
            sessionStorage.clear();
            document.getElementById('prod_factor').value = '';
        };
    </script>
    <style>
        .formTable th,
        .formTable td,
        .rainfallTable th,
        .rainfallTable td,
        .runningTotals th,
        .runningTotals td,
        .submit {
            border: 1px solid #ddd;
            font-family: Arial, Helvetica, sans-serif;
        }

        #tableSection th {
            background-color: gray;
            color: white;
        }

        .container-md {
            padding: 0;
        }

        #title {
            font-size: 40px;
            margin-left: 10px;
            font-weight: bolder;
        }

        .submit {
            border: 0px;
        }

        #rainfallResults tr:hover,
        #runningTotalsResults tr:hover {
            background-color: lightblue;
        }

        .results2Div {
            position: relative;
            right: 50px;
            padding-top: 40px;
        }

        .results.col-lg-5>div {
            width: 200%;
        }

        .formTable td:hover {
            background-color: lightblue;
        }

        .formTable td,
        .runningTotals td,
        .rainfallTable td {
            text-align: right;
        }

        .runningTotals th,
        .rainfallTable th {
            text-align: center;
        }

        .runningTotals {
            width: 100%;
            max-width: 700px;
        }

        .rainfallTable th {
            font-size: 12px;
        }

        #agmlogo {
            height: 100px;
            width: auto;
            margin-right: 10px;
        }

        .page-id-1961 header,
        .page-id-1961 footer,
        .wp-block-post-title {
            display: none;
        }

        #loadingIndicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 9999;
        }

        .spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('bigquery_form', 'bigquery_form');