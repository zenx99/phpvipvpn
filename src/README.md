# TrueMoney Voucher Class

If you want to use the TrueMoney gift/voucher functionality, you need to create a `Voucher.php` file in this directory.

## Installation

1. You can install a TrueMoney Voucher library using Composer:

```bash
composer require m4h45amu7x/truemoney-gift
```

2. Or create a `Voucher.php` file manually in this directory with the required functionality.

The Voucher class should have at least these methods:
- A constructor that accepts a phone number and voucher URL
- A `verify()` method to verify if the voucher is valid
- A `redeem()` method to redeem the voucher

Example structure:

```php
<?php
namespace M4h45amu7x;

class Voucher {
    private $mobileNumber;
    private $voucherUrl;
    
    public function __construct($mobileNumber, $voucherUrl) {
        $this->mobileNumber = $mobileNumber;
        $this->voucherUrl = $voucherUrl;
    }
    
    public function verify() {
        // Verify voucher code logic
        // Return array with status and voucher data
    }
    
    public function redeem() {
        // Redeem voucher logic
        // Return array with status and redemption data
    }
}
```

For more information, you can check out available TrueMoney voucher libraries on GitHub or Packagist.
