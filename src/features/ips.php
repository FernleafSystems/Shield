<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) && parent::isReadyToExecute();
	}

	protected function doExtraSubmitProcessing() {
		/** @var Shield\Modules\IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( !defined( strtoupper( $oOpts->getOpt( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$oOpts->resetOptToDefault( 'auto_expire' );
		}

		$nLimit = $oOpts->getOffenseLimit();
		if ( !is_int( $nLimit ) || $nLimit < 0 ) {
			$oOpts->resetOptToDefault( 'transgression_limit' );
		}
	}

	/**
	 * IP addresses that should never be put on the black list.
	 * @return string[]
	 */
	public function getReservedIps() {
		return [
			Services::Request()->getServerAddress(),
			$this->getCon()->getModule_Plugin()->getMyServerIp()
		];
	}

	/**
	 * @return array
	 */
	public function getAutoUnblockIps() {
		$aIps = $this->getOpt( 'autounblock_ips', [] );
		return is_array( $aIps ) ? $aIps : [];
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function updateIpRequestAutoUnblockTs( $sIp ) {
		$aExistingIps = $this->getAutoUnblockIps();
		$aExistingIps[ $sIp ] = Services::Request()->ts();
		return $this->setAutoUnblockIps( $aExistingIps );
	}

	/**
	 * @param array $aIps
	 * @return $this
	 */
	public function setAutoUnblockIps( $aIps ) {
		return $this->setOpt( 'autounblock_ips', $aIps );
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		/** @var Shield\Modules\IPs\Options $oOpts */
		$oOpts = $this->getOptions();

		switch ( $sSection ) {

			case 'section_auto_black_list':
				if ( !$oOpts->isEnabledAutoBlackList() ) {
					$aWarnings[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( "IP blocking is turned-off because the offenses limit is set to 0.", 'wp-simple-firewall' ) );
				}
				break;

			case 'section_behaviours':
			case 'section_probes':
			case 'section_logins':
				if ( !$oOpts->isEnabledAutoBlackList() ) {
					$aWarnings[] = __( "Since the offenses limit is set to 0, these options have no effect.", 'wp-simple-firewall' );
				}

				if ( $sSection == 'section_behaviours' && strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$aWarnings[] = __( "Your User Agent appears to be empty. We recommend not turning on this option.", 'wp-simple-firewall' );
				}
				break;
		}

		return $aWarnings;
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {

			case 'text_loginfailed':
				$sText = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.', 'wp-simple-firewall' )
				);
				break;

			case 'text_remainingtrans':
				$sText = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'You have %s remaining offenses(s) against this site and then your IP address will be completely blocked.', 'wp-simple-firewall' )
					.'<br/><strong>'.__( 'Seriously, stop repeating what you are doing or you will be locked out.', 'wp-simple-firewall' ).'</strong>'
				);
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		if ( !$this->getCon()->isPluginDeleting() ) {
			$this->addFilterIpsToWhiteList();
		}
		parent::onPluginShutdown(); //save
	}

	/**
	 */
	protected function addFilterIpsToWhiteList() {
		$aIps = [];
		$oSp = $this->loadServiceProviders();

		if ( function_exists( 'mwp_init' ) ) {
			foreach ( array_flip( $oSp->getIps_ManageWp() ) as $sIp => $n ) {
				$aIps[ $sIp ] = 'ManageWP';
			}
		}

		if ( class_exists( 'ICWP_Plugin' ) ) {
			foreach ( array_flip( $oSp->getIps_iControlWP( true ) ) as $sIp => $n ) {
				$aIps[ $sIp ] = 'iControlWP';
			}
		}

		$aIps = apply_filters( 'icwp_simple_firewall_whitelist_ips', $aIps );

		if ( !empty( $aIps ) && is_array( $aIps ) ) {
			/** @var ICWP_WPSF_Processor_Ips $oPro */
			$oPro = $this->getProcessor();

			$aWhiteIps = $oPro->getWhitelistIps();
			foreach ( $aIps as $sIP => $sLabel ) {
				if ( !in_array( $sIP, $aWhiteIps ) ) {
					$oPro->addIpToWhiteList( $sIP, $sLabel );
				}
			}
		}
	}

	protected function ensureFeatureEnabled() {
		// we prevent disabling of this feature if the white list isn't empty
		if ( !$this->isModuleEnabled() ) {
			/** @var ICWP_WPSF_Processor_Ips $oProcessor */
			$oProcessor = $this->getProcessor();
			if ( count( $oProcessor->getWhitelistIpsData() ) > 0 ) {
				$this->setIsMainFeatureEnabled( true );
				$this->setFlashAdminNotice(
					sprintf( __( 'Sorry, the %s feature may not be disabled while there are IP addresses in the White List', 'wp-simple-firewall' ), $this->getMainFeatureName() )
				);
			}
		}
	}

	/**
	 * @return Shield\Databases\IPs\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\IPs\Handler();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'IPs';
	}

	/**
	 * @return string
	 * @deprecated 8.1
	 */
	public function getOptTransgressionLimit() {
		return $this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isAutoBlackListEnabled() {
		return ( $this->getOptTransgressionLimit() > 0 );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isEnabledTrack404() {
		return $this->isSelectOptionEnabled( 'track_404' );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isEnabledTrackFakeWebCrawler() {
		return $this->isSelectOptionEnabled( 'track_fakewebcrawler' );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isEnabledTrackLoginInvalid() {
		return $this->isSelectOptionEnabled( 'track_logininvalid' );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isEnabledTrackLoginFailed() {
		return $this->isSelectOptionEnabled( 'track_loginfailed' );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isEnabledTrackLinkCheese() {
		return $this->isSelectOptionEnabled( 'track_linkcheese' );
	}

	/**
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isEnabledTrackXmlRpc() {
		return $this->isSelectOptionEnabled( 'track_xmlrpc' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isTrackOptTransgression( $sOptionKey ) {
		return strpos( $this->getOpt( $sOptionKey ), 'transgression' ) !== false;
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isTrackOptDoubleTransgression( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'transgression-double' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 * @deprecated 8.1
	 */
	public function isTrackOptImmediateBlock( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'block' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 * @deprecated 8.1
	 */
	protected function isSelectOptionEnabled( $sOptionKey ) {
		$bOptPrem = $this->getOptions()->isOptPremium( $sOptionKey );
		return ( !$bOptPrem || $this->getCon()->isPremiumActive() ) && !$this->isOpt( $sOptionKey, 'disabled' );
	}

	/**
	 * @return int
	 * @deprecated 8.1
	 */
	public function getAutoExpireTime() {
		$sConstant = strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' );
		return defined( $sConstant ) ? constant( $sConstant ) : ( DAY_IN_SECONDS*30 );
	}
}