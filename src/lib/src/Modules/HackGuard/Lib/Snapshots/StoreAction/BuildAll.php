<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;

class BuildAll extends BaseBulk {

	/**
	 */
	public function build() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $oAsset ) {
			try {
				( new Build() )
					->setMod( $this->getMod() )
					->setAsset( $oAsset )
					->run();
			}
			catch ( \Exception $oE ) {
			}
		}
	}
}