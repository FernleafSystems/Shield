<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_UserManagement_Sessions extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
		add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100, 1 );
	}

	/**
	 * Cron callback
	 */
	public function runDailyCron() {
		( new UserManagement\Lib\CleanExpired() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user ) {
		if ( !$user instanceof \WP_User ) {
			$user = Services::WpUsers()->getUserByUsername( $username );
		}
		$this->enforceSessionLimits( $user );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->enforceSessionLimits( Services::WpUsers()->getUserById( $nUserId ) );
	}

	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->checkCurrentSession();
		}
	}

	private function checkCurrentSession() {
		$con = $this->getCon();
		/** @var UserManagement\ModCon $mod */
		$mod = $this->getMod();
		try {
			if ( $mod->hasValidRequestIP() ) {
				$this->assessSession();
			}
		}
		catch ( \Exception $e ) {
			$event = $e->getMessage();
			$con->fireEvent( $event );
			$con->getModule_Sessions()
				->getSessionCon()
				->terminateCurrentSession();
			$WPU = Services::WpUsers();
			is_admin() ? $WPU->forceUserRelogin( [ 'shield-forcelogout' => $event ] ) : $WPU->logoutUser( true );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function assessSession() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();

		$sess = $this->getMod()->getSession();
		if ( empty( $sess ) ) {
			throw new \Exception( 'session_notfound' );
		}

		$nTime = Services::Request()->ts();

		if ( $oOpts->hasMaxSessionTimeout() && ( $nTime - $sess->logged_in_at > $oOpts->getMaxSessionTime() ) ) {
			throw new \Exception( 'session_expired' );
		}

		if ( $oOpts->hasSessionIdleTimeout() && ( $nTime - $sess->last_activity_at > $oOpts->getIdleTimeoutInterval() ) ) {
			throw new \Exception( 'session_idle' );
		}

		$oIP = Services::IP();
		if ( $oOpts->isLockToIp() && $oIP->getRequestIp() != $sess->ip ) {
			// We force-refresh the server IPs just to be sure.
			Services::IP()->getServerPublicIPs( true );
			if ( !$oIP->isLoopback() ) {
				throw new \Exception( 'session_iplock' );
			}
		}
		// TODO: 'session_browserlock';
	}

	/**
	 * @param int $nTimeout
	 * @return int
	 */
	public function setMaxAuthCookieExpiration( $nTimeout ) {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->hasMaxSessionTimeout() ? min( $nTimeout, $oOpts->getMaxSessionTime() ) : $nTimeout;
	}

	/**
	 * @param \WP_User $user
	 */
	protected function enforceSessionLimits( $user ) {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		$nSessionLimit = $opts->getOpt( 'session_username_concurrent_limit', 1 );
		if ( !$this->isLoginCaptured() && $nSessionLimit > 0 && $user instanceof WP_User ) {
			$this->setLoginCaptured();
			try {
				$this->getMod()
					 ->getDbHandler_Sessions()
					 ->getQueryDeleter()
					 ->addWhere( 'wp_username', $user->user_login )
					 ->deleteExcess( $nSessionLimit, 'last_activity_at', true );
			}
			catch ( \Exception $e ) {
			}
		}
	}

	/**
	 * @param \WP_Error $error
	 * @return \WP_Error
	 */
	public function addLoginMessage( $error ) {

		if ( !$error instanceof \WP_Error ) {
			$error = new \WP_Error();
		}

		$sForceLogout = Services::Request()->query( 'shield-forcelogout' );
		if ( $sForceLogout ) {

			switch ( $sForceLogout ) {
				case 'session_expired':
					$sMessage = __( 'Your session has expired.', 'wp-simple-firewall' );
					break;

				case 'session_idle':
					$sMessage = __( 'Your session was idle for too long.', 'wp-simple-firewall' );
					break;

				case 'session_iplock':
					$sMessage = __( 'Your session was locked to another IP Address.', 'wp-simple-firewall' );
					break;

				case 'session_notfound':
					$sMessage = sprintf(
						__( 'You do not currently have a %s user session.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					);
					break;

				case 'session_browserlock':
					$sMessage = __( 'Your browser appears to have changed for this session.', 'wp-simple-firewall' );
					break;

				case 'session_unverified':
				default:
					$sMessage = __( 'Your session was terminated.', 'wp-simple-firewall' );
					break;
			}

			$sMessage .= '<br />'.__( 'Please login again.', 'wp-simple-firewall' );
			$error->add( 'shield-forcelogout', $sMessage );
		}
		return $error;
	}
}