# PaywithTerra WooCommerce plugin
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/stars/paywithterra-payment-gateway?color=%230d6efd&label=WordPress%20marketplace)](https://wordpress.org/plugins/paywithterra-payment-gateway/)
[![Live demo shop](https://img.shields.io/badge/Live%20demo-shop-brightgreen)](https://woocommerce-demo.paywithterra.com/)
[![YouTube Video Views](https://img.shields.io/youtube/views/gLrBzvdZG4A?label=Video%20demo)](https://youtu.be/gLrBzvdZG4A)
[![Twitter Follow](https://img.shields.io/twitter/follow/PaywithTerra?label=%40PaywithTerra)](https://twitter.com/paywithterra)

Officially supported PaywithTerra Payment Gateway plugin for WooCommerce.  
Based on [PaywithTerra PHP Library](https://github.com/paywithterra/php-api-library).


## Prerequisites
PHP version 5.6, 7.0, 7.1, 7.2, 7.3, or 7.4  
PHP extensions: ext-json, ext-curl, ext-mbstring  
Updated WordPress and WooCommerce versions


## Installation

### From WordPress admin panel
Open page "Plugins - Add", search for keyword `paywithterra` and press "Install now"  

<details>
  <summary>Click to show screenshot</summary>

![image](https://user-images.githubusercontent.com/89657732/132091932-422e225d-163a-42ee-ac8a-23f97396da37.png)

</details>

Plugin installed - see [Usage](#usage) section.

### From zip-archive (manually)

1. Download archive from [WordPress marketplace](https://wordpress.org/plugins/paywithterra-payment-gateway/)
or from [release on GitHub](https://github.com/paywithterra/woocommerce-paywithterra/releases/latest)
2. Unpack this archive to the `wp-content/plugins` directory.

### From git (console)

~~~~ bash
cd wp-content/plugins

git clone --recursive https://github.com/paywithterra/woocommerce-paywithterra.git
~~~~

## Usage

1. Activate plugin in WordPress Dashboard named  **PaywithTerra Payment Gateway**
2. Press "Set up" button near the plugin
3. Fill the API Key and Address fields and make payment method Active.

**That's all! Now your customers are able to pay for orders through the Terra blockchain.**

<details>
  <summary>Click to show screenshots</summary>

![image](https://user-images.githubusercontent.com/89657732/132068568-77115288-9a88-4bca-b154-480dfae015ae.png)
![image](https://user-images.githubusercontent.com/89657732/132092205-975dfe66-94dd-40f1-be7c-c602b478050a.png)

</details>

## License
[The MIT License (MIT)](LICENSE)
