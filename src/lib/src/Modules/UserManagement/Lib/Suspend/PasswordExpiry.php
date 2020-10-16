<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PasswordExpiry
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend
 */
class PasswordExpiry extends Base {

	/**
	 * @var int
	 */
	private $nMaxPasswordAge;

	/**
	 * @param \WP_User       $user
	 * @param ShieldUserMeta $meta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, $meta ) {
		if ( $this->isPassExpired( $meta ) ) {

			$user = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				implode( ' ', [
					__( 'Sorry, this account is suspended because the password has expired.', 'wp-simple-firewall' ),
					__( 'Please reset your password to regain access.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s &rarr;</a>',
						Services::WpGeneral()->getLostPasswordUrl(),
						__( 'Reset', 'wp-simple-firewall' )
					),
				] )
			);
		}
		return $user;
	}

	/**
	 * @param ShieldUserMeta $oMeta
	 * @return bool
	 */
	private function isPassExpired( $oMeta ) {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( empty( $oMeta->pass_started_at ) ) {
			$oMeta->pass_started_at = $oMeta->first_seen_at;
		}
		return ( Services::Request()->ts() - $oMeta->pass_started_at > $oOpts->getPassExpireTimeout() );
	}

	public function getMaxPasswordAge() :int {
		return (int)$this->nMaxPasswordAge;
	}

	/**
	 * @param int $nMaxPasswordAge
	 * @return $this
	 */
	public function setMaxPasswordAge( $nMaxPasswordAge ) {
		$this->nMaxPasswordAge = $nMaxPasswordAge;
		return $this;
	}
}