<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops\ResetPlugin;
use WP_CLI;

class Reset extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'reset' ] ),
			[ $this, 'cmdReset' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Reset the Shield plugin to default settings.',
			'synopsis'  => [
				[
					'type'        => 'flag',
					'name'        => 'force',
					'optional'    => true,
					'description' => 'Bypass confirmation prompt.',
				],
			],
		] ) );
	}

	public function cmdReset( $null, $aA ) {
		if ( !$this->isForceFlag( $aA ) ) {
			WP_CLI::confirm( __( 'Are you sure you want to reset the Shield plugin to defaults?', 'wp-simple-firewall' ) );
		}
		( new ResetPlugin() )
			->setCon( $this->getCon() )
			->run();
		WP_CLI::success( __( 'Plugin reset to defaults.', 'wp-simple-firewall' ) );
	}
}