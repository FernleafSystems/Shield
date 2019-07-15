<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

/**
 * Class ScannerBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
abstract class ScannerBase {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Base\ScanActionConsumer;

	/**
	 * @return ResultsSet
	 */
	abstract public function run();

	/**
	 * @param string $sFullPath
	 * @return ResultItem|null
	 */
	protected function scanPath( $sFullPath ) {
		$oResultItem = null;

		/** @var MalScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		try {
			$oLocator = ( new Utilities\File\LocateStrInFile() )->setPath( $sFullPath );

			{ // Simple Patterns first
				$oLocator->setIsRegEx( false );
				foreach ( $oAction->patterns_simple as $sSig ) {

					$aLines = $oLocator->setNeedle( $sSig )
									   ->run();
					if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
						$oResultItem = $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
						return $oResultItem;
					}
				}
			}

			{ // RegEx Patterns
				$oLocator->setIsRegEx( true );
				foreach ( $oAction->patterns_regex as $sSig ) {

					$aLines = $oLocator->setNeedle( $sSig )
									   ->run();
					if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
						$oResultItem = $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
						return $oResultItem;
					}
				}
			}
		}
		catch ( \Exception $oE ) {
		}

		return $oResultItem;
	}

	/**
	 * @param $aLines
	 * @param $sFullPath
	 * @param $sSig
	 * @return ResultItem
	 */
	private function getResultItemFromLines( $aLines, $sFullPath, $sSig ) {
		$oResultItem = new ResultItem();
		$oResultItem->path_full = wp_normalize_path( $sFullPath );
		$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
		$oResultItem->is_mal = true;
		$oResultItem->mal_sig = base64_encode( $sSig );
		$oResultItem->file_lines = $aLines;
		return $oResultItem;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function canExcludeFile( $sFullPath ) {
		return $this->isValidCoreFile( $sFullPath ) || $this->isPluginFileValid( $sFullPath );
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isPluginFileValid( $sFullPath ) {
		$bCanExclude = false;

		if ( strpos( $sFullPath, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0 ) {

			$oPluginFiles = new Utilities\WpOrg\Plugin\Files();
			$oThePlugin = $oPluginFiles->findPluginFromFile( $sFullPath );
			if ( $oThePlugin instanceof WpPluginVo ) {
				try {
					$sTmpFile = $oPluginFiles
						->setWorkingSlug( $oThePlugin->slug )
						->setWorkingVersion( $oThePlugin->Version )
						->getOriginalFileFromVcs( $sFullPath );
					if ( Services::WpFs()->exists( $sTmpFile )
						 && ( new Utilities\File\Compare\CompareHash() )->isEqualFilesMd5( $sTmpFile, $sFullPath ) ) {
						$bCanExclude = true;
					}
				}
				catch ( \Exception $oE ) {
				}
			}
		}

		return $bCanExclude;
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	private function isValidCoreFile( $sFullPath ) {
		$sCoreHash = Services::CoreFileHashes()->getFileHash( $sFullPath );
		try {
			$bValid = !empty( $sCoreHash )
					  && ( new Utilities\File\Compare\CompareHash() )->isEqualFileMd5( $sFullPath, $sCoreHash );
		}
		catch ( \Exception $oE ) {
			$bValid = false;
		}
		return $bValid;
	}
}