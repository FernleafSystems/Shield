<?php

if ( class_exists( 'ICWP_WPSF_BaseDbProcessor', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

abstract class ICWP_WPSF_BaseDbProcessor extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler
	 */
	protected $oDbh;

	/**
	 * The full database table name.
	 * @var string
	 */
	protected $sFullTableName;

	/**
	 * @var boolean
	 */
	protected $bTableExists;

	/**
	 * @var bool
	 */
	private $bTableStructureIsValid;

	/**
	 * @var integer
	 */
	protected $nAutoExpirePeriod = null;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Base $oModCon
	 * @param string                        $sTableName
	 */
	public function __construct( $oModCon, $sTableName = null ) {
		parent::__construct( $oModCon );
		$this->initializeTable( $sTableName );
	}

	public function onWpInit() {
		parent::onWpInit();

		$oCon = $this->getController();
		if ( $oCon->getHasPermissionToManage() ) {
			add_action( $oCon->prefix( 'delete_plugin' ), array( $this, 'deleteTable' ) );
		}
	}

	/**
	 */
	public function deleteTable() {
		$this->deleteCleanupCron();
		$this->getDbHandler()->deleteTable();
	}

	/**
	 * @param string $sTableName
	 * @throws Exception
	 */
	protected function initializeTable( $sTableName ) {
		if ( empty( $sTableName ) ) {
			throw new Exception( 'Table name is empty' );
		}
		$this->oDbh = $this->getDbHandler()
						   ->setTable( $this->getMod()->prefixOptionKey( $sTableName ) )
						   ->setColumnsDefinition( $this->getTableColumnsByDefinition() )
						   ->setSqlCreate( $this->getCreateTableSql() );

		if ( $this->oDbh->tableInit() ) {
			$this->createCleanupCron();
		}
	}

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		try {
			return ( parent::isReadyToRun() && $this->getDbHandler()->isReady() );
		}
		catch ( Exception $oE ) {
			return false;
		}
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler
	 */
	abstract protected function createDbHandler();

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Handler
	 */
	public function getDbHandler() {
		if ( !isset( $this->oDbh ) ) {
			$this->oDbh = $this->createDbHandler();
		}
		return $this->oDbh;
	}

	/**
	 * @return string
	 */
	abstract protected function getCreateTableSql();

	/**
	 * @return array
	 */
	abstract protected function getTableColumnsByDefinition();

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function createCleanupCron() {
		$sFullHookName = $this->getDbCleanupHookName();
		if ( !wp_next_scheduled( $sFullHookName ) && !defined( 'WP_INSTALLING' ) ) {
			$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' )*HOUR_IN_SECONDS;
			wp_schedule_event( $nNextRun, 'daily', $sFullHookName );
		}
		$sFullHookName = $this->getDbCleanupHookName();
		add_action( $sFullHookName, array( $this, 'cleanupDatabase' ) );
	}

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function deleteCleanupCron() {
		wp_clear_scheduled_hook( $this->getDbCleanupHookName() );
	}

	/**
	 * @return string
	 */
	protected function getDbCleanupHookName() {
		return $this->getController()->prefix( $this->getMod()->getSlug().'_db_cleanup' );
	}

	/**
	 * @return bool|int
	 */
	public function cleanupDatabase() {
		$nAutoExpirePeriod = $this->getAutoExpirePeriod();
		if ( is_null( $nAutoExpirePeriod ) || !$this->getDbHandler()->isTable() ) {
			return false;
		}
		$nTimeStamp = $this->time() - $nAutoExpirePeriod;
		return $this->getDbHandler()->deleteRowsOlderThan( $nTimeStamp );
	}

	/**
	 * 1 in 10 page loads will clean the databases. This ensures that even if the crons don't run
	 * correctly, we'll keep it trim.
	 */
	public function onModuleShutdown() {
		parent::onModuleShutdown();
		if ( rand( 1, 20 ) === 2 ) {
			$this->cleanupDatabase();
		}
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		return null;
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete
	 */
	protected function getQueryDeleter() {
		return $this->getDbHandler()->getQueryDeleter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Insert
	 */
	protected function getQueryInserter() {
		return $this->getDbHandler()->getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Select
	 */
	protected function getQuerySelector() {
		return $this->getDbHandler()->getQuerySelector();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Update
	 */
	protected function getQueryUpdater() {
		return $this->getDbHandler()->getQueryUpdater();
	}
}