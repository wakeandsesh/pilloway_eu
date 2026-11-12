<?php
// Run only from CLI, once a database backup has been taken.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST']='127.0.0.1:8098';
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
foreach (array('pilloway-cookie-consent/pilloway-cookie-consent.php','pilloway-meta-pixel/pilloway-meta-pixel.php','pilloway-google-shopping-feed/pilloway-google-shopping-feed.php','catcode-card-payments-with-wayforpay-for-woocommerce/catcode-card-payments-with-wayforpay-for-woocommerce.php') as $plugin) {
 $result=activate_plugin($plugin); if(is_wp_error($result)) throw new Exception($result->get_error_message());
}
// Configure sandbox only on the local environment, never invent live merchant details.
if('local'===wp_get_environment_type()) {
 $settings=get_option('woocommerce_wayforpay_settings',array());$settings['enabled']='yes';$settings['testmode']='yes';$settings['title']='Card payment (WayForPay)';update_option('woocommerce_wayforpay_settings',$settings);
}
update_option('gla_target_audience',array('location'=>'selected','countries'=>array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE')));
$pll=get_option('polylang');$pll['domains']['de']='';update_option('polylang',$pll);delete_transient('pll_languages_list');
update_option('wp_page_for_privacy_policy',4612);
update_option('woocommerce_allowed_countries','all');
update_option('admin_email','pilloway.info@gmail.com');
flush_rewrite_rules(false);
echo 'EU plugins and settings configured.'.PHP_EOL;
