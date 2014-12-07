=== WPSC DTA Export ===
Contributors: Kolja Schleich
Tags: shopping cart, tickets, shop, e-commerce, stock counter, DTA
Requires at least: 2.5
Tested up to: 4.0.1
Stable tag: 1.6

Export orders from [WP E-Commerce Plugin](http://wordpress.org/extend/plugins/wp-e-commerce/) as DTA-File. This file format is used as automatic payment method in Germany.

== Description ==

Export orders from the [WP E-Commerce Plugin](http://wordpress.org/extend/plugins/wp-e-commerce/) as DTA File.
This file format is used in Germany to exchange information about money transactions with banks or online banking programs. 
It uses the DTA class written by [Hermann Stainer](http://www.web-gear.com/) and [Martin Schütte](http://pear.php.net/package/Payment_DTA).
It automatically remembers the last exported order to avoid double payments.
Tested with WP E-commerce Version 3.9.

The plugin needs PHP 5+.

**Translations**

* German

== Installation ==

To install the plugin to the following steps

1. Unzip the zip-file and upload the content to your Wordpress Plugin directory.
2. Activiate the plugin via the admin plugin page.

== Credits ==
The WPSC DTA Export icon is adapted from the Fugue Icons of http://www.pinvoke.com/.

== Screenshots ==
1. Administration Page

== Changelog == 

= 1.6 =
* NEW: Compatible with Wordpress 4.0.1 and WP E-Commerce 3.9
* UPDATE: Use version 1.4.3 of DTA class further developed by [Martin Schütte](http://pear.php.net/package/Payment_DTA).
* BUGFIX: escape html specialchars in $_POST variables for security

= 1.0 - 1.5 =
* First versions for WP E-commerce 3.7.4.