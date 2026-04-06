<?php
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function test_logic($tLat, $tLng, $tAcc, $maxDist, $sLat, $sLng, $sAcc) {
    $distance = haversineDistance($tLat, $tLng, $sLat, $sLng);
    
    // BACKEND LOGIC (Copied from modified attendance_api.php)
    $tAccVal = floatval($tAcc ?? 30);
    $sAccVal = floatval($sAcc > 0 ? $sAcc : 30);
    $extraBuffer = 10;
    $allowedDistance = $maxDist + $tAccVal + $sAccVal + $extraBuffer;
    
    $passed = ($distance <= $allowedDistance);
    
    echo "--- Test Case ---\n";
    echo "Teacher: ($tLat, $tLng) Acc: {$tAcc}m, MaxDist: {$maxDist}m\n";
    echo "Student: ($sLat, $sLng) Acc: {$sAcc}m\n";
    echo "Calculated Distance: " . round($distance, 2) . "m\n";
    echo "Allowed Distance (Max + T_Acc + S_Acc + 10): " . round($allowedDistance, 2) . "m\n";
    echo "Result: " . ($passed ? "PASSED ✅" : "FAILED ❌") . "\n";
    return $passed;
}

// Case 1: Close together, high GPS error (The problematic case)
// 12.9716, 77.5946 is Bangalore
test_logic(12.9716, 77.5946, 50, 15, 12.9719, 77.5948, 60);
// Distance is ~40m. 
// Old logic: 15 + 30 = 45m. (Might pass, but barely).
// New logic: 15 + 50 + 60 + 10 = 135m. (Will pass).

echo "\n";

// Case 2: Far apart, low error
test_logic(12.9716, 77.5946, 10, 15, 12.9750, 77.6000, 10);
// Distance is ~700m. 
// New logic: 15 + 10 + 10 + 10 = 45m. (Will fail).

echo "\n";

// Case 3: Exactly the same coords (Ideal case)
test_logic(12.9716, 77.5946, 20, 15, 12.9716, 77.5946, 20);
// Distance 0.
// New logic: 15 + 20 + 20 + 10 = 65m. (Will pass).
