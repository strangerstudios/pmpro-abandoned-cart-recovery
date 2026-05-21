=== Paid Memberships Pro - Abandoned Cart Recovery ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, cart, abandoned, recovery
Requires at least: 5.4
Tested up to: 6.9
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Recover lost revenue by capturing abandoned carts and following up with customers to complete their purchase.

== Description ==

The Abandoned Cart Recovery Add On helps maximize your membership site's revenue by re-engaging users who did not complete checkout. Through automated, customizable email reminders, you can recover orders and convert interested visitors into paying members. Plus, the Add On includes reports to track recovered revenue and carts along the three-stage recovery in process.

* Select which levels should send abandoned cart emails.
* Automatically sends up to three email reminders for abandoned carts.
* Fully customizable email templates for each reminder with opt-out options.
* Detailed reporting of recovery attempts, recovered orders, and recovered revenue.
* Privacy-focused integration with your site's existing privacy policy.
* Optional Resume Checkout Bar: capture the visitor's cart on the checkout page and show a popup plus sticky bottom bar on other pages that links them back to checkout with their level, discount code, and payment plan pre-applied.

== Installation ==

1. Upload the `pmpro-abandoned-cart-recovery` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-abandoned-cart-recovery/issues

== Changelog ==
= TBD =
* FEATURE: New "Resume Checkout Bar". When enabled, captures level/discount/payment plan when a visitor reaches the checkout page, then shows a popup and sticky bottom bar on subsequent pages linking back to checkout with everything pre-filled. Saved to cookie + user meta. Cleared on successful checkout. Settings live on Memberships > Abandoned Cart Recovery.

= 1.0.3 - 2026-05-01 =
* ENHANCEMENT: Recovery reminder emails are now scheduled via Action Scheduler when running PMPro 3.5 or newer. Sites on older versions continue to use WP-Cron. #17 (@andrewlimaza)
* ENHANCEMENT: Updated the default subject and body for the three recovery reminder email templates to use Liquid (`{{ variable }}`) syntax on PMPro 3.7 and newer. Older PMPro versions continue to receive the legacy `!!variable!!` defaults. #18 (@dparker1005)

= 1.0.2 - 2026-01-14 =
* BUG FIX/ENHANCEMENT: Better localization support in default email templates. #15 (@ideadude)
* BUG FIX: Fixes a fatal error when Abandoned Cart Recovery has no levels selected during cron execution. #16 (@andrewlimaza)
* BUG FIX: Fixing broken link to view orders when running PMPro v3.6+. #14 (@dparker1005)

= 1.0.1 - 2025-09-15 =
* BUG FIX: Fixed a fatal error that could occur while processing recovery attempts when a user or level has been deleted. #11 (@dparker1005)

= 1.0 - 2025-08-11 =
* ENHANCEMENT: Now allowing sending test emails when editing email templates. #8 (@dparker1005)
* ENHANCEMENT: Now showing all available email template variables when editing email templates. #8 (@dparker1005)
* BUG FIX: Fixed PHP notices that would be logged when the plugin is active. #6, #9 (@MaximilianoRicoTabo, @dparker1005)

= 0.1 - 2024-12-24 =
* Initial release