# Shipping Loggi for WooCommerce #
**Contributors:** mariovalney  
**Donate link:** https://github.com/mariovalney/shipping-loggi-for-woocommerce  
**Tags:** woocommerce, api, loggi, shipping, mariovalney  
**Requires at least:** 5.0  
**Tested up to:** 5.6  
**Requires PHP:** 7.0  
**Stable tag:** trunk  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Shipping via motorcycle courier with Loggi

## Description ##

A plugin to integrate Loggi with WooCommerce.

[WooCommerce](https://wordpress.org/plugins/woocommerce/ "Install it first, of course") is a awesome plugin used by 5+ million WordPress websites to create e-commerce.

### How to Use ###

Easy and quick!

Activate "Shipping Loggi for WooCommerce".

After that, add Loggi as shipping method and insert your credentials.

### Translations ###

You can [translate Shipping Loggi for WooCommerce](https://translate.wordpress.org/projects/wp-plugins/shipping-loggi-for-woocommerce) to your language.

### Review ###

We would be grateful for a [review here](https://wordpress.org/support/plugin/shipping-loggi-for-woocommerce/reviews/).

### Support ###

* WooCommerce - 4.8

## Installation ##

First

* Install [WooCommerce](https://wordpress.org/plugins/woocommerce/) and activate it.

Next

* Install "Shipping Loggi for WooCommerce" by plugins dashboard.

Or

* Upload the entire `shipping-loggi-for-woocommerce` folder to the `/wp-content/plugins/` directory.

Then

* Activate the plugin through the 'Plugins' menu in WordPress.

## Frequently Asked Questions ##

### How packages are calculated? ###

We'll send all data about height, width, length and weight to Loggi and receive all the calculated packages.

### What's the limit of sizes? ###

Loggi has the limit of 55x55x55cm and 20kg.

### How can I limit the shipping rate to a product or type of products? ###

By default we'll consider all products that need shipping, but you can use [Shipping Classes](https://docs.woocommerce.com/document/product-shipping-classes) to filter the method to one or more shipping classes or for products with none shipping class.

### What is the "try to merge boxes" option? ###

By default every item of order will be one package and we'll send all data to Loggi.

Loggi will receive two items by time and check if they can be sent in 1 or 2 deliveries. For example, two items with 20x20x20 will be sent in one travel, because they are less than the limit of 55x55x55. But two items with 40x40x40 will result in two deliveries (double of cost).

If you activate this option we'll try to agrupate items by our own way before send data to Loggi as they can handle only 2 packages by request.

### What are the credentials? ###

Your e-mail and API Key from Loggi.

If you do not know your API Key, you can insert your password and will try to retrieve it. [Learn more](https://docs.api.loggi.com/docs/obtendo-suas-credenciais).

### What is a shop? ###

Every Loggi integration needs a shop. So you should request one for Loggi Support and select it on shipping settings.

You can have one Loggi integration for every shop if you can send items from multiple cities.

### Can I help you? ###

Yes! Visit [GitHub repository](https://github.com/mariovalney/shipping-loggi-for-woocommerce).

## Changelog ##

### 1.0 ###

* It's alive!

## Upgrade Notice ##

### 1.0.0 ###

It's alive!
