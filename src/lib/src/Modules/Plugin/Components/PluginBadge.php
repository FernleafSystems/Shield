<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PluginBadge
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components
 */
class PluginBadge {

	use Modules\ModConsumer;

	public function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$bDisplay = $opts->isOpt( 'display_plugin_badge', 'Y' )
					&& ( Services::Request()->cookie( $this->getCookieIdBadgeState() ) != 'closed' );
		if ( $bDisplay ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'wp_footer', [ $this, 'printPluginBadge' ], 100 );
			add_action( 'login_footer', [ $this, 'printPluginBadge' ], 100 );
		}

		add_action( 'widgets_init', [ $this, 'addPluginBadgeWidget' ] );

		add_shortcode( 'SHIELD_BADGE', function () {
			$this->render( false );
		} );
	}

	/**
	 * https://wordpress.org/support/topic/fatal-errors-after-update-to-7-0-2/#post-11169820
	 */
	public function addPluginBadgeWidget() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		if ( !empty( $oMod ) && Services::WpGeneral()->getWordpressIsAtLeastVersion( '4.6.0' )
			 && !class_exists( 'Tribe_WP_Widget_Factory' ) ) {
			register_widget( new BadgeWidget( $oMod ) );
		}
	}

	private function getCookieIdBadgeState() :string {
		return $this->getCon()->prefix( 'badgeState' );
	}

	public function includeJquery() {
		wp_enqueue_script( 'jquery', null, [], false, true );
	}

	public function printPluginBadge() {
		echo $this->render( true );
	}

	/**
	 * @param bool $isFloating
	 * @return string
	 */
	public function render( $isFloating = false ) {
		$con = $this->getCon();
		/** @var Modules\SecurityAdmin\Options $secAdminOpts */
		$secAdminOpts = $con->getModule_SecAdmin()->getOptions();

		if ( $secAdminOpts->isEnabledWhitelabel() && $secAdminOpts->isReplacePluginBadge() ) {
			$badgeUrl = $secAdminOpts->getOpt( 'wl_homeurl' );
			$name = $secAdminOpts->getOpt( 'wl_namemenu' );
			$logo = $secAdminOpts->getOpt( 'wl_dashboardlogourl' );
		}
		else {
			$badgeUrl = 'https://shsec.io/wpsecurityfirewall';
			$name = $con->getHumanName();
			$logo = $con->getPluginUrl_Image( 'shield/shield-security-logo-colour-32px.png' );

			$lic = $con->getModule_License()
					   ->getLicenseHandler()
					   ->getLicense();
			if ( !empty( $lic->aff_ref ) ) {
				$badgeUrl = add_query_arg( [ 'ref' => $lic->aff_ref ], $badgeUrl );
			}
		}

		$badgeAttrs = [
			'name'         => $name,
			'url'          => $badgeUrl,
			'logo'         => $logo,
			'protected_by' => apply_filters( 'icwp_shield_plugin_badge_text',
				sprintf( __( 'This Site Is Protected By %s', 'wp-simple-firewall' ),
					'<br/><span class="plugin-badge-name">'.$name.'</span>' )
			),
			'custom_css'   => '',
		];
		if ( $con->isPremiumActive() ) {
			$badgeAttrs = apply_filters( 'icwp_shield_plugin_badge_attributes', $badgeAttrs, $isFloating );
		}

		$data = [
			'ajax'    => [
				'plugin_badge_close' => $this->getMod()->getAjaxActionData( 'plugin_badge_close', true ),
			],
			'content' => [
				'custom_css' => esc_js( $badgeAttrs[ 'custom_css' ] ),
			],
			'flags'   => [
				'nofollow'    => apply_filters( 'icwp_shield_badge_relnofollow', false ),
				'is_floating' => $isFloating
			],
			'hrefs'   => [
				'badge' => $badgeAttrs[ 'url' ],
				'logo'  => $badgeAttrs[ 'logo' ],
			],
			'strings' => [
				'protected' => $badgeAttrs[ 'protected_by' ],
				'name'      => $badgeAttrs[ 'name' ],
			],
		];

		try {
			$render = $this->getMod()->renderTemplate( 'snippets/plugin_badge_widget', $data, true );
		}
		catch ( \Exception $oE ) {
			$render = 'Could not generate badge: '.$oE->getMessage();
		}
		return $render;
	}

	public function setBadgeStateClosed() :bool {
		return (bool)Services::Response()
							 ->cookieSet(
								 $this->getCookieIdBadgeState(),
								 'closed',
								 DAY_IN_SECONDS
							 );
	}

	public function setIsDisplayPluginBadge( bool $isDisplay ) {
		$this->getOptions()->setOpt( 'display_plugin_badge', $isDisplay ? 'Y' : 'N' );
	}
}