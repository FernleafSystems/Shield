<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Sessions extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var int
	 */
	const DAYS_TO_KEEP = 30;

	/**
	 * @var Session\EntryVO
	 */
	private $oCurrent;

	/**
	 * @param \ICWP_WPSF_Processor_Sessions $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Sessions $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'sessions_table_name' ) );
	}

	public function run() {
		if ( $this->isReadyToRun() ) {
			if ( !Services::WpUsers()->isProfilePage() ) { // only on logout
				add_action( 'clear_auth_cookie', function () {
					$this->terminateCurrentSession();
				}, 0 );
			}
			add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
		}
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		if ( !$oUser instanceof \WP_User ) {
			$oUser = Services::WpUsers()->getUserByUsername( $sUsername );
		}
		$this->activateUserSession( $oUser );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->activateUserSession( Services::WpUsers()->getUserById( $nUserId ) );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->autoAddSession();
		}
	}

	public function onModuleShutdown() {
		if ( !Services::Rest()->isRest() ) {
			/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
			$oFO = $this->getMod();
			if ( $oFO->hasSession() ) {
				/** @var Session\Update $oUpd */
				$oUpd = $this->getDbHandler()->getQueryUpdater();
				$oUpd->updateLastActivity( $this->getCurrentSession() );
			}
		}

		parent::onModuleShutdown();
	}

	private function autoAddSession() {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		if ( !$oMod->hasSession() && $oMod->isAutoAddSessions() ) {
			$this->queryCreateSession(
				$this->getCon()->getSessionId( true ),
				Services::WpUsers()->getCurrentWpUsername()
			);
		}
	}

	/**
	 * Only show Go To Admin link for Authors and above.
	 * @param string $sMessage
	 * @return string
	 * @throws \Exception
	 */
	public function printLinkToAdmin( $sMessage = '' ) {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		$oU = Services::WpUsers()->getCurrentWpUser();

		if ( in_array( Services::Request()->query( 'action' ), [ '', 'login' ] )
			 && ( $oU instanceof \WP_User ) && $oMod->hasSession() ) {
			$sMessage .= sprintf( '<p class="message">%s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' )
				.sprintf( ' <span style="white-space: nowrap">(%s)</span>', $oU->user_login ),
				( $oU->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}
		return $sMessage;
	}

	/**
	 * @param \WP_User $oUser
	 * @return boolean
	 */
	private function activateUserSession( $oUser ) {
		if ( !$this->isLoginCaptured() && $oUser instanceof \WP_User ) {
			$this->setLoginCaptured();
			// If they have a currently active session, terminate it (i.e. we replace it)
			$oSession = $this->queryGetSession( $this->getSessionId(), $oUser->user_login );
			if ( $oSession instanceof Session\EntryVO ) {
				$this->terminateSession( $oSession->id );
				$this->clearCurrentSession();
			}

			$this->queryCreateSession( $this->getSessionId(), $oUser->user_login );
		}
		return true;
	}

	/**
	 * @return string
	 */
	private function getSessionId() {
		return $this->getCon()->getSessionId();
	}

	/**
	 * @param int $nSessionId
	 * @return bool
	 */
	public function terminateSession( $nSessionId ) {
		$this->getCon()->fireEvent( 'session_terminate' );
		return $this->getMod()
					->getDbHandler()
					->getQueryDeleter()
					->deleteById( $nSessionId );
	}

	/**
	 * @return bool
	 */
	public function terminateCurrentSession() {
		$bSuccess = false;
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			$oSes = $this->getCurrentSession();
			if ( $oSes instanceof Session\EntryVO ) {
				$bSuccess = $this->terminateSession( $oSes->id );
			}
			$this->getCon()->clearSession();
			$this->clearCurrentSession();
		}
		return $bSuccess;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id varchar(32) NOT NULL DEFAULT '',
			wp_username varchar(255) NOT NULL DEFAULT '',
			ip varchar(40) NOT NULL DEFAULT '0',
			browser varchar(32) NOT NULL DEFAULT '',
			logged_in_at int(15) NOT NULL DEFAULT 0,
			last_activity_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			last_activity_uri text NOT NULL DEFAULT '',
			li_code_email varchar(6) NOT NULL DEFAULT '',
			login_intent_expires_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			secadmin_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return Session\EntryVO|null
	 */
	public function getCurrentSession() {
		if ( empty( $this->oCurrent ) ) {
			$this->oCurrent = $this->loadCurrentSession();
		}
		return $this->oCurrent;
	}

	/**
	 * @return $this
	 */
	public function clearCurrentSession() {
		$this->oCurrent = null;
		return $this;
	}

	/**
	 * @return Session\EntryVO|null
	 */
	public function loadCurrentSession() {
		$oSession = null;
		if ( did_action( 'init' ) ) {
			$oSession = $this->queryGetSession( $this->getSessionId() );
		}
		return $oSession;
	}

	/**
	 * @return Session\Handler
	 */
	protected function createDbHandler() {
		return new Session\Handler();
	}

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	protected function queryCreateSession( $sSessionId, $sUsername ) {
		if ( empty( $sSessionId ) || empty( $sUsername ) ) {
			return null;
		}

		$this->getCon()->fireEvent( 'session_start' );

		/** @var Session\Insert $oInsert */
		$oInsert = $this->getMod()
						->getDbHandler()
						->getQueryInserter();
		return $oInsert->create( $sSessionId, $sUsername );
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return Session\EntryVO|null
	 */
	private function queryGetSession( $sSessionId, $sUsername = '' ) {
		/** @var Session\Select $oSel */
		$oSel = $this->getMod()
					 ->getDbHandler()
					 ->getQuerySelector();
		return $oSel->retrieveUserSession( $sSessionId, $sUsername );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'sessions_table_columns' );
		return ( is_array( $aDef ) ? $aDef : [] );
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		return DAY_IN_SECONDS*self::DAYS_TO_KEEP;
	}
}