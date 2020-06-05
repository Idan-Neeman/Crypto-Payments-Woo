=== Crypto Payments Woo - Bitcoin/FairCoin Gateway for WooCommerce ===

Contributors: Idan-Neeman, cryptartica, sanchaz  
Donation address: Bitcoin - 17iHDVnEktxcSvtcbNuhu16A9qhWLZ4n6Y | FairCoin - fanWh4UNEUqTpZU3JTFeesyzyoE1bew7ZC  
Tags: bitcoin, faircoin, bitcoin wordpress plugin, faircoin wordpress plugin  
Requires at least: Wordpress 3.0.1  
Tested up to: Wordpress 5.4.1  
Requires PHP: 5.6.40 or newer  
Stable tag: trunk  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Accept Bitcoin/FairCoin payment from WooCommerce store without help of middle man! Receive payment instantly and directly to your own coin address (generate on-the-fly by Electrum) without rotating to 3rd party wallet.
0 fees, 0 commissions, no third parties, financial sovereignty.
Using Electrum/ElectrumFair Master Public Keys. No keys are stored on your server, and your funds are always safe.

Bank the unbanked.

Your online store must use WooCommerce platform (free wordpress plugin).
Once you installed and activated WooCommerce, you may install and activate "Crypto Payments Woo".
Use at your own risk!

If you encounter any problems, please open an issue.

= Features =

* FairCoin and Bitcoin payment gateways.
* Zero fees and no commissions for Bitcoin/FairCoin payments processing from any third party.
* Accept payment directly into your personal Electrum/ElectrumFair wallet.
* No middleman for payments.
* Accept payment in Bitcoin/FairCoin for physical and digital downloadable products.
* Individual configurations for each gateway, allowing you to customise each one according to market conditions.
* Automatic conversion to Bitcoin/FairCoin via realtime exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.
* Ability to set the amount of time for which to cache the exchange rate, allowing you to react faster to market volatily.
* Store wide configurations such as automatically marking paid orders as completed and Privacy Mode (allows the reuse or not of addresses when orders are not completed).
* Ability to set main currency of your store in any currency or faircoin.

== Installation ==

1.  Install WooCommerce plugin and configure your store (if you haven't done so already - http://wordpress.org/plugins/woocommerce/).
2.  Install "Crypto Payments Woo" wordpress plugin just like any other Wordpress plugin.
3.  Activate.
4.  Download and install on your computer Electrum wallet program (Bitcoin - https://electrum.org | FairCoin - https://faircoin.co/download)
5.  Run and setup your wallet. For each Electrum please do the following actions:
6.  Click on View->Show Console
7.  Click on "Console" tab and run this command (to extend the size of wallet's gap limit): wallet.storage.put('gap_limit',1000)
8.  Grab your wallet's Master Public Key by navigating to:
	    Wallet -> Information, or (for older versions of Electrum): Preferences -> Import/Export -> Master Public Key -> Show
9.  Within your site's Wordpress admin, navigate to:
	    Crypto Payments Woo -> General Settings, then choose the relevant coin settings
	    and paste the value of Master Public Key into "Electrum Master Public Key" field.
10.  Change the rest of the coin settings as you wish (Exchange Rate Reference, Rate Multiplier etc).
11.  Press [Save changes]
12.  If you do not see any errors - your store is ready for operation and to access payments!
13.  Please donate BTC to: 17iHDVnEktxcSvtcbNuhu16A9qhWLZ4n6Y or FAIR to: fanWh4UNEUqTpZU3JTFeesyzyoE1bew7ZC 


== Screenshots ==

1. General Settings
![General Settings](screenshots/screenshot-1.png?raw=true)
2. BTC Settings
![BTC Settings](screenshots/screenshot-2.png?raw=true)
3. FAIR Settings
![FAIR Settings](screenshots/screenshot-3.png?raw=true)
4. BTC Gateway Settings
![BTC Gateway Settings](screenshots/screenshot-4.png?raw=true)
5. FAIR Gateway Settings
![FAIR Gateway Settings](/screenshots/screenshot-5.png?raw=true)

== Remove plugin ==

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


== Supporters ==

Previously contributed to "Bitcoin blockchains payment gateways for woocommerce (BCH, BSV)"

* Idan-Neeman: https://github.com/Idan-Neeman
* sanchaz: https://sanchaz.net
* mboyd1: https://github.com/mboyd1
* Yifu Guo: http://bitsyn.com/
* Bitcoin Grants: http://bitcoingrant.org/
* Chris Savery: https://github.com/bkkcoins/misc
* lowcostego: http://wordpress.org/support/profile/lowcostego
* WebDesZ: http://wordpress.org/support/profile/webdesz
* ninjastik: http://wordpress.org/support/profile/ninjastik
* timbowhite: https://github.com/timbowhite
* devlinfox: http://wordpress.org/support/profile/devlinfox


== Frequently Asked Questions ==

Are my keys kept on my server? No! No Private keys are ever stored on your server, the plugin does not need to know about them. So if you do ever get hacked there are no keys to steal, your cryptocurrency is safe. The plugin makes use of HD Master Public Key.

Does this use any payment processor? No! This uses your Electrum/ElectrumFair Master Public Keys, the crytocurrency goes straight from your customer's wallet to your wallet.

Are there any fees? No! There is no middle man, no fees are ever taken at any point, nor will they ever be.

Can I contribute? Sure! Go ahead and make a pull request. Clearly explain what the change does, what's the intended result and why it's required and if it changes any existing behaviour.

== Changelog ==  
= 1.02 =
* Fixed redirect for paid order

= 1.01 =
* Fix bugs & Adding php 7.4 support

= 1.00 =
* Initial Release

== Roadmap ==


== Upgrade Notice ==

None
