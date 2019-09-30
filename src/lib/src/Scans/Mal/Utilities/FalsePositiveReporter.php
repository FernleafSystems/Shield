<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malware;

/**
 * Class FalsePositiveReporter
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities
 */
class FalsePositiveReporter {

	use Modules\ModConsumer;

	/**
	 * To prevent duplicate reports, we cache what we report and only send the report
	 * if we've never sent this before.
	 * @param string $sFullPath
	 * @param string $sAlgo
	 * @param bool   $bIsFalsePositive
	 * @return mixed
	 */
	public function report( $sFullPath, $sAlgo = 'sha1', $bIsFalsePositive = true ) {
		$bReported = false;

		/** @var Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( !$oOpts->isMalUseNetworkIntelligence() ) {
			return $bReported;
		}

		$aReported = $oOpts->getOpt( 'mal_fp_reports', [] );
		$sSig = md5( serialize(
			[
				basename( $sFullPath ),
				sha1( Services::DataManipulation()->convertLineEndingsDosToLinux( $sFullPath ) ),
				$bIsFalsePositive
			]
		) );

		if ( !is_array( $aReported ) ) {
			$aReported = [];
		}
		if ( !isset( $aReported[ $sSig ] ) ) {
			// Haven't reported yet, so we proceed.
			$bReported = ( new Malware\Whitelist\ReportFalsePositive() )
				->report( $sFullPath, $sAlgo, $bIsFalsePositive );
		}

		$aReported[ $sSig ] = Services::Request()->ts();
		$oOpts->setOpt( 'mal_fp_reports', array_filter(
			$aReported,
			function ( $nTS ) {
				return $nTS > Services::Request()->carbon()->subMonth()->timestamp;
			}
		) );

		$this->getMod()->saveModOptions(); // important to save immediately due to async nature

		return $bReported;
	}
}
