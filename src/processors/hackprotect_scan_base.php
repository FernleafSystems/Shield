<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_ScanBase extends Shield\Modules\BaseShield\ShieldProcessor {

	use Shield\Scans\Common\ScanActionConsumer;
	const SCAN_SLUG = 'base';

	public function run() {
		add_action(
			$this->getCon()->prefix( 'ondemand_scan_'.$this->getThisScanCon()->getSlug() ),
			function () {
				/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
				$oMod = $this->getMod();
				$oMod->getScanController()
					 ->startScans( [ $this->getThisScanCon()->getSlug() ] );
			}
		);
	}

	/**
	 */
	public function hookOnDemandScan() {
		$this->scheduleOnDemandScan();
	}

	/**
	 * @param int $nDelay
	 */
	public function scheduleOnDemandScan( $nDelay = 3 ) {
		$sHook = $this->getCon()->prefix( 'ondemand_scan_'.$this->getThisScanCon()->getSlug() );
		if ( !wp_next_scheduled( $sHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + $nDelay, $sHook );
		}
	}

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		return $this->getThisScanCon()->getScanActionVO();
	}

	/**
	 * Because it's the cron and we'll maybe be notifying user, we look
	 * only for items that have not been notified recently.
	 */
	public function cronProcessScanResults() {
		$oScanCon = $this->getThisScanCon();
		$oRes = $oScanCon->getAllResultsForCron();
		if ( $oRes->hasItems() ) {
			$this->getThisScanCon()->runCronAutoRepair( $oRes );

			if ( $this->runCronUserNotify( $oRes ) ) {
				$oScanCon->updateAllAsNotified();
			}
		}
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		return false;
	}

	/**
	 * @return string
	 */
	protected function getScannerButtonForEmail() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
			$oFO->getUrlManualScan(),
			'border:2px solid #e66900;padding:20px;line-height:19px;margin:15px 20px 10px;display:inline-block;text-align:center;width:200px;font-size:18px;color: #e66900;border-radius:3px;',
			__( 'Run Scanner', 'wp-simple-firewall' )
		);
	}

	/**
	 * @return HackGuard\Scan\Controller\Base|mixed
	 */
	protected function getThisScanCon() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return $oMod->getScanCon( static::SCAN_SLUG );
	}

	/**
	 * @param \ICWP_WPSF_Processor_HackProtect_Scanner $oScanner
	 * @return $this
	 * @deprecated 8.5
	 */
	public function setScannerDb( $oScanner ) {
		return $this;
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isRestricted() {
		return false;
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isAvailable() {
		return $this->getThisScanCon()->isScanningAvailable();
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	protected function isCronAutoRepair() {
		return $this->getThisScanCon()->isCronAutoRepair();
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isEnabled() {
		return $this->getThisScanCon()->isEnabled();
	}

	/**
	 * Override this to provide the correct VO
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @deprecated 8.5
	 */
	protected function getNewActionVO() {
		return $this->getThisScanCon()->getScanActionVO();
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oRes
	 * @deprecated 8.5
	 */
	protected function runCronAutoRepair( $oRes ) {
	}
}