<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Client;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\MainWPVO;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Controller {

	use PluginControllerConsumer;

	const MIN_VERSION_MAINWP = '4.1';

	public function run() {
		try {
			$this->runServerSide();
		}
		catch ( \Exception $e ) {
		}
		try {
			$this->runClientSide();
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runClientSide() {
		$con = $this->getCon();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$mwpVO->is_client = $this->isMainWPChildActive();

		if ( !$mwpVO->is_client ) {
			throw new \Exception( 'MainWP Child not active' );
		}

		( new Client\Init() )
			->setCon( $con )
			->run();

		$con->mwpVO = $mwpVO;
	}

	/**
	 * @throws \Exception
	 */
	private function runServerSide() {
		$con = $this->getCon();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$mwpVO->is_server = false;

		if ( !$this->isMainWPServerActive() ) {
			throw new \Exception( 'MainWP not active' );
		}

		$mwpVO->child_key = ( new Server\Init() )
			->setCon( $con )
			->run();
		$mwpVO->child_file = $con->getRootFile();

		$mwpVO->is_server = true;

		$con->mwpVO = $mwpVO;
	}

	private function isMainWPChildActive() :bool {
		return @class_exists( '\MainWP\Child\MainWP_Child' );
	}

	private function isMainWPServerActive() :bool {
		return (bool)apply_filters( 'mainwp_activated_check', false );
	}

	public static function isMainWPChildVersionSupported() :bool {
		return version_compare( \MainWP\Child\MainWP_Child::$version, self::MIN_VERSION_MAINWP, '>=' );
	}

	public static function isMainWPServerVersionSupported() :bool {
		return defined( 'MAINWP_VERSION' )
			   && version_compare( MAINWP_VERSION, self::MIN_VERSION_MAINWP, '>=' );
	}
}