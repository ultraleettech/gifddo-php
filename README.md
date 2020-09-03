# Gifddo PHP Client
Gifddo payment service integration library.

## Prerequisites
To get started with Gifddo integration, you will need to generate a key pair. You can do that using the `openssl` command line tool:
```
openssl genrsa 2048 > privkey.pem
openssl rsa -in privkey.pem -pubout -out pubkey.pem -outform PEM
```

These commands will generate the keys in the required (PEM) format. Save the files somewhere in your project folder.

You will then have to contact Gifddo and request a merchant account (in either live or testing environment). Make sure to send them the **public** key you just generated above. In response, you should receive a merchant ID, which you'll need when initializing the library.

## Usage
Install the library in your project via Composer:
```
composer require gifddo/client
```

Initialize the client by instantiating the class:
```
$merchantId = 'MERCHANTID'; // The ID you received from Gifddo
$privateKey = file_get_contents('privkey.pem'); // Change the path as needed
$testMode = false; // Set to true to use the test environment

$gifddo = new \Gifddo\Client($merchantId, $privateKey, $testMode);
```

Create and sign payment request parameters:
```
$params = $gifddo->initiate([
    'stamp' => 'uniqueID', // Unique ID for the payment, max 20 characters. A random string is generated if omitted.
    'amount' => 10.00, // Required. Payment amount.
    'currency' => 'EUR', // Payment currency. Defaults to EUR.
    'reference' => 'REF', // Required. Any reference data you want to associate with the payment.
    'email' => 'john@domain.com', // Required. Payer e-mail address.
    'first_name' => 'John', // Required. Payer first name.
    'last_name' => 'Smith', // Required. Payer last name.
    'return_url' => 'https://yourdomain.com/success', // Required. URL to redirect the user to upon successful payment.
    'cancel_url' => 'https://yourdomain.com/fail', // URL to redirect the user to upon failed or cancelled payment. Defaults to return_url.
]);
```

Perform the request:
```
// Redirect the browser automatically
$gifddo->request($params);

// Or, alternatively, only return the request URL:
$url = $gifddo->request($params, true);
```

> Note: instead of having the backend make the API request for you, you may want to perform it directly on the frontend. You can do so by returning the parameters you received in the `initiate` call above to the browser, along with the Gifddo API endpoint URL, which you can obtain by calling `$client->getUrl()`:
> ```
> $response = [
>     'url' => $client->getUrl(),
>     'params' => $params,
> ];
> ```
>
> On the frontend side, you can then populate a hidden form with the fields listed in `params`, and post it to the `url`. Gifddo will automatically redirect the browser to the payment page.

The user then makes the payment on the Gifddo site (at the redirected or returned URL). When the process is complete, the user is redirected to the `return_url` provided in the `initiate` method call. Additionally, the provided URL also receives a payment status update directly from Gifddo, with the same data, except for the `VK_AUTO` request parameter, which is set to `Y` in case of automatic status update, and `N` for client browser request. You should make sure to check the value of that parameter when deciding whether to redirect the user (or display a success/fail message, or anything else based on the specific needs of your application).

Verify the payment status message on the `return_url` (and `cancel_url`, if you set a different one for that) endpoint as follows:
```
// Verify the signature:
if (!$gifddo->verify($_REQUEST, $_REQUEST['VK_MAC'])) {
    // do something to indicate that message signature verification failed
}

// Record the payment status update here
$paymentId = $_REQUEST['VK_STAMP'];
// ...

// Display success or fail message to the user (or redirect the browser)
switch ($_REQUEST['VK_SERVICE']) {
    case \Gifddo\Client::SUCCESSFUL_RESPONSE_CODE:
        if ($_REQUEST['VK_AUTO'] === 'N') {
            echo 'Payment completed';
        }
        break;

    case \Gifddo\Client::UNSUCCESSFUL_RESPONSE_CODE:
        if ($_REQUEST['VK_AUTO'] === 'N') {
            echo 'Payment failed';
        }
        break;
}
```

Modify the above code according to your needs. You can omit the `switch` block in case you set different `return_url` and `cancel_url`.

## License
Copyright (c) 2020-Present Gifddo

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
