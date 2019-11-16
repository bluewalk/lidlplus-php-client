# lidlplus-php-client
Lidl Plus PHP API client

This library allows you to query your Lidl Plus receipts, coupons and stores.
It even allows you to generate a JPEG receipt to use in your automation scripts, e.g. automatically add a receipt to your transactions.

## How to use
First of all you need to get your `refresh_token` from your app. To do this you need to set up a MITM proxy and capture traffic.
You can do this with Fiddler or Proxie. Install the CA certificate on your phone, start capture and start your Lidl Plus app.
You'll see a request going to `https://accounts.lidl.com/connect/token` which will use your `refresh_token`.

```php
$lidl = new LidlPlus('MyRefreshToken');
$receipts = $lidl->GetReceipts();

$latest = $lidl->GetReceiptJpeg($receipts->Records[0]->Id);

header('Content-type: image/jpeg');
print $latest;
```

## Acknowledgments
This script is using a stripped down version of Kreative Software's barcode.php (https://github.com/kreativekorp/barcode)
