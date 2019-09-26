<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $sFullPath
	 * @return ResultItem|null
	 */
	public function scan( $sFullPath ) {
		$oResultItem = null;

		/** @var ScanActionVO $oAction */
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
						$this->setFalsePositiveConfidence( $oResultItem );
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
						$this->setFalsePositiveConfidence( $oResultItem );
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
		$oResultItem->file_lines = array_map(
			function ( $nLineNumber ) {
				return $nLineNumber + 1;
			},
			$aLines // because lines start at ZERO
		);
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
	 * @param ResultItem $oItem
	 */
	private function setFalsePositiveConfidence( $oItem ) {
		/** @var ScanActionVO $oScanVO */
		$oScanVO = $this->getScanActionVO();

		$oItem->fp_confidence = 0;
		$sFilePart = basename( $oItem->path_full );
		if ( isset( $oScanVO->whitelist[ $sFilePart ] ) ) {
			try {
				$oHasher = new Utilities\File\Compare\CompareHash();
				foreach ( $oScanVO->whitelist[ $sFilePart ] as $sWlHash => $nConfidence ) {
					if ( $oHasher->isEqualFileSha1( $oItem->path_full, $sWlHash ) ) {
						$oItem->fp_confidence = $nConfidence;
						break;
					}
				}
			}
			catch ( \InvalidArgumentException $oE ) {
			}
		}
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

				$oPlugVersion = ( new Utilities\WpOrg\Plugin\Versions() )
					->setWorkingSlug( $oThePlugin->slug )
					->setWorkingVersion( $oThePlugin->Version );

				// Only try to download load a file if the plugin actually uses SVN Tags for this version.
				if ( $oPlugVersion->exists( $oThePlugin->Version, true ) ) {
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