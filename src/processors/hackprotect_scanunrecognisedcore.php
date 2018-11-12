<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_FileCleanerScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

use \FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ICWP_WPSF_Processor_HackProtect_FileCleanerScan extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string[]
	 */
	protected $aCoreFiles;

	/**
	 */
	public function run() {
		$this->setupChecksumCron();

		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$oReq = $this->loadRequest();

			switch ( $oReq->query( 'shield_action' ) ) {
				case 'delete_unrecognised_file':
					$sPath = '/'.$oReq->query( 'repair_file_path' ); // "/" prevents esc_url() from prepending http.
					break;
			}
		}
	}

	protected function setupChecksumCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()
			 ->setRecurrence( $this->prefix( sprintf( 'per-day-%s', $oFO->getScanFrequency() ) ) )
			 ->createCronJob(
				 $oFO->getUfcCronName(),
				 array( $this, 'cron_dailyFileCleanerScan' )
			 );
		add_action( $oFO->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 */
	public function deleteCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()->deleteCronJob( $oFO->getUfcCronName() );
	}

	/**
	 * @return Scans\UnrecognisedCore\ResultsSet
	 */
	public function doScan() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oScanner = ( new Scans\UnrecognisedCore\Scanner() )
			->setExclusions( $oFO->getUfcFileExclusions() );

		if ( $oFO->isUfsScanUploads() ) {
			$sUploadsDir = $this->loadWp()->getDirUploads();
			if ( !empty( $sUploadsDir ) ) {
				$oScanner->addScanDirector( $sUploadsDir )
						 ->addDirSpecificFileTypes(
							 $sUploadsDir,
							 [
								 'php',
								 'php5',
								 'js',
							 ]
						 );
			}
		}
		$oResults = $oScanner->run();

		$oFO->setLastScanAt( 'ufc' );
		$oResults->hasItems() ? $oFO->setLastScanProblemAt( 'ufc' ) : $oFO->clearLastScanProblemAt( 'ufc' );

		return $oResults;
	}


	public function cron_dailyFileCleanerScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isUfcEnabled() ) {
			$oRes = $oFO->isUfcDeleteFiles() ? $this->doScanAndFullRepair() : $this->doScan();
			if ( $oRes->hasItems() && $oFO->isUfsSendReport() ) {
				$this->emailResults( $oRes->getItemsPathsFull() );
			}
		}
	}

	/**
	 * @return Scans\UnrecognisedCore\ResultsSet
	 */
	public function doScanAndFullRepair() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oResultSet = $this->doScan();
		( new Scans\UnrecognisedCore\Repair() )->repairResultsSet( $oResultSet );
		$oFO->clearLastScanProblemAt( 'ufc' );

		return $oResultSet;
	}

	/**
	 * @param array $aFiles
	 */
	protected function emailResults( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '[%s] %s', _wpsf__( 'Warning' ), _wpsf__( 'Unrecognised WordPress Files Detected' ) ),
				 $this->buildEmailBodyFromFiles( $aFiles )
			 );

		$this->addToAuditEntry(
			sprintf( _wpsf__( 'Sent Unrecognised File Scan Notification email alert to: %s' ), $sTo )
		);
	}

	/**
	 * The newer approach is to only enumerate files if they were deleted
	 * @param string[] $aFiles
	 * @return string[]
	 */
	private function buildEmailBodyFromFiles( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$sName = $this->getController()->getHumanName();
		$sHomeUrl = $this->loadWp()->getHomeUrl();

		$aContent = array(
			sprintf( _wpsf__( 'The %s Unrecognised File Scanner found files which you need to review.' ), $sName ),
			sprintf( '%s: %s', _wpsf__( 'Site URL' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			''
		);

		if ( $oFO->isUfcDeleteFiles() || $oFO->isIncludeFileLists() || !$oFO->canRunWizards() ) {
			$aContent[] = _wpsf__( 'Files that were discovered' ).':';
			foreach ( $aFiles as $sFile ) {
				$aContent[] = ' - '.$sFile;
			}
			$aContent[] = '';

			if ( $oFO->isUfcDeleteFiles() ) {
				$aContent[] = sprintf( _wpsf__( '%s has attempted to delete these files based on your current settings.' ), $sName );
				$aContent[] = '';
			}
		}

		if ( $oFO->canRunWizards() ) {
			$aContent[] = _wpsf__( 'We recommend you run the scanner to review your site' ).':';
			$aContent[] = sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
				$oFO->getUrl_Wizard( 'ufc' ),
				'border:1px solid;padding:20px;line-height:19px;margin:10px 20px;display:inline-block;text-align:center;width:290px;font-size:18px;',
				_wpsf__( 'Run Scanner' )
			);
			$aContent[] = '';
		}

		if ( !$oFO->getConn()->isRelabelled() ) {
			$aContent[] = '[ <a href="https://icwp.io/moreinfochecksum">'._wpsf__( 'More Info On This Scanner' ).' ]</a>';
		}

		return $aContent;
	}
}