=== PaywithTerra Payment Gateway ===
Contributors: paywithterra
Tags: woocommerce, gateway, crypto, terra
Donate link: https://paywithterra.org
Requires at least: 4.4
Tested up to: 6.1
Requires PHP: 5.6
License: MIT
License URI: https://raw.githubusercontent.com/paywithterra/woocommerce-paywithterra/main/LICENSE
Stable tag: 2.0.0

Take Terra payments on your WooCommerce store.

== Description ==
Officially supported PaywithTerra Payment Gateway plugin for WooCommerce.
It uses the publicly available Terra blockchain finder API to search for the payment and automatically update the order status.

== Installation ==
1. Activate plugin in WordPress Dashboard named PaywithTerra Payment Gateway.
2. Go to WooCommerce - Settings - Payments and press Set up button near the new PaywithTerra method.
3. Fill the Setting form with your Terra Address and make payment method Active.

== Frequently Asked Questions ==
= Does this require an PaywithTerra account? =

No! The plugin starting from v2 does not require any account.

= Does this require an SSL certificate? =

An SSL certificate is recommended for additional safety and security for your customers.

= Where do I find my wallet address? =

Create Terra wallet using any Terra client. Details here: https://setup-station.terra.money/

== Changelog ==

= 2.0.0 - 2022-10-08 =
* Update - Rewritten plugin from scratch to work with official Terra finder instead of addition API.

= 1.0.7 - 2022-01-10 =
* Update - New option to disable ssl-check for outdated servers

= 1.0.6 - 2021-09-15 =
* Update - Order note about successful payment now contains link to Terra Finder

= 1.0.5 - 2021-09-04 =
* Update - Plugin now shows the last HTTP code in case of order creation error

= 1.0.4 - 2021-09-04 =
* Update - Plugin now checks if WooCommerce activated before starting

= 1.0.3 - 2021-09-03 =
* Update - UI now contains link to quick-navigation for plugin setup

= 1.0.2 - 2021-09-02 =
* Update - Added logging option, ability to select icon and few fixes (security and reviewity)

= 1.0.1 - 2021-09-01 =
* Update - First public version.