<?php
// Test script to verify voucher hash extraction
$testLink = 'https://gift.truemoney.com/campaign?v=0196fe0966d57bc8ae5789f50f9747889a5';

echo "Testing TrueMoney link: " . $testLink . "\n\n";

// Test the regex patterns
$voucherHash = null;

// Pattern 1: Standard gift link
$linkRegex1 = '/https:\/\/gift\.truemoney\.com\/campaign\/?\?v=([0-9A-Fa-f]{32})/';
if (preg_match($linkRegex1, $testLink, $matches)) {
    $voucherHash = $matches[1];
    echo "Pattern 1 matched: " . $voucherHash . "\n";
}

// Pattern 2: Alternative campaign link format
if (!$voucherHash) {
    $linkRegex2 = '/https:\/\/gift\.truemoney\.com\/campaign\?v=([0-9A-Fa-f]{32})/';
    if (preg_match($linkRegex2, $testLink, $matches)) {
        $voucherHash = $matches[1];
        echo "Pattern 2 matched: " . $voucherHash . "\n";
    }
}

// Pattern 3: More flexible pattern for various hash lengths and formats
if (!$voucherHash) {
    $linkRegex3 = '/v=([0-9A-Fa-f]{18,50})/';
    if (preg_match($linkRegex3, $testLink, $matches)) {
        $voucherHash = $matches[1];
        echo "Pattern 3 matched: " . $voucherHash . "\n";
    }
}

if ($voucherHash) {
    echo "\nSuccessfully extracted voucher hash: " . $voucherHash . "\n";
    echo "Hash length: " . strlen($voucherHash) . " characters\n";
} else {
    echo "\nFailed to extract voucher hash from the link.\n";
}
?>
