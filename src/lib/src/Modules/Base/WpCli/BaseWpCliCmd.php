<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseWpCliCmd {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
	}

	protected function run() {
		try {
			$this->addCmds();
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @param array $aParts
	 * @return string
	 */
	protected function buildCmd( array $aParts ) {
		return implode( ' ',
			array_filter( array_merge( $this->getBaseCmdParts(), $aParts ) )
		);
	}

	/**
	 * @return bool
	 */
	protected function canRun() {
		/** @var Options $oOpts */
		$oOpts = $this->getCon()
					  ->getModule_Plugin()
					  ->getOptions();
		return $this->getOptions()->getWpCliCfg()[ 'enabled' ]
			   && $oOpts->isEnabledWpcli();
	}

	/**
	 * @return string[]
	 */
	protected function getBaseCmdParts() {
		return [ 'shield', $this->getBaseCmdKey() ];
	}

	/**
	 * @return string
	 */
	protected function getBaseCmdKey() {
		$sRoot = $this->getOptions()->getWpCliCfg()[ 'root' ];
		return empty( $sRoot ) ? $this->getMod()->getModSlug( false ) : $sRoot;
	}

	/**
	 * @param array $aArgs
	 * @return array
	 */
	protected function mergeCommonCmdArgs( array $aArgs ) {
		return array_merge(
			$this->getCommonCmdArgs(),
			$aArgs
		);
	}

	/**
	 * @return array
	 */
	protected function getCommonCmdArgs() {
		return [
			'before_invoke' => function () {
				$this->beforeInvokeCmd();
			},
			'after_invoke'  => function () {
				$this->afterInvokeCmd();
			},
			'when'          => 'before_wp_load',
		];
	}

	protected function afterInvokeCmd() {
	}

	protected function beforeInvokeCmd() {
	}

	/**
	 * @param array $aA
	 * @return \WP_User
	 * @throws \WP_CLI\ExitException
	 */
	protected function loadUserFromArgs( array $aA ) {
		$oWpUsers = Services::WpUsers();

		$oU = null;
		if ( isset( $aA[ 'uid' ] ) ) {
			$oU = $oWpUsers->getUserById( $aA[ 'uid' ] );
		}
		elseif ( isset( $aA[ 'email' ] ) ) {
			$oU = $oWpUsers->getUserByEmail( $aA[ 'email' ] );
		}
		elseif ( isset( $aA[ 'username' ] ) ) {
			$oU = $oWpUsers->getUserByUsername( $aA[ 'username' ] );
		}

		if ( !$oU instanceof \WP_User || $oU->ID < 1 ) {
			\WP_CLI::error( "Couldn't find that user." );
		}

		return $oU;
	}

	/**
	 * @param array $aA
	 * @return bool
	 */
	protected function isForceFlag( array $aA ) {
		return (bool)\WP_CLI\Utils\get_flag_value( $aA, 'force', false );
	}
}