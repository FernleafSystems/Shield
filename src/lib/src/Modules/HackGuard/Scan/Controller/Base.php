<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseEntryFormatter;

abstract class Base {

	use ModConsumer;

	/**
	 * @var BaseScanActionVO
	 */
	private $oScanActionVO;

	/**
	 * Base constructor.
	 * see dynamic constructors: features/hack_protect.php
	 */
	public function __construct() {
	}

	public function cleanStalesResults() {
		$oResults = ( new HackGuard\Scan\Results\ResultsRetrieve() )
			->setScanController( $this )
			->retrieve();
		foreach ( $oResults->getItems() as $oItem ) {
			if ( !$this->isResultItemStale( $oItem ) ) {
				$oResults->removeItem( $oItem->hash );
			}
		}
		( new HackGuard\Scan\Results\ResultsDelete() )
			->setScanController( $this )
			->delete( $oResults );
	}

	/**
	 * @param BaseResultItem|mixed $oItem
	 * @return bool
	 */
	abstract protected function isResultItemStale( $oItem );

	/**
	 * @param int|string $sItemId
	 * @param string     $sAction
	 * @return bool
	 * @throws \Exception
	 */
	public function executeItemAction( $sItemId, $sAction ) {
		$bSuccess = false;

		if ( is_numeric( $sItemId ) ) {
			/** @var Databases\Scanner\EntryVO $oEntry */
			$oEntry = $this->getScanResultsDbHandler()
						   ->getQuerySelector()
						   ->byId( $sItemId );
			if ( empty( $oEntry ) ) {
				throw new \Exception( 'Item could not be found.' );
			}

			$oItem = ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
				->setScanController( $this )
				->convertVoToResultItem( $oEntry );

			$bSuccess = $this->getItemActionHandler()
							 ->setDbHandler( $this->getScanResultsDbHandler() )
							 ->setScanItem( $oItem )
							 ->process( $sAction );
		}

		return $bSuccess;
	}

	/**
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function getAllResultsForCron() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Scanner\Select $oSel */
		$oSel = $this->getScanResultsDbHandler()->getQuerySelector();
		$oSel->filterByScan( $this->getSlug() )
			 ->filterForCron( $oMod->getScanNotificationInterval() );
		return ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanController( $this )
			->fromVOsToResultsSet( $oSel->query() );
	}

	/**
	 * @return bool
	 */
	public function updateAllAsNotified() {
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $this->getScanResultsDbHandler()->getQueryUpdater();
		return $oUpd->setAllNotifiedForScan( $this->getSlug() );
	}

	/**
	 * @param bool $bIncludeIgnored
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function getAllResults( $bIncludeIgnored = false ) {
		/** @var Databases\Scanner\Select $oSel */
		$oSel = $this->getScanResultsDbHandler()->getQuerySelector();
		$oSel->filterByScan( $this->getSlug() );
		if ( !$bIncludeIgnored ) {
			$oSel->filterByNotIgnored();
		}
		return ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanController( $this )
			->fromVOsToResultsSet( $oSel->query() );
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler
	 */
	protected function getItemActionHandler() {
		return $this->newItemActionHandler()
					->setMod( $this->getMod() )
					->setScanController( $this );
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler|mixed
	 */
	abstract protected function newItemActionHandler();

	/**
	 * @return BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		if ( !$this->oScanActionVO instanceof BaseScanActionVO ) {
			$this->oScanActionVO = HackGuard\Scan\ScanActionFromSlug::GetAction( $this->getSlug() );
		}
		return $this->oScanActionVO;
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		return false;
	}

	/**
	 * @return bool
	 */
	abstract public function isEnabled();

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function isScanningAvailable() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return !$this->isPremiumOnly() || $oOpts->isPremium();
	}

	/**
	 * @return $this
	 */
	public function resetIgnoreStatus() {
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $this->getScanResultsDbHandler()->getQueryUpdater();
		$oUpd->clearIgnoredAtForScan( $this->getSlug() );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function resetNotifiedStatus() {
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $this->getScanResultsDbHandler()->getQueryUpdater();
		$oUpd->clearNotifiedAtForScan( $this->getSlug() );
		return $this;
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oRes
	 */
	public function runCronAutoRepair( $oRes ) {
		if ( $this->isCronAutoRepair() ) {
			$this->getItemActionHandler()
				 ->getRepairer()
				 ->setIsManualAction( false )
				 ->setAllowDelete( false )
				 ->repairResultsSet( $oRes );
		}
	}

	/**
	 * @return $this
	 */
	public function purge() {
		( new HackGuard\Scan\Results\ResultsDelete() )
			->setScanController( $this )
			->deleteAllForScan();
		return $this;
	}

	/**
	 * @return Databases\Scanner\Handler
	 */
	public function getScanResultsDbHandler() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return $oMod->getDbHandler_ScanResults();
	}

	/**
	 * @return string
	 */
	public function getSlug() {
		try {
			$sSlug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \ReflectionException $oRE ) {
			$sSlug = '';
		}
		return $sSlug;
	}

	/**
	 * @return BaseResultItem|mixed
	 */
	public function getNewResultItem() {
		$sClass = $this->getScanNamespace().'ResultItem';
		return new $sClass();
	}

	/**
	 * @return BaseResultsSet|mixed
	 */
	public function getNewResultsSet() {
		$sClass = $this->getScanNamespace().'ResultsSet';
		return new $sClass();
	}

	/**
	 * @return BaseEntryFormatter|mixed
	 */
	public function getTableEntryFormatter() {
		$sClass = $this->getScanNamespace().'Table\\EntryFormatter';
		/** @var BaseEntryFormatter $oF */
		$oF = new $sClass();
		return $oF->setScanController( $this )
				  ->setMod( $this->getMod() )
				  ->setScanActionVO( $this->getScanActionVO() );
	}

	/**
	 * @return string
	 */
	public function getScanNamespace() {
		try {
			$sName = ( new \ReflectionClass( $this->getScanActionVO() ) )->getNamespaceName();
		}
		catch ( \Exception $oE ) {
			$sName = __NAMESPACE__;
		}
		return rtrim( $sName, '\\' ).'\\';
	}
}