=== Bizapp for WooCommerce ===
Contributors: bizappventures
Tags: bizapp, woocommerce, integration, product, sync, order
Requires at least: 4.6
Tested up to: 6.5
Stable tag: 2.0.1

Bizapp integration for WooCommerce.

== Description ==

To synchronize your Bizapp products and send WooCommerce orders to your Bizapp account.

== Installation ==

1. Log in to your WordPress admin.
2. Search plugins "Bizapp for WooCommerce" and click Install Now.
3. Activate the plugin.
4. Navigate to "Bizapp" in the sidebar to access the plugin settings page.
5. Follow the instructions and update the plugin settings.

== Changelog ==
= 2.0.8 - 2024-08-13 =
- Fix sync for old product in HPOS

= 2.0.7 - 2024-07-26 =
- Remove update stock delay
- Add logs on wp-content/debug.log

= 2.0.6 - 2024-07-22 =
- Fix In Stock (Unlimited)

= 2.0.4 - 2024-07-17 =
- HPOS enabled
- Fix webhook on HPOS 

= 2.0.2 - 2024-06-11 =
- Show Logged Response: when push order manual to BizApp 

= 2.0.1 - 2024-06-05 =
1.⁠ ⁠Create selected products in Woocommerce store with the data provided in the API.
2.⁠ ⁠Use load balancing and user experience strategies to improve the user experience and the consistency and efficiency of the product creation.
3.⁠ ⁠Show a progress bar with relevant info when creating the products.
4.⁠ ⁠⁠Not importing products from API when the Auto Import Switch is "OFF"
5.⁠ ⁠Only Importing the selected products when the Auto Import Switch is "ON"
6.⁠ ⁠Disable the checkbox when the products were already in Sync.
7.⁠ ⁠Enable the checkbox if the previously Synced product was deleted.

= 1.2.10 - 2022-11-15 =
- Fixed: Product synchronization stock status not updated

= 1.2.9 - 2021-07-10 =
- Fixed: Bizapp product list not loaded in settings page for multisite

= 1.2.8 - 2021-07-03 =
- Fixed: Push order error if SKU is empty on the variation

= 1.2.7 - 2021-05-16 =
- Added: Display tracking number in order details table

= 1.2.6 - 2021-05-16 =
- Fixed: Replace empty state and country name with its code in order details

= 1.2.5 - 2021-04-23 =
- Fixed: Empty state name in order details
- Modified: Postcode order in the customer's address

= 1.2.3 - 2021-04-18 =
- Fixed: Ignore empty product data on webhook request
- Fixed: Allow special characters on webhook request
- Minor code re-structuring

= 1.2.0 - 2021-03-28 =
- Added: Sent to Bizapp column in Orders page
- Major code re-structuring

= 1.1.5 - 2021-03-05 =
- Fixed: Featured and gallery image missing on product sync

= 1.1.4 - 2021-03-02 =
- Modified: Improve error handling - API request error

= 1.1.3 - 2021-03-02 =
- Fixed: Bizapp product SKU not detected on variable product

= 1.1.2 - 2021-03-01 =
- Fixed: Change fatal error to normal error when API request is timeout

= 1.1.1 - 2021-02-28 =
- Modified: Request timeout to 30 seconds - default: 5 seconds

= 1.1.0 - 2021-02-11 =
- Added: Payment method ID, shipping method title and ID parameter in WooCommerce order data submission to Bizapp
- Added: Automatically update tracking number and change order status to completed
- Added: Admin able to manually send the order to Bizapp, with bulk actions
- Added: Admin able to set SKU for each variable for variable product
- Modified: Pass state/country name instead of state/country code in WooCommerce order data submission to Bizapp
- Code re-structuring and minor improvement

= 1.0.0 =
- Initial release of the plugin.
