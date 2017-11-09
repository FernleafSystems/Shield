<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_License', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_License extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		add_filter( $this->getPremiumLicenseFilterName(), array( $this, 'hasValidWorkingLicense' ) );
	}

	protected function doExtraSubmitProcessing() {
		// attempt activation on every save.
		if ( $this->isLicenseKeyValidFormat() ) {
			$this->validateLicense();
		}
		else {
			$this->deactivate( 'Invalid License Key Format' );
		}
	}

	/**
	 */
	public function displayFeatureConfigPage() {
		$oWp = $this->loadWp();

		$this->validateLicense(); // just to ensure we have the latest going in.

		$aData = array(
			'strings'           => array(
				'product_name'    => sprintf( 'Product Name' ),
				'license_active'  => sprintf( 'License Active State' ),
				'license_status'  => sprintf( 'License Official Status' ),
				'license_key'     => sprintf( 'License Key' ),
				'license_expires' => sprintf( 'License Expires' ),
				'license_email'   => sprintf( 'License Owner Email' ),
			),
			'vars'              => array(
				'product_name'    => $this->getLicenseItemName(),
				'license_active'  => $this->hasValidWorkingLicense() ? 'Active' : 'Not Active',
				'license_status'  => $this->getOfficialLicenseStatus(),
				'license_key'     => $this->getLicenseKey(),
				'license_expires' => date( $oWp->getDateFormat().' '.$oWp->getTimeFormat(), $oWp->getTimeAsGmtOffset( $this->getLicenseExpiresAt() ) ),
				'license_email'   => $this->getOfficialLicenseRegisteredEmail(),
			),
			'aHrefs'            => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDefinition( 'landing_page_url' ),
			),
			'bShowStateSummary' => false,
			'flags'             => array(
				'wrap_page_content' => false,
			),
		);
		$this->display( $aData );
	}

	/**
	 * @param string $sDeactivatedReason
	 */
	public function deactivate( $sDeactivatedReason = '' ) {
		if ( $this->isLicenseActive() ) {
			$this->setOpt( 'license_deactivated_at', $this->loadDataProcessor()->time() )
				 ->setOpt( 'license_expires_at', 0 )
				 ->setOfficialLicenseRegisteredEmail( '' );
			if ( $this->isOfficialLicenseStatusValid() ) {
				$this->setOpt( 'license_official_status', 'cleared' );
			}
			if ( !empty( $sDeactivatedReason ) ) {
				$this->setOpt( 'license_deactivated_reason', $sDeactivatedReason );
			}
		}
	}

	protected function validateLicense() {
		$nRequestTime = $this->loadDataProcessor()->time();

		$bLicenseIsValid = $this->isOfficialLicenseStatusValid() && !$this->isLastCheckExpired();
		$sErrorMessage = '';

		$oLicense = $this->activateOfficialLicense();

		if ( is_null( $oLicense ) ) {
			// error for license lookup
			$sErrorMessage = 'Could not lookup license with license server.';
		}
		else if ( !$oLicense->isReady() ) {
			$sErrorMessage = 'Unexpected response from license server.';
		}
		else if ( $oLicense->isReady() ) {

			$bLicenseWasValid = $this->isLicenseActive();

			$this->setOpt( 'license_expires_at', $oLicense->getExpiresAt() )
				 ->setOpt( 'license_last_checked_at', $nRequestTime )
				 ->setOpt( 'license_official_status', $oLicense->getLicenseStatus() )
				 ->setOfficialLicenseRegisteredEmail( $oLicense->getCustomerEmail() );

			$bLicenseIsValid = $this->isOfficialLicenseStatusValid() && !$this->isLicenseExpired();

			$bNewlyActivated = !$bLicenseWasValid && $bLicenseIsValid;
			$bNewlyDeactivated = $bLicenseWasValid && !$bLicenseIsValid;

			if ( $bNewlyActivated || !$this->isLicenseActive() ) {
				$this->setOpt( 'license_activated_at', $nRequestTime );
			}
			else if ( $bNewlyDeactivated ) {
				$sErrorMessage = sprintf( 'Official license check returned as %s.', $oLicense->getLicenseStatus() );
			}
		}

		if ( !$bLicenseIsValid ) {
			$this->deactivate( $sErrorMessage );
		}
	}

	/**
	 * @return ICWP_EDD_LicenseVO
	 */
	protected function activateOfficialLicense() {
		return $this->loadEdd()
					->activateLicense(
						$this->getLicenseStoreUrl(),
						$this->getLicenseKey(),
						$this->getLicenseItemId()
					);
	}

	/**
	 * @return int
	 */
	protected function getLicenseActivatedAt() {
		return $this->getOpt( 'license_activated_at' );
	}

	/**
	 * @return int
	 */
	protected function getLicenseDeactivatedAt() {
		return $this->getOpt( 'license_deactivated_at' );
	}

	/**
	 * @return string
	 */
	public function getLicenseKey() {
		return $this->getOpt( 'license_key' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemId() {
		return $this->getDefinition( 'license_item_id' );
	}

	/**
	 * @return string
	 */
	public function getLicenseItemName() {
		return $this->getDefinition( 'license_item_name' );
	}

	/**
	 * @return string
	 */
	public function getLicenseStoreUrl() {
		return $this->getDefinition( 'license_store_url' );
	}

	/**
	 * @return int
	 */
	protected function getLicenseExpiresAt() {
		return $this->getOpt( 'license_expires_at' );
	}

	/**
	 * @return int
	 */
	protected function getLicenseLastCheckedAt() {
		return $this->getOpt( 'license_last_checked_at' );
	}

	/**
	 * @return string
	 */
	protected function getOfficialLicenseStatus() {
		return $this->getOpt( 'license_official_status' );
	}

	/**
	 * @return string
	 */
	protected function getOfficialLicenseRegisteredEmail() {
		return $this->getOpt( 'license_registered_email' );
	}

	/**
	 * @return bool
	 */
	public function isOfficialLicenseStatusValid() {
		return ( $this->getOfficialLicenseStatus() == 'valid' );
	}

	/**
	 * @return bool
	 */
	public function isLicenseActive() {
		return ( $this->getLicenseActivatedAt() > 0 )
			   && ( $this->getLicenseDeactivatedAt() < $this->getLicenseActivatedAt() );
	}

	/**
	 * @return bool
	 */
	public function isLicenseKeyValidFormat() {
		return $this->verifyLicenseKeyFormat( $this->getLicenseKey() );
	}

	/**
	 * @return bool
	 */
	protected function isLicenseExpired() {
		return ( $this->getLicenseExpiresAt() < $this->loadDataProcessor()->GetRequestTime() );
	}

	/**
	 * @return bool
	 */
	protected function isLastCheckExpired() {
		return ( $this->loadDataProcessor()->time() - $this->getLicenseLastCheckedAt()
				 > $this->getDefinition( 'license_lack_check_expire_days' )*DAY_IN_SECONDS );
	}

	/**
	 * We test various data points:
	 * 1) the key is valid format
	 * 2) the official license status is 'valid'
	 * 3) the license is marked as "active"
	 * 4) the license hasn't expired
	 * 5) the time since the last check hasn't expired
	 * @return bool
	 */
	public function hasValidWorkingLicense() {
		return $this->isLicenseKeyValidFormat() && $this->isOfficialLicenseStatusValid()
			   && $this->isLicenseActive() && !$this->isLicenseExpired();
	}

	/**
	 * @param string $sEmail
	 * @return string
	 */
	protected function setOfficialLicenseRegisteredEmail( $sEmail ) {
		return $this->setOpt( 'license_registered_email', $sEmail );
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function verifyLicenseKeyFormat( $sKey ) {
		$bValid = !empty( $sKey ) && is_string( $sKey )
				  && ( strlen( $sKey ) == $this->getDefinition( 'license_key_length' ) );

		if ( $bValid ) {
			switch ( $this->getDefinition( 'license_key_type' ) ) {
				case 'alphanumeric':
				default:
					$bValid = ( preg_match( '#[^a-z0-9]#i', $sKey ) === 0 );
					break;
			}
		}
		return $bValid;
	}

	/**
	 * @return boolean
	 */
	public function getIfShowFeatureMenuItem() {
		return parent::getIfShowFeatureMenuItem() && self::getController()->isPremiumExtensionsEnabled();
	}

	/**
	 */
	protected function doPrePluginOptionsSave() {
		$sLicKey = $this->getLicenseKey();
		if ( strlen( $sLicKey ) > 0 ) {
			switch ( $this->getDefinition( 'license_key_type' ) ) {
				case 'alphanumeric':
				default:
					$this->setOpt( 'license_key', preg_replace( '#[^a-z0-9]#i', '', $sLicKey ) );
					break;
			}
		}
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		// Automatically validate active licenses if they've expired.
		if ( $this->hasValidWorkingLicense() && $this->isLastCheckExpired() ) {
			$this->validateLicense();
		}
		parent::action_doFeatureShutdown();
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_license_options' :
				$sTitle = _wpsf__( 'License Options' );
				$sTitleShort = _wpsf__( 'License Options' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Activate Shield Pro Extensions.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'TODO.' ) )
				);
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $aOptionsParams[ 'slug' ] ) );
		}

		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {
			case 'license_key' :
				$sName = _wpsf__( 'License Key' );
				$sSummary = _wpsf__( 'License Key' );
				$sDescription = _wpsf__( 'License Key' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}