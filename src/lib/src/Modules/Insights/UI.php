<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();
		$aSecNotices = $this->getNotices();

		$nNoticesCount = 0;
		foreach ( $aSecNotices as $aNoticeSection ) {
			$nNoticesCount += isset( $aNoticeSection[ 'count' ] ) ? $aNoticeSection[ 'count' ] : 0;
		}

		return [
			'vars'    => [
				'insight_stats'         => $this->getStats(),
				'overview_cards'        => ( new OverviewCards() )
					->setMod( $this->getMod() )
					->buildForShuffle(),
			],
			'ajax'    => [
				'render_chart_post' => $con->getModule_Events()->getAjaxActionData( 'render_chart_post', true ),
			],
			'hrefs'   => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
			],
			'flags'   => [
				'show_ads'              => false,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
				'has_notices'           => count( $aSecNotices ) > 0,
			],
			'strings' => [
				'title_security_notices'    => __( 'Security Notices', 'wp-simple-firewall' ),
				'subtitle_security_notices' => __( 'Potential security issues on your site right now', 'wp-simple-firewall' ),
				'configuration_summary'     => __( 'Plugin Configuration Summary', 'wp-simple-firewall' ),
				'click_to_toggle'           => __( 'click to toggle', 'wp-simple-firewall' ),
				'go_to_options'             => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'key'                       => __( 'Key' ),
				'key_positive'              => __( 'Positive Security', 'wp-simple-firewall' ),
				'key_warning'               => __( 'Potential Warning', 'wp-simple-firewall' ),
				'key_danger'                => __( 'Potential Danger', 'wp-simple-firewall' ),
				'key_information'           => __( 'Information', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array[]
	 * @deprecated 10.0
	 */
	private function getNotices() :array {
		return [];
	}

	private function getNoticesSite() :array {
		$oSslService = new \FernleafSystems\Wordpress\Services\Utilities\Ssl();

		$aNotices = [
			'title'    => __( 'Site', 'wp-simple-firewall' ),
			'messages' => []
		];

		// SSL Expires
		$sHomeUrl = Services::WpGeneral()->getHomeUrl();
		$bHomeSsl = strpos( $sHomeUrl, 'https://' ) === 0;

		if ( $bHomeSsl && $oSslService->isEnvSupported() ) {

			try {
				// first verify SSL cert:
				$oSslService->getCertDetailsForDomain( $sHomeUrl );

				// If we didn't throw and exception, we got it.
				$nExpiresAt = $oSslService->getExpiresAt( $sHomeUrl );
				if ( $nExpiresAt > 0 ) {
					$nTimeLeft = ( $nExpiresAt - Services::Request()->ts() );
					$bExpired = $nTimeLeft < 0;
					$nDaysLeft = $bExpired ? 0 : (int)round( $nTimeLeft/DAY_IN_SECONDS, 0, PHP_ROUND_HALF_DOWN );

					if ( $nDaysLeft < 15 ) {

						if ( $bExpired ) {
							$sMess = __( 'SSL certificate for this site has expired.', 'wp-simple-firewall' );
						}
						else {
							$sMess = sprintf( __( 'SSL certificate will expire soon (in %s days)', 'wp-simple-firewall' ), $nDaysLeft );
						}

						$aMessage = [
							'title'   => 'SSL Cert Expiration',
							'message' => $sMess,
							'href'    => '',
							'rec'     => __( 'Check or renew your SSL certificate.', 'wp-simple-firewall' )
						];
					}
				}
			}
			catch ( \Exception $oE ) {
				$aMessage = [
					'title'   => 'SSL Cert Expiration',
					'message' => 'Failed to retrieve a valid SSL certificate.',
					'href'    => ''
				];
			}

			if ( !empty( $aMessage ) ) {
				$aNotices[ 'messages' ][ 'ssl_cert' ] = $aMessage;
			}
		}

		{ // db password strength
			$nStrength = ( new \ZxcvbnPhp\Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ];
			if ( $nStrength < 4 ) {
				$aNotices[ 'messages' ][ 'db_strength' ] = [
					'title'   => 'DB Password',
					'message' => __( 'DB Password appears to be weak.', 'wp-simple-firewall' ),
					'href'    => '',
					'rec'     => __( 'The database password should be strong.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		return $aNotices;
	}

	private function getRecentEvents() :array {
		$con = $this->getCon();

		$aTheStats = array_filter(
			$con->loadEventsService()->getEvents(),
			function ( $aEvt ) {
				return isset( $aEvt[ 'recent' ] ) && $aEvt[ 'recent' ];
			}
		);

		/** @var Strings $oStrs */
		$oStrs = $this->getMod()->getStrings();
		$aNames = $oStrs->getInsightStatNames();

		/** @var Events\Select $oSel */
		$oSel = $con->getModule_Events()
					->getDbHandler_Events()
					->getQuerySelector();

		$aRecentStats = array_intersect_key(
			array_map(
				function ( $oEntryVO ) use ( $aNames ) {
					/** @var Events\EntryVO $oEntryVO */
					return [
						'name' => isset( $aNames[ $oEntryVO->event ] ) ? $aNames[ $oEntryVO->event ] : '*** '.$oEntryVO->event,
						'val'  => Services::WpGeneral()->getTimeStringForDisplay( $oEntryVO->created_at )
					];
				},
				$oSel->getLatestForAllEvents()
			),
			$aTheStats
		);

		$sNotYetRecorded = __( 'Not yet recorded', 'wp-simple-firewall' );
		foreach ( array_keys( $aTheStats ) as $sStatKey ) {
			if ( !isset( $aRecentStats[ $sStatKey ] ) ) {
				$aRecentStats[ $sStatKey ] = [
					'name' => isset( $aNames[ $sStatKey ] ) ? $aNames[ $sStatKey ] : '*** '.$sStatKey,
					'val'  => $sNotYetRecorded
				];
			}
		}

		return $aRecentStats;
	}

	/**
	 * @return array[]
	 */
	protected function getStats() {
		$oCon = $this->getCon();
		/** @var Events\Select $oSelEvents */
		$oSelEvents = $oCon->getModule_Events()
						   ->getDbHandler_Events()
						   ->getQuerySelector();

		/** @var IPs\Select $oSelectIp */
		$oSelectIp = $oCon->getModule_IPs()
						  ->getDbHandler_IPs()
						  ->getQuerySelector();

		$aStatsData = [
			'login'          => [
				'id'        => 'login_block',
				'title'     => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'login_block' ) ),
				'tooltip_p' => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			//			'firewall'       => [
			//				'id'      => 'firewall_block',
			//				'title'   => __( 'Firewall Blocks', 'wp-simple-firewall' ),
			//				'val'     => $oSelEvents->clearWheres()->sumEvent( 'firewall_block' ),
			//				'tooltip' => __( 'Total requests blocked by firewall rules.', 'wp-simple-firewall' )
			//			],
			'bot_blocks'     => [
				'id'        => 'bot_blocks',
				'title'     => __( 'Bot Detection', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEventsLike( 'bottrack_' ) ),
				'tooltip_p' => __( 'Total requests identified as bots.', 'wp-simple-firewall' ),
			],
			'comments'       => [
				'id'        => 'comment_block',
				'title'     => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvents( [
						'spam_block_bot',
						'spam_block_human',
						'spam_block_recaptcha'
					] ) ),
				'tooltip_p' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' ),
			],
			'transgressions' => [
				'id'        => 'ip_offense',
				'title'     => __( 'Offenses', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'ip_offense' ) ),
				'tooltip_p' => __( 'Total offenses against the site.', 'wp-simple-firewall' ),
			],
			'conn_kills'     => [
				'id'        => 'conn_kill',
				'title'     => __( 'Connection Killed', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'conn_kill' ) ),
				'tooltip_p' => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' ),
			],
			'ip_blocked'     => [
				'id'        => 'ip_blocked',
				'title'     => __( 'IP Blocked', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Now' ),
					$oSelectIp->filterByBlacklist()->count()
				),
				'tooltip_p' => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $aStatsData as $sKey => $sStatData ) {
			$sSub = sprintf( __( 'previous %s %s', 'wp-simple-firewall' ), 7, __( 'days', 'wp-simple-firewall' ) );
			$aStatsData[ $sKey ][ 'title_sub' ] = $sSub;
			$aStatsData[ $sKey ][ 'tooltip_chart' ] = sprintf( '%s: %s.', __( 'Stats', 'wp-simple-firewall' ), $sSub );
		}

		return $aStatsData;
	}
}