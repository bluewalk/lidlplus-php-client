# lidlplus-php-client
Lidl Plus PHP API client

This library allows you to query your Lidl Plus receipts, coupons and stores.
It even allows you to generate a JPEG receipt to use in your automation scripts, e.g. automatically add a receipt to your transactions.

## Get your refresh Token
You first need to retrieve your refreshToken. This can be done with the nodeJS script getLidlRefreshToken.js. You might need to install
additional node JS libraries first with npm (e.g. request or openid-client).
Run the script and enter the country and language with the country you are using your account with.

## How to use
First of all you need to get your `refresh_token` from your app as described above. Afterwards the library can be used as described below.
Mind that the country code is essential to retrieve your purchases. If the country code does not match the country where you purchased someting,
your will not be able to receive your transactions.

Tested and working countrieCodes: Netherlands (default, 'NL') and Germany ('DE').


```php
require_once __DIR__ . 'vendor/autoload.php'; 

$lidl = new Net\Bluewalk\LidlPlus\LidlPlus('MyRefreshToken', 'CountryCode');
$receipts = $lidl->GetReceipts();

$latest = $lidl->GetReceiptJpeg($receipts->Records[0]->Id);

header('Content-type: image/jpeg');
print $latest;
```

## Acknowledgments
This script is using a stripped down version of Kreative Software's barcode.php (https://github.com/kreativekorp/barcode).

Script to fetch refresh_token is based on based script of Bastiaan Steinmeier, https://github.com/basst85.
