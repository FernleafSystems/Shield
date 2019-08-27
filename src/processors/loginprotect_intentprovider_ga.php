<?php

use FernleafSystems\Wordpress\Services\Services;
use Dolondro\GoogleAuthenticator;

class ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * @var GoogleAuthenticator\Secret
	 */
	private $oWorkingSecret;

	/**
	 */
	public function run() {
		parent::run();
		if ( $this->getCon()->getShieldAction() == 'garemovalconfirm' ) {
			add_action( 'wp_loaded', [ $this, 'validateUserGaRemovalLink' ], 10 );
		}
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oCon = $this->getCon();

		$bValidatedProfile = $this->hasValidatedProfile( $oUser );

		if ( !$bValidatedProfile ) {
			$this->resetSecret( $oUser );
		}

		$aData = [
			'has_validated_profile'            => $bValidatedProfile,
			'user_google_authenticator_secret' => $this->getSecret( $oUser ),
			'is_my_user_profile'               => ( $oUser->ID == Services::WpUsers()->getCurrentWpUserId() ),
			'i_am_valid_admin'                 => $oCon->isPluginAdmin(),
			'user_to_edit_is_admin'            => Services::WpUsers()->isUserAdmin( $oUser ),
			'strings'                          => [
				'description_otp_code'     => __( 'Provide the current code generated by your Google Authenticator app.', 'wp-simple-firewall' ),
				'description_otp_code_ext' => __( 'To reset this QR Code enter fake data here.', 'wp-simple-firewall' ),
				'description_chart_url'    => __( 'Use your Google Authenticator app to scan this QR code and enter the one time password below.', 'wp-simple-firewall' ),
				'description_ga_secret'    => __( 'If you have a problem with scanning the QR code enter this code manually into the app.', 'wp-simple-firewall' ),
				'desc_remove'              => __( 'Check the box to remove Google Authenticator login authentication.', 'wp-simple-firewall' ),
				'label_check_to_remove'    => sprintf( __( 'Remove %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
				'label_enter_code'         => __( 'Google Authenticator Code', 'wp-simple-firewall' ),
				'label_ga_secret'          => __( 'Manual Code', 'wp-simple-firewall' ),
				'label_scan_qr_code'       => __( 'Scan This QR Code', 'wp-simple-firewall' ),
				'title'                    => __( 'Google Authenticator', 'wp-simple-firewall' ),
				'cant_add_other_user'      => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Google Authenticator' ),
				'cant_remove_admins'       => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
				'provided_by'              => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $oCon->getHumanName() ),
				'remove_more_info'         => sprintf( __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ) )
			],
			'data'                             => [
				'otp_field_name' => $this->getLoginFormParameter()
			]
		];

		if ( !$bValidatedProfile ) {
			$aData[ 'chart_url' ] = $this->getGaRegisterChartUrl( $oUser );
		}

		echo $this->getMod()->renderTemplate( 'snippets/user_profile_googleauthenticator.php', $aData );
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	public function getGaRegisterChartUrl( $oUser ) {
		if ( empty( $oUser ) ) {
			$sUrl = '';
		}
		else {
			$sUrl = ( new GoogleAuthenticator\QrImageGenerator\GoogleQrImageGenerator () )
				->generateUri(
					$this->getGaSecret( $oUser )
				);
		}
		return $sUrl;
	}

	/**
	 * The only thing we can do is REMOVE Google Authenticator from an account that is not our own
	 * But, only admins can do this.  If Security Admin feature is enabled, then only they can do it.
	 * @param int $nSavingUserId
	 */
	public function handleEditOtherUserProfileSubmit( $nSavingUserId ) {

		// Can only edit other users if you're admin/security-admin
		if ( $this->getCon()->isPluginAdmin() ) {
			$oWpUsers = Services::WpUsers();
			$oSavingUser = $oWpUsers->getUserById( $nSavingUserId );

			$sShieldTurnOff = Services::Request()->post( 'shield_turn_off_google_authenticator' );
			if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {

				$bPermissionToRemoveGa = true;
				// if the current user has Google Authenticator on THEIR account, process their OTP.
				$oCurrentUser = $oWpUsers->getCurrentWpUser();
				if ( $this->hasValidatedProfile( $oCurrentUser ) ) {
					$bPermissionToRemoveGa = $this->processOtp( $oCurrentUser, $this->fetchCodeFromRequest() );
				}

				if ( $bPermissionToRemoveGa ) {
					$this->processRemovalFromAccount( $oSavingUser );
					$sMsg = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
				}
				else {
					$sMsg = __( 'Google Authenticator could not be removed from the account - ensure your code is correct.', 'wp-simple-firewall' );
				}
				$this->getMod()->setFlashAdminNotice( $sMsg, $bPermissionToRemoveGa );
			}
		}
		else {
			// DO NOTHING EVER
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	protected function processRemovalFromAccount( $oUser ) {
		$this->setProfileValidated( $oUser, false )
			 ->resetSecret( $oUser );
		return $this;
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {
		$oWpUsers = Services::WpUsers();

		$oSavingUser = $oWpUsers->getUserById( $nSavingUserId );

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtp = $this->fetchCodeFromRequest();
		$bValidOtp = $this->processOtp( $oSavingUser, $sOtp );

		$sMessageOtpInvalid = __( 'One Time Password (OTP) was not valid.', 'wp-simple-firewall' ).' '.__( 'Please try again.', 'wp-simple-firewall' );

		$sShieldTurnOff = Services::Request()->post( 'shield_turn_off_google_authenticator' );
		if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {

			$bError = false;
			if ( $bValidOtp ) {
				$this->processRemovalFromAccount( $oSavingUser );
				$sFlash = __( 'Google Authenticator was successfully removed from the account.', 'wp-simple-firewall' );
			}
			else if ( empty( $sOtp ) ) {

				if ( $this->sendEmailConfirmationGaRemoval( $oSavingUser ) ) {
					$sFlash = __( 'An email has been sent to you in order to confirm Google Authenticator removal', 'wp-simple-firewall' );
				}
				else {
					$bError = true;
					$sFlash = __( 'We tried to send an email for you to confirm Google Authenticator removal but it failed.', 'wp-simple-firewall' );
				}
			}
			else {
				$bError = true;
				$sFlash = $sMessageOtpInvalid;
			}
			$this->getMod()->setFlashAdminNotice( $sFlash, $bError );
			return;
		}

		// At this stage, if the OTP was empty, then we have no further processing to do.
		if ( empty( $sOtp ) ) {
			return;
		}

		// We're trying to validate our OTP to activate our GA
		if ( !$this->hasValidatedProfile( $oSavingUser ) ) {

			if ( $bValidOtp ) {
				$this->setProfileValidated( $oSavingUser );
				$sFlash = sprintf(
					__( '%s was successfully added to your account.', 'wp-simple-firewall' ),
					__( 'Google Authenticator', 'wp-simple-firewall' )
				);
			}
			else {
				$this->resetSecret( $oSavingUser );
				$sFlash = $sMessageOtpInvalid;
			}
			$this->getMod()->setFlashAdminNotice( $sFlash, !$bValidOtp );
		}
	}

	/**
	 * @param array $aFields
	 * @return array
	 */
	public function addLoginIntentField( $aFields ) {
		if ( $this->getCurrentUserHasValidatedProfile() ) {
			$aFields[] = [
				'name'        => $this->getLoginFormParameter(),
				'type'        => 'text',
				'value'       => '',
				'placeholder' => __( 'Please use your Google Authenticator App to retrieve your code.', 'wp-simple-firewall' ),
				'text'        => __( 'Google Authenticator Code', 'wp-simple-firewall' ),
				'help_link'   => 'https://icwp.io/wpsf42',
				'extras'      => [
					'onkeyup' => "this.value=this.value.replace(/[^\d]/g,'')"
				]
			];
		}
		return $aFields;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function sendEmailConfirmationGaRemoval( $oUser ) {
		$bSendSuccess = false;

		$aEmailContent = [];
		$aEmailContent[] = __( 'You have requested the removal of Google Authenticator from your WordPress account.', 'wp-simple-firewall' )
						   .__( 'Please click the link below to confirm.', 'wp-simple-firewall' );
		$aEmailContent[] = $this->generateGaRemovalConfirmationLink();

		$sRecipient = $oUser->get( 'user_email' );
		if ( Services::Data()->validEmail( $sRecipient ) ) {
			$sEmailSubject = __( 'Google Authenticator Removal Confirmation', 'wp-simple-firewall' );
			$bSendSuccess = $this->getEmailProcessor()
								 ->sendEmailWithWrap( $sRecipient, $sEmailSubject, $aEmailContent );
		}
		return $bSendSuccess;
	}

	/**
	 */
	public function validateUserGaRemovalLink() {
		// Must be already logged in for this link to work.
		$oWpCurrentUser = Services::WpUsers()->getCurrentWpUser();
		if ( empty( $oWpCurrentUser ) ) {
			return;
		}

		// Session IDs must be the same
		$sSessionId = Services::Request()->query( 'sessionid' );
		if ( empty( $sSessionId ) || ( $sSessionId !== $this->getCon()->getSessionId() ) ) {
			return;
		}

		$this->processRemovalFromAccount( $oWpCurrentUser );
		$this->getMod()
			 ->setFlashAdminNotice( __( 'Google Authenticator was successfully removed from this account.', 'wp-simple-firewall' ) );
		Services::Response()->redirectToAdmin();
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		return $this->validateGaCode( $oUser, $sOtpCode );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	public function validateGaCode( $oUser, $sOtpCode ) {
		$bValidOtp = false;
		if ( !empty( $sOtpCode ) && preg_match( '#^[0-9]{6}$#', $sOtpCode ) ) {
			try {
				$bValidOtp = ( new GoogleAuthenticator\GoogleAuthenticator() )
					->authenticate( $this->getSecret( $oUser ), $sOtpCode );
			}
			catch ( \Exception $oE ) {
			}
			catch ( \Psr\Cache\CacheException $oE ) {
			}
		}
		return $bValidOtp;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? 'googleauth_verified' : 'googleauth_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
					'method'     => 'Google Authenticator',
				]
			]
		);
	}

	/**
	 * @return string
	 */
	protected function generateGaRemovalConfirmationLink() {
		$aQueryArgs = [
			'shield_action' => 'garemovalconfirm',
			'sessionid'     => $this->getCon()->getSessionId()
		];
		return add_query_arg( $aQueryArgs, Services::WpGeneral()->getAdminUrl() );
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function genNewSecret( \WP_User $oUser ) {
		try {
			return $this->getGaSecret( $oUser )->getSecretKey();
		}
		catch ( \InvalidArgumentException $oE ) {
			return '';
		}
	}

	/**
	 * @param \WP_User $oUser
	 * @return GoogleAuthenticator\Secret
	 * @throws InvalidArgumentException
	 */
	private function getGaSecret( $oUser ) {
		if ( !isset( $this->oWorkingSecret ) ) {
			$this->oWorkingSecret = ( new GoogleAuthenticator\SecretFactory() )
				->create(
					sanitize_user( $oUser->user_login ),
					preg_replace( '#[^0-9a-z]#i', '', Services::WpGeneral()->getSiteName() )
				);
		}
		return $this->oWorkingSecret;
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function getSecret( WP_User $oUser ) {
		$sSec = parent::getSecret( $oUser );
		return empty( $sSec ) ? $this->resetSecret( $oUser ) : $sSec;
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Google_Authenticator;
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		return parent::isSecretValid( $sSecret ) && ( strlen( $sSecret ) == 16 );
	}
}