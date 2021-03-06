<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\BaseScanFromFileMap;

/**
 * Class ScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ScanFromFileMap extends BaseScanFromFileMap {

	/**
	 * @return FileScanner
	 */
	protected function getFileScanner() {
		return ( new FileScanner() )
			->setMod( $this->getMod() )
			->setScanActionVO( $this->getScanActionVO() );
	}
}