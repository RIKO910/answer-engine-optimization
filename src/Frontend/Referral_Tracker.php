<?php
/**
 * Tiny, cookie-free beacon that records visits arriving from AI assistants.
 *
 * Runs in the browser so it also works on cached pages. It only sends a request
 * when the referrer or utm_source is a known AI assistant.
 *
 * @package AEO
 */

namespace AEO\Frontend;

use AEO\Core\Settings;
use AEO\Services\Traffic_Log;

defined( 'ABSPATH' ) || exit;

/**
 * Referral tracker.
 */
class Referral_Tracker {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		if ( Settings::get( 'traffic_logging', 1 ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'print_script' ), 99 );
		}
	}

	/**
	 * Print the inline beacon.
	 *
	 * @return void
	 */
	public static function print_script() {
		if ( is_admin() || is_user_logged_in() || is_feed() || is_robots() ) {
			return;
		}
		$domains = array_map( 'preg_quote', array_keys( Traffic_Log::REFERRERS ) );
		$pattern = '(^|\\.)(' . implode( '|', $domains ) . ')$';
		$config  = array(
			'u' => rest_url( 'aeo-genius/v1/track' ),
			'p' => $pattern,
		);
		?>
<script id="aeo-ai-referral">(function(c){try{var r=document.referrer||'',q=new URLSearchParams(location.search).get('utm_source')||'',re=new RegExp(c.p,'i'),h='';try{h=r?new URL(r).hostname.replace(/^www\./,''):''}catch(e){}var hit=(h&&re.test(h))||(h==='bing.com'&&/\/chat/.test(r))||(q&&re.test(q.replace(/^www\./,'')));if(!hit)return;var k='aeo_ref'+location.pathname;if(sessionStorage.getItem(k))return;sessionStorage.setItem(k,'1');var b=JSON.stringify({ref:r,utm:q,path:location.pathname});if(navigator.sendBeacon){navigator.sendBeacon(c.u,new Blob([b],{type:'application/json'}))}else{fetch(c.u,{method:'POST',headers:{'Content-Type':'application/json'},body:b,keepalive:true})}}catch(e){}})(<?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ); ?>);</script>
		<?php
	}
}
