# PaywithTerra PHP Library
Officially supported WooCommerce PaywithTerra Payment Gateway plugin.  
Based on [PaywithTerra PHP Library](https://github.com/paywithterra/php-api-library).

## Prerequisites
PHP version 5.6, 7.0, 7.1, 7.2, 7.3, or 7.4  
PHP extensions: ext-json, ext-curl, ext-mbstring  
Updated WordPress and WooCommerce versions


## Installation

Once our plug-in will be reviewed in official plugins stores, we will add easier ways to install in a few clicks.

### A. Install manually

Download the [release on GitHub](https://github.com/paywithterra/woocommerce-paywithterra/releases)
and unpack to the `wp-content/plugins` directory.

### B. Install from GitHub

~~~~ bash
cd wp-content/plugins

git clone --recursive https://github.com/paywithterra/woocommerce-paywithterra.git
~~~~

## Usage

1. Activate plugin in WordPress Dashboard named  **WooCommerce PaywithTerra Payment Gateway**.
2. Go to WooCommerce - Settings - Payments and press "Set up" button near the new **PaywithTerra** method.
3. Fill the API Key and Address fields and make payment method Active.

**That's all! Now your customers are able to pay for orders through the Terra blockchain.**


## License
[The MIT License (MIT)](LICENSE)