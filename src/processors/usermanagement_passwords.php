<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement_Passwords', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

/**
 * Referenced some of https://github.com/BenjaminNelan/PwnedPasswordChecker
 * Class ICWP_WPSF_Processor_UserManagement_Pwned
 */
class ICWP_WPSF_Processor_UserManagement_Passwords extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var bool
	 */
	private $bPasswordFailedChecks;

	public function run() {
		// Account Reg
		add_filter( 'registration_errors', array( $this, 'checkPassword' ), 100, 3 );
		// Profile Update
		add_action( 'user_profile_update_errors', array( $this, 'checkPassword' ), 100, 3 );
		// Reset
		add_action( 'validate_password_reset', array( $this, 'checkPassword' ), 100, 3 );
		// Login
		add_filter( 'authenticate', array( $this, 'checkLoginPassword' ), PHP_INT_MAX, 3 );
		add_action( 'wp_login', array( $this, 'onWpLogin' ) );

		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ) );

		$this->loadAutoload();
	}

	/**
	 * @param WP_User|WP_Error $oErrorUser
	 * @param string           $sUsername
	 * @param string           $sPassword
	 * @return WP_User|WP_Error
	 */
	public function checkLoginPassword( $oErrorUser, $sUsername, $sPassword ) {

		if ( $oErrorUser instanceof WP_User ) { // successful login.
			try {
				$this->applyPasswordChecks( $sPassword );
				$this->bPasswordFailedChecks = false;
			}
			catch ( Exception $oE ) {
				$this->bPasswordFailedChecks = true;
			}
		}

		return $oErrorUser;
	}

	/**
	 * @param string $sUsername
	 */
	public function onWpLogin( $sUsername ) {
		$oUser = $this->loadWpUsers()->getUserByUsername( $sUsername );
		if ( $oUser instanceof WP_User ) {
			$this->setPasswordFlags( $oUser );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	private function setPasswordFlags( $oUser ) {
		$oMeta = $this->getFeature()->getUserMeta( $oUser );

		$sCurrentPassHash = substr( sha1( $oUser->user_pass ), 0, 6 );
		if ( !isset( $oMeta->pass_hash ) || ( $oMeta->pass_hash != $sCurrentPassHash ) ) {
			$oMeta->pass_hash = $sCurrentPassHash;
			$oMeta->pass_started_at = $this->time();
		}

		$oMeta->pass_check_failed_at = (bool)$this->bPasswordFailedChecks ? $this->time() : 0;

		return $this;
	}

	public function onWpLoaded() {
		if ( !$this->loadDP()->isMethodPost() && $this->loadWpUsers()->isUserLoggedIn() ) {
			$this->processExpiredPassword();
		}
	}

	private function processExpiredPassword() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();
		$bExpired = false;
		$nExpireTimeout = $oFO->getPassExpireTimeout();
		$oMeta = $oFO->getCurrentUserMeta();
		if ( $nExpireTimeout > 0 ) {
			if ( !empty( $oMeta->pass_hash ) ) { // we can only do this if we've recorded a password
				$bExpired = ( $this->time() - $oMeta->pass_started_at ) > $nExpireTimeout;
			}
		}

		$bPassCheckFailed = false;
		if ( $oFO->isPassForceUpdateExisting() ) {
			$bPassCheckFailed = isset( $oMeta->pass_check_failed_at ) ? $oMeta->pass_check_failed_at > 0 : false;
		}

		// TODO Test this URL on wpms
		if ( $bExpired || $bPassCheckFailed ) {
			$this->loadAdminNoticesProcessor()
				 ->addFlashMessage( _wpsf__( "Your password doesn't currently meet requirements set by your security administrator." ) );
			$this->loadWp()
				 ->doRedirect(
					 self_admin_url( 'profile.php' ),
					 array(
						 $oFO->prefix( 'force-user-password' ) => '1'
					 )
				 );
		}
	}

	/**
	 * @param WP_Error $oErrors
	 * @return WP_Error
	 */
	public function checkPassword( $oErrors ) {
		$aExistingCodes = $oErrors->get_error_code();
		if ( empty( $aExistingCodes ) ) {
			$sPassword = $this->getLoginPassword();

			if ( !empty( $sPassword ) ) {
				try {
					$this->applyPasswordChecks( $sPassword );

					$oWpUser = $this->loadWpUsers();
					if ( $oWpUser->isUserLoggedIn() ) {
						$this->getCurrentUserMeta()->pass_check_failed_at = 0;
					}
				}
				catch ( Exception $oE ) {
					$sMessage = _wpsf__( 'Your security administrator has imposed requirements for password quality.' )
								.' '.$oE->getMessage();
					$oErrors->add( 'shield_password_policy', $sMessage );
				}
			}
		}

		return $oErrors;
	}

	/**
	 * @param string $sPassword
	 * @throws Exception
	 */
	protected function applyPasswordChecks( $sPassword ) {
		$this->getPasswordMeetsMinimumLength( $sPassword );
		$this->getPasswordMeetsMinimumStrength( $sPassword );
		$this->sendRequestToPwnedRange( $sPassword );
	}

	/**
	 * @param string $sPassword
	 * @return bool
	 * @throws Exception
	 */
	protected function getPasswordMeetsMinimumStrength( $sPassword ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();
		$nMin = $oFO->getPassMinStrength();

		$oStengther = new \ZxcvbnPhp\Zxcvbn();
		$aResults = $oStengther->passwordStrength( $sPassword );
		$nScore = $aResults[ 'score' ];

		if ( $nMin > 0 && $nScore < $nMin ) {
			throw new Exception( sprintf( "Password strength (%s) doesn't meet the minimum required (%s).",
				$oFO->getPassStrengthName( $nScore ), $oFO->getPassStrengthName( $nMin ) ) );
		}
		return true;
	}

	/**
	 * @param string $sPassword
	 * @return bool
	 * @throws Exception
	 */
	protected function getPasswordMeetsMinimumLength( $sPassword ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();
		$nMin = $oFO->getPassMinLength();
		$nLength = strlen( $sPassword );
		if ( $nMin > 0 && $nLength < $nMin ) {
			throw new Exception( sprintf( _wpsf__( 'Password length (%s) too short (min: %s characters)' ), $nLength, $nMin ) );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	protected function verifyApiAccess() {
		try {
			$this->sendRequestToPwnedRange( 'P@ssw0rd' );
		}
		catch ( Exception $oE ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $sPass
	 * @return bool
	 * @throws Exception
	 */
	protected function sendRequestToPwned( $sPass ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();
		$oConn = $oFO->getConn();

		$aResponse = $this->loadFS()->requestUrl(
			sprintf( '%s/%s', $oFO->getDef( 'pwned_api_url_password_single' ), hash( 'sha1', $sPass ) ),
			array(
				'headers' => array(
					'user-agent' => sprintf( '%s WP Plugin-v%s', $oConn->getHumanName(), $oConn->getVersion() )
				)
			),
			true
		);

		$sError = '';
		if ( is_wp_error( $aResponse ) ) {
			$sError = $aResponse->get_error_message();
		}
		else if ( empty( $aResponse ) ) {
			$sError = 'Response was empty';
		}
		else if ( is_array( $aResponse ) ) {
			if ( empty( $aResponse[ 'response' ][ 'code' ] ) ) {
				$sError = 'Unexpected Error: No response code available from the API';
			}
			else if ( $aResponse[ 'response' ][ 'code' ] == 404 ) {
				// means that the password isn't on the pwned list. It's acceptable.
			}
			else if ( empty( $aResponse[ 'body' ] ) ) {
				$sError = 'Unexpected Error: The response from the API was empty';
			}
			else {
				// password pwned
				$nCount = intval( $aResponse[ 'body' ] );
				if ( $nCount == 0 ) {
					$sError = 'Unexpected Error: The API response could not be properly parsed.';
				}
				else {
					$sError = _wpsf__( 'Please use a different password.' )
							  .' '._wpsf__( 'This password has already been pwned.' )
							  .' '.sprintf(
								  '(<a href="%s" target="_blank">%s</a>)',
								  'https://www.troyhunt.com/ive-just-launched-pwned-passwords-version-2/',
								  sprintf( _wpsf__( '%s times' ), $nCount )
							  );
				}
			}
		}

		if ( !empty( $sError ) ) {
			throw new Exception( $sError );
		}

		return true;
	}

	protected function sendRequestToPwnedRange( $sPass ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();
		$oConn = $oFO->getConn();

		$sPassHash = strtoupper( hash( 'sha1', $sPass ) );
		$sSubHash = substr( $sPassHash, 0, 5 );

		$aResponse = $this->loadFS()->requestUrl(
			sprintf( '%s/%s', $oFO->getDef( 'pwned_api_url_password_range' ), $sSubHash ),
			array(
				'headers' => array(
					'user-agent' => sprintf( '%s WP Plugin-v%s', $oConn->getHumanName(), $oConn->getVersion() )
				)
			),
			true
		);

		$sError = '';
		if ( is_wp_error( $aResponse ) ) {
			$sError = $aResponse->get_error_message();
		}
		else if ( empty( $aResponse ) ) {
			$sError = 'Response was empty';
		}
		else if ( is_array( $aResponse ) ) {
			if ( empty( $aResponse[ 'response' ][ 'code' ] ) ) {
				$sError = 'Unexpected Error: No response code available from the API';
			}
			else if ( $aResponse[ 'response' ][ 'code' ] != 200 ) {
				// means that the password isn't on the pwned list. It's acceptable.
				$sError = 'Unexpected Error: The response from the API was unexpected';
			}
			else if ( empty( $aResponse[ 'body' ] ) ) {
				$sError = 'Unexpected Error: The response from the API was empty';
			}
			else {
				$nCount = 0;
				foreach ( array_map( 'trim', explode( "\n", trim( $aResponse[ 'body' ] ) ) ) as $sRow ) {
					if ( $sSubHash.substr( strtoupper( $sRow ), 0, 35 ) == $sPassHash ) {
						$nCount = substr( $sRow, 36 ); // need to preg_replace to clean up funny characters.
						break;
					}
				}
				if ( $nCount > 0 ) {
					$sError = _wpsf__( 'Please use a different password.' )
							  .' '._wpsf__( 'This password has already been pwned.' )
							  .' '.sprintf(
								  '(<a href="%s" target="_blank">%s</a>)',
								  'https://www.troyhunt.com/ive-just-launched-pwned-passwords-version-2/',
								  sprintf( _wpsf__( '%s times' ), $nCount )
							  );
				}
			}
		}

		if ( !empty( $sError ) ) {
			throw new Exception( $sError );
		}

		return true;
	}

	/**
	 * @return string
	 */
	private function getLoginPassword() {
		return $this->loadDP()->post( 'pass1' );
	}
}