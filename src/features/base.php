<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_FeatureHandler_Base
 */
abstract class ICWP_WPSF_FeatureHandler_Base {

	use Shield\Modules\PluginControllerConsumer;

	/**
	 * @var string
	 */
	private $sOptionsStoreKey;

	/**
	 * @var string
	 */
	protected $sModSlug;

	/**
	 * @var bool
	 */
	protected $bImportExportWhitelistNotify = false;

	/**
	 * @var ICWP_WPSF_FeatureHandler_Email
	 * @deprecated 10.1
	 */
	private static $oEmailHandler;

	/**
	 * @var Shield\Modules\Base\BaseProcessor
	 */
	private $oProcessor;

	/**
	 * @var ICWP_WPSF_Wizard_Base
	 */
	private $oWizard;

	/**
	 * @var Shield\Modules\Base\BaseReporting
	 */
	private $oReporting;

	/**
	 * @var Shield\Modules\Base\UI
	 */
	private $oUI;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $oOpts;

	/**
	 * @var Shield\Modules\Base\WpCli
	 */
	private $oWpCli;

	/**
	 * @var Shield\Databases\Base\Handler[]
	 */
	private $aDbHandlers;

	/**
	 * @param Shield\Controller\Controller $oPluginController
	 * @param array                        $aMod
	 * @throws \Exception
	 */
	public function __construct( $oPluginController, $aMod = [] ) {
		if ( !$oPluginController instanceof Shield\Controller\Controller ) {
			throw new \Exception( 'Plugin controller not supplied to Module' );
		}
		$this->setCon( $oPluginController );

		if ( empty( $aMod[ 'storage_key' ] ) && empty( $aMod[ 'slug' ] ) ) {
			throw new \Exception( 'Module storage key AND slug are undefined' );
		}

		$this->sOptionsStoreKey = empty( $aMod[ 'storage_key' ] ) ? $aMod[ 'slug' ] : $aMod[ 'storage_key' ];
		if ( isset( $aMod[ 'slug' ] ) ) {
			$this->sModSlug = $aMod[ 'slug' ];
		}

		if ( $this->verifyModuleMeetRequirements() ) {
			$this->handleAutoPageRedirects();
			$this->setupHooks( $aMod );
			$this->doPostConstruction();
		}
	}

	protected function setupHooks( array $aModProps ) {
		$con = $this->getCon();
		$nRunPriority = isset( $aModProps[ 'load_priority' ] ) ? $aModProps[ 'load_priority' ] : 100;

		add_action( $con->prefix( 'modules_loaded' ), function () {
			$this->onModulesLoaded();
		}, $nRunPriority );
		add_action( $con->prefix( 'run_processors' ), [ $this, 'onRunProcessors' ], $nRunPriority );
		add_action( 'init', [ $this, 'onWpInit' ], 1 );

		$nMenuPri = isset( $aModProps[ 'menu_priority' ] ) ? $aModProps[ 'menu_priority' ] : 100;
		add_filter( $con->prefix( 'submenu_items' ), [ $this, 'supplySubMenuItem' ], $nMenuPri );
		add_action( $con->prefix( 'plugin_shutdown' ), [ $this, 'onPluginShutdown' ] );
		add_action( $con->prefix( 'deactivate_plugin' ), [ $this, 'onPluginDeactivate' ] );
		add_action( $con->prefix( 'delete_plugin' ), [ $this, 'onPluginDelete' ] );
		add_filter( $con->prefix( 'aggregate_all_plugin_options' ), [ $this, 'aggregateOptionsValues' ] );

		add_filter( $con->prefix( 'register_admin_notices' ), [ $this, 'fRegisterAdminNotices' ] );

		add_action( $con->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
		add_action( $con->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'onWpEnqueueAdminJs' ], 100 );

		if ( is_admin() || is_network_admin() ) {
			$this->loadAdminNotices();
		}

//		if ( $this->isAdminOptionsPage() ) {
//			add_action( 'current_screen', array( $this, 'onSetCurrentScreen' ) );
//		}

		$this->setupCustomHooks();
	}

	protected function setupCustomHooks() {
	}

	protected function doPostConstruction() {
	}

	public function runDailyCron() {
		$this->cleanupDatabases();
	}

	public function runHourlyCron() {
	}

	protected function cleanupDatabases() {
		foreach ( $this->getDbHandlers( true ) as $oDbh ) {
			if ( $oDbh instanceof Shield\Databases\Base\Handler && $oDbh->isReady() ) {
				$oDbh->autoCleanDb();
			}
		}
	}

	/**
	 * @param bool $bInitAll
	 * @return Shield\Databases\Base\Handler[]
	 */
	protected function getDbHandlers( $bInitAll = false ) {
		if ( $bInitAll ) {
			foreach ( $this->getAllDbClasses() as $sDbSlug => $sDbClass ) {
				$this->getDbH( $sDbSlug );
			}
		}
		return is_array( $this->aDbHandlers ) ? $this->aDbHandlers : [];
	}

	/**
	 * @param string $sDbhKey
	 * @return Shield\Databases\Base\Handler|mixed|false
	 */
	protected function getDbH( $sDbhKey ) {
		$dbh = false;

		if ( !is_array( $this->aDbHandlers ) ) {
			$this->aDbHandlers = [];
		}

		if ( !empty( $this->aDbHandlers[ $sDbhKey ] ) ) {
			$dbh = $this->aDbHandlers[ $sDbhKey ];
		}
		else {
			$aDbClasses = $this->getAllDbClasses();
			if ( isset( $aDbClasses[ $sDbhKey ] ) ) {
				/** @var Shield\Databases\Base\Handler $dbh */
				$dbh = new $aDbClasses[ $sDbhKey ]();
				try {
					$dbh->setMod( $this )->tableInit();
				}
				catch ( \Exception $e ) {
				}
			}
			$this->aDbHandlers[ $sDbhKey ] = $dbh;
		}

		return $dbh;
	}

	/**
	 * @return string[]
	 */
	private function getAllDbClasses() {
		$classes = $this->getOptions()->getDef( 'db_classes' );
		return is_array( $classes ) ? $classes : [];
	}

	/**
	 * @return false|Shield\Modules\Base\Upgrade|mixed
	 */
	public function getUpgradeHandler() {
		return $this->loadModElement( 'Upgrade' );
	}

	/**
	 * @param string $sEncoding
	 * @return array
	 */
	public function getAjaxFormParams( $sEncoding = 'none' ) {
		$oReq = Services::Request();
		$aFormParams = [];
		$sRaw = $oReq->post( 'form_params', '' );

		if ( !empty( $sRaw ) ) {

			$sMaybeEncoding = $oReq->post( 'enc_params' );
			if ( in_array( $sMaybeEncoding, [ 'none', 'lz-string', 'b64' ] ) ) {
				$sEncoding = $sMaybeEncoding;
			}

			switch ( $sEncoding ) {
				case 'lz-string':
					$sRaw = \LZCompressor\LZString::decompress( base64_decode( $sRaw ) );
					break;

				case 'b64':
					$sRaw = base64_decode( $sRaw );
					break;

				case 'none':
				default:
					break;
			}

			parse_str( $sRaw, $aFormParams );
		}
		return $aFormParams;
	}

	/**
	 * @param array $aAdminNotices
	 * @return array
	 */
	public function fRegisterAdminNotices( $aAdminNotices ) {
		if ( !is_array( $aAdminNotices ) ) {
			$aAdminNotices = [];
		}
		return array_merge( $aAdminNotices, $this->getOptions()->getAdminNotices() );
	}

	private function verifyModuleMeetRequirements() :bool {
		$bMeetsReqs = true;

		$aPhpReqs = $this->getOptions()->getFeatureRequirement( 'php' );
		if ( !empty( $aPhpReqs ) ) {

			if ( !empty( $aPhpReqs[ 'version' ] ) ) {
				$bMeetsReqs = $bMeetsReqs && Services::Data()->getPhpVersionIsAtLeast( $aPhpReqs[ 'version' ] );
			}
			if ( !empty( $aPhpReqs[ 'functions' ] ) && is_array( $aPhpReqs[ 'functions' ] ) ) {
				foreach ( $aPhpReqs[ 'functions' ] as $sFunction ) {
					$bMeetsReqs = $bMeetsReqs && function_exists( $sFunction );
				}
			}
			if ( !empty( $aPhpReqs[ 'constants' ] ) && is_array( $aPhpReqs[ 'constants' ] ) ) {
				foreach ( $aPhpReqs[ 'constants' ] as $sConstant ) {
					$bMeetsReqs = $bMeetsReqs && defined( $sConstant );
				}
			}
		}

		return $bMeetsReqs;
	}

	protected function onModulesLoaded() {
	}

	public function onRunProcessors() {
		$oOpts = $this->getOptions();
		if ( $oOpts->getFeatureProperty( 'auto_load_processor' ) ) {
			$this->loadProcessor();
		}
		try {
			$bSkip = (bool)$oOpts->getFeatureProperty( 'skip_processor' );
			if ( !$bSkip && !$this->isUpgrading() && $this->isModuleEnabled() && $this->isReadyToExecute() ) {
				$this->doExecuteProcessor();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		return !is_null( $this->getProcessor() );
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->execute();
	}

	public function onWpInit() {
	}

	/**
	 * We have to do it this way as the "page hook" is built upon the top-level plugin
	 * menu name. But what if we white label?  So we need to dynamically grab the page hook
	 */
	public function onSetCurrentScreen() {
		global $page_hook;
		add_action( 'load-'.$page_hook, [ $this, 'onLoadOptionsScreen' ] );
	}

	public function onLoadOptionsScreen() {
		if ( $this->getCon()->isValidAdminArea() ) {
			$this->buildContextualHelp();
		}
	}

	/**
	 * Override this and adapt per feature
	 * @return Shield\Modules\Base\BaseProcessor|mixed
	 */
	protected function loadProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			try {
				// TODO: Remove 'abstract' from base processor after transition to new processors is complete
				$class = $this->findElementClass( 'Processor', true );
			}
			catch ( Exception $e ) {
				$class = $this->getProcessorClassName();
			}
			if ( !@class_exists( $class ) ) {
				return null;
			}
			$this->oProcessor = new $class( $this );
		}
		return $this->oProcessor;
	}

	/**
	 * This is the old method
	 * @deprecated 10.1
	 */
	protected function getProcessorClassName() :string {
		return implode( '_',
			[
				strtoupper( $this->getCon()->getPluginPrefix( '_' ) ),
				'Processor',
				str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getSlug() ) ) )
			]
		);
	}

	/**
	 * Override this and adapt per feature
	 * @return string
	 */
	protected function getWizardClassName() {
		return implode( '_',
			[
				strtoupper( $this->getCon()->getPluginPrefix( '_' ) ),
				'Wizard',
				str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getSlug() ) ) )
			]
		);
	}

	public function isUpgrading() :bool {
		return $this->getCon()->getIsRebuildOptionsFromFile() || $this->getOptions()->getRebuildFromFile();
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			if ( rand( 1, 40 ) === 2 ) {
				// cleanup databases randomly just in-case cron doesn't run.
				$this->cleanupDatabases();
			}
			$this->saveModOptions();
		}
	}

	public function getOptionsStorageKey() :string {
		return $this->getCon()->prefixOption( $this->sOptionsStoreKey ).'_options';
	}

	/**
	 * @return Shield\Modules\Base\BaseProcessor|\FernleafSystems\Utilities\Logic\OneTimeExecute|mixed
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	public function getUrl_AdminPage() :string {
		return Services::WpGeneral()
					   ->getUrl_AdminPage(
						   $this->getModSlug(),
						   $this->getCon()->getIsWpmsNetworkAdminOnly()
					   );
	}

	/**
	 * @param string $sAction
	 * @return string
	 */
	public function buildAdminActionNonceUrl( $sAction ) {
		$aActionNonce = $this->getNonceActionData( $sAction );
		$aActionNonce[ 'ts' ] = Services::Request()->ts();
		return add_query_arg( $aActionNonce, $this->getUrl_AdminPage() );
	}

	protected function getModActionParams( string $action ) :array {
		$con = $this->getCon();
		return [
			'action'     => $con->prefix(),
			'exec'       => $action,
			'mod_slug'   => $this->getModSlug(),
			'ts'         => Services::Request()->ts(),
			'exec_nonce' => substr(
				hash_hmac( 'md5', $action.Services::Request()->ts(), $con->getSiteInstallationId() )
				, 0, 6 )
		];
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function verifyModActionRequest() :bool {
		$bValid = false;

		$con = $this->getCon();
		$req = Services::Request();

		$sExec = $req->request( 'exec' );
		if ( !empty( $sExec ) && $req->request( 'action' ) == $con->prefix() ) {


			if ( wp_verify_nonce( $req->request( 'exec_nonce' ), $sExec ) && $con->getMeetsBasePermissions() ) {
				$bValid = true;
			}
			else {
				$bValid = $req->request( 'exec_nonce' ) ===
						  substr( hash_hmac( 'md5', $sExec.$req->request( 'ts' ), $con->getSiteInstallationId() ), 0, 6 );
			}
			if ( !$bValid ) {
				throw new Exception( 'Invalid request' );
			}
		}

		return $bValid;
	}

	public function getUrl_DirectLinkToOption( string $key ) :string {
		$url = $this->getUrl_AdminPage();
		$def = $this->getOptions()->getOptDefinition( $key );
		if ( !empty( $def[ 'section' ] ) ) {
			$url = $this->getUrl_DirectLinkToSection( $def[ 'section' ] );
		}
		return $url;
	}

	public function getUrl_DirectLinkToSection( string $section ) :string {
		if ( $section == 'primary' ) {
			$section = $this->getOptions()->getPrimarySection()[ 'slug' ];
		}
		return $this->getUrl_AdminPage().'#tab-'.$section;
	}

	/**
	 * TODO: Get rid of this crap and/or handle the \Exception thrown in loadFeatureHandler()
	 * @return ICWP_WPSF_FeatureHandler_Email
	 * @throws \Exception
	 * @deprecated 10.1
	 */
	public function getEmailHandler() {
		return $this->getCon()->getModule( 'email' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Email
	 */
	public function getEmailProcessor() {
		return $this->getEmailHandler()->getProcessor();
	}

	/**
	 * @param bool $enable
	 * @return $this
	 */
	public function setIsMainFeatureEnabled( bool $enable ) {
		$this->getOptions()->setOpt( 'enable_'.$this->getSlug(), $enable ? 'Y' : 'N' );
		return $this;
	}

	public function isModuleEnabled() :bool {
		/** @var Shield\Modules\Plugin\Options $oPluginOpts */
		$oPluginOpts = $this->getCon()->getModule_Plugin()->getOptions();

		if ( $this->getOptions()->getFeatureProperty( 'auto_enabled' ) === true ) {
			// Auto enabled modules always run regardless
			$enabled = true;
		}
		elseif ( $oPluginOpts->isPluginGloballyDisabled() ) {
			$enabled = false;
		}
		elseif ( $this->getCon()->getIfForceOffActive() ) {
			$enabled = false;
		}
		elseif ( $this->getOptions()->getFeatureProperty( 'premium' ) === true
				 && !$this->isPremium() ) {
			$enabled = false;
		}
		else {
			$enabled = $this->isModOptEnabled();
		}

		return $enabled;
	}

	public function isModOptEnabled() :bool {
		$opts = $this->getOptions();
		return $opts->isOpt( $this->getEnableModOptKey(), 'Y' )
			   || $opts->isOpt( $this->getEnableModOptKey(), true, true );
	}

	public function getEnableModOptKey() :string {
		return 'enable_'.$this->getSlug();
	}

	public function getMainFeatureName() :string {
		return __( $this->getOptions()->getFeatureProperty( 'name' ), 'wp-simple-firewall' );
	}

	public function getModSlug( bool $prefix = true ) :string {
		return $prefix ? $this->prefix( $this->getSlug() ) : $this->getSlug();
	}

	/**
	 * @return string
	 */
	public function getSlug() {
		if ( !isset( $this->sModSlug ) ) {
			$this->sModSlug = $this->getOptions()->getFeatureProperty( 'slug' );
		}
		return $this->sModSlug;
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	public function supplySubMenuItem( $aItems ) {

		$sTitle = $this->getOptions()->getFeatureProperty( 'menu_title' );
		$sTitle = empty( $sTitle ) ? $this->getMainFeatureName() : __( $sTitle, 'wp-simple-firewall' );

		if ( !empty( $sTitle ) ) {

			$sHumanName = $this->getCon()->getHumanName();

			$bMenuHighlighted = $this->getOptions()->getFeatureProperty( 'highlight_menu_item' );
			if ( $bMenuHighlighted ) {
				$sTitle = sprintf( '<span class="icwp_highlighted">%s</span>', $sTitle );
			}

			$sMenuPageTitle = $sTitle.' - '.$sHumanName;
			$aItems[ $sMenuPageTitle ] = [
				$sTitle,
				$this->getModSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$this->getIfShowModuleMenuItem()
			];

			$aAdditionalItems = $this->getOptions()->getAdditionalMenuItems();
			if ( !empty( $aAdditionalItems ) && is_array( $aAdditionalItems ) ) {

				foreach ( $aAdditionalItems as $aMenuItem ) {
					$sMenuPageTitle = $sHumanName.' - '.$aMenuItem[ 'title' ];
					$aItems[ $sMenuPageTitle ] = [
						__( $aMenuItem[ 'title' ], 'wp-simple-firewall' ),
						$this->prefix( $aMenuItem[ 'slug' ] ),
						[ $this, $aMenuItem[ 'callback' ] ],
						true
					];
				}
			}
		}
		return $aItems;
	}

	/**
	 * Handles the case where we want to redirect certain menu requests to other pages
	 * of the plugin automatically. It lets us create custom menu items.
	 * This can of course be extended for any other types of redirect.
	 */
	public function handleAutoPageRedirects() {
		$aConf = $this->getOptions()->getRawData_FullFeatureConfig();
		if ( !empty( $aConf[ 'custom_redirects' ] ) && $this->getCon()->isValidAdminArea() ) {
			foreach ( $aConf[ 'custom_redirects' ] as $aRedirect ) {
				if ( Services::Request()->query( 'page' ) == $this->prefix( $aRedirect[ 'source_mod_page' ] ) ) {
					Services::Response()->redirect(
						$this->getCon()->getModule( $aRedirect[ 'target_mod_page' ] )->getUrl_AdminPage(),
						$aRedirect[ 'query_args' ],
						true,
						false
					);
				}
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getAdditionalMenuItem() {
		return [];
	}

	/**
	 * TODO: not the place for this method.
	 * @return array[]
	 */
	public function getModulesSummaryData() {
		return array_map(
			function ( $mod ) {
				return $mod->buildSummaryData();
			},
			$this->getCon()->modules
		);
	}

	/**
	 * @return array
	 */
	public function buildSummaryData() {
		$opts = $this->getOptions();
		$sMenuTitle = $opts->getFeatureProperty( 'menu_title' );

		$aSections = $opts->getSections();
		foreach ( $aSections as $sSlug => $aSection ) {
			try {
				$aStrings = $this->getStrings()->getSectionStrings( $aSection[ 'slug' ] );
				foreach ( $aStrings as $sKey => $sVal ) {
					unset( $aSection[ $sKey ] );
					$aSection[ $sKey ] = $sVal;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$aSum = [
			'slug'          => $this->getSlug(),
			'enabled'       => $this->getUIHandler()->isEnabledForUiSummary(),
			'active'        => $this->isThisModulePage() || $this->isPage_InsightsThisModule(),
			'name'          => $this->getMainFeatureName(),
			'sidebar_name'  => $opts->getFeatureProperty( 'sidebar_name' ),
			'menu_title'    => empty( $sMenuTitle ) ? $this->getMainFeatureName() : __( $sMenuTitle, 'wp-simple-firewall' ),
			'href'          => network_admin_url( 'admin.php?page='.$this->getModSlug() ),
			'sections'      => $aSections,
			'options'       => [],
			'show_mod_opts' => $this->getIfShowModuleOpts(),
		];

		foreach ( $opts->getVisibleOptionsKeys() as $sOptKey ) {
			try {
				$aOptData = $this->getStrings()->getOptionStrings( $sOptKey );
				$aOptData[ 'href' ] = $this->getUrl_DirectLinkToOption( $sOptKey );
				$aSum[ 'options' ][ $sOptKey ] = $aOptData;
			}
			catch ( \Exception $e ) {
			}
		}

		$aSum[ 'tooltip' ] = sprintf(
			'%s',
			empty( $aSum[ 'sidebar_name' ] ) ? $aSum[ 'name' ] : __( $aSum[ 'sidebar_name' ], 'wp-simple-firewall' )
		);
		return $aSum;
	}

	/**
	 * @return bool
	 */
	public function getIfShowModuleMenuItem() {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_menu_item' );
	}

	/**
	 * @return bool
	 */
	public function getIfShowModuleOpts() {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_options' );
	}

	/**
	 * Get config 'definition'.
	 * @param string $key
	 * @return mixed|null
	 */
	public function getDef( string $key ) {
		return $this->getOptions()->getDef( $key );
	}

	/**
	 * @return $this
	 */
	public function clearLastErrors() {
		return $this->setLastErrors( [] );
	}

	/**
	 * @param bool   $bAsString
	 * @param string $sGlue
	 * @return string|array
	 */
	public function getLastErrors( $bAsString = false, $sGlue = " " ) {
		$errors = $this->getOptions()->getOpt( 'last_errors' );
		if ( !is_array( $errors ) ) {
			$errors = [];
		}
		return $bAsString ? implode( $sGlue, $errors ) : $errors;
	}

	public function hasLastErrors() :bool {
		return count( $this->getLastErrors( false ) ) > 0;
	}

	public function getTextOpt( string $key ) :string {
		$sValue = $this->getOptions()->getOpt( $key, 'default' );
		if ( $sValue == 'default' ) {
			$sValue = $this->getTextOptDefault( $key );
		}
		return __( $sValue, 'wp-simple-firewall' );
	}

	/**
	 * Override this on each feature that has Text field options to supply the text field defaults
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {
		return 'Undefined Text Opt Default';
	}

	/**
	 * @param array|string $mErrors
	 * @return $this
	 */
	public function setLastErrors( $mErrors = [] ) {
		if ( !is_array( $mErrors ) ) {
			if ( is_string( $mErrors ) ) {
				$mErrors = [ $mErrors ];
			}
			else {
				$mErrors = [];
			}
		}
		$this->getOptions()->setOpt( 'last_errors', $mErrors );
		return $this;
	}

	public function setOptions( array $options ) {
		$opts = $this->getOptions();
		foreach ( $options as $key => $value ) {
			$opts->setOpt( $key, $value );
		}
	}

	public function isModuleRequest() :bool {
		return $this->getModSlug() === Services::Request()->request( 'mod_slug' );
	}

	/**
	 * @param string $sAction
	 * @param bool   $bAsJsonEncodedObject
	 * @return array|string
	 */
	public function getAjaxActionData( $sAction = '', $bAsJsonEncodedObject = false ) {
		$aData = $this->getNonceActionData( $sAction );
		$aData[ 'ajaxurl' ] = admin_url( 'admin-ajax.php' );
		return $bAsJsonEncodedObject ? json_encode( (object)$aData ) : $aData;
	}

	/**
	 * @param string $sAction
	 * @return array
	 */
	public function getNonceActionData( $sAction = '' ) {
		$aData = $this->getCon()->getNonceActionData( $sAction );
		$aData[ 'mod_slug' ] = $this->getModSlug();
		return $aData;
	}

	/**
	 * @return string[]
	 */
	public function getDismissedNotices() :array {
		$notices = $this->getOptions()->getOpt( 'dismissed_notices' );
		return is_array( $notices ) ? $notices : [];
	}

	/**
	 * @return string[]
	 */
	public function getUiTrack() :array {
		$a = $this->getOptions()->getOpt( 'ui_track' );
		return is_array( $a ) ? $a : [];
	}

	public function setDismissedNotices( array $dis ) {
		$this->getOptions()->setOpt( 'dismissed_notices', $dis );
	}

	public function setUiTrack( array $UI ) {
		$this->getOptions()->setOpt( 'ui_track', $UI );
	}

	/**
	 * @return \ICWP_WPSF_Wizard_Base|null
	 */
	public function getWizardHandler() {
		if ( !isset( $this->oWizard ) ) {
			$sClassName = $this->getWizardClassName();
			if ( !class_exists( $sClassName ) ) {
				return null;
			}
			$this->oWizard = new $sClassName();
			$this->oWizard->setMod( $this );
		}
		return $this->oWizard;
	}

	/**
	 * @param bool $bPreProcessOptions
	 * @return $this
	 */
	public function saveModOptions( $bPreProcessOptions = false ) {

		if ( $bPreProcessOptions ) {
			$this->preProcessOptions();
		}

		$this->doPrePluginOptionsSave();
		if ( apply_filters( $this->prefix( 'force_options_resave' ), false ) ) {
			$this->getOptions()
				 ->setNeedSave( true );
		}

		// we set the flag that options have been updated. (only use this flag if it's a MANUAL options update)
		$this->bImportExportWhitelistNotify = $this->getOptions()->getNeedSave();
		$this->store();
		return $this;
	}

	protected function preProcessOptions() {
	}

	private function store() {
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		$this->getOptions()
			 ->doOptionsSave( $this->getCon()->getIsResetPlugin(), $this->isPremium() );
		remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
	}

	/**
	 * @param array $aAggregatedOptions
	 * @return array
	 */
	public function aggregateOptionsValues( $aAggregatedOptions ) {
		return array_merge( $aAggregatedOptions, $this->getOptions()->getAllOptionsValues() );
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	public function onPluginDeactivate() {
	}

	public function onPluginDelete() {
		foreach ( $this->getDbHandlers( true ) as $oDbh ) {
			if ( !empty( $oDbh ) ) {
				$oDbh->tableDelete();
			}
		}
		$this->getOptions()->deleteStorage();
	}

	/**
	 * @return array - map of each option to its option type
	 */
	protected function getAllFormOptionsAndTypes() {
		$aOpts = [];

		foreach ( $this->getUIHandler()->buildOptions() as $aOptionsSection ) {
			if ( !empty( $aOptionsSection ) ) {
				foreach ( $aOptionsSection[ 'options' ] as $aOption ) {
					$aOpts[ $aOption[ 'key' ] ] = $aOption[ 'type' ];
				}
			}
		}

		return $aOpts;
	}

	protected function handleModAction( string $sAction ) {
	}

	/**
	 * @throws \Exception
	 */
	public function saveOptionsSubmit() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
		}

		$this->doSaveStandardOptions();

		$this->saveModOptions( true );

		// only use this flag when the options are being updated with a MANUAL save.
		if ( isset( $this->bImportExportWhitelistNotify ) && $this->bImportExportWhitelistNotify ) {
			if ( !wp_next_scheduled( $this->prefix( 'importexport_notify' ) ) ) {
				wp_schedule_single_event( Services::Request()->ts() + 15, $this->prefix( 'importexport_notify' ) );
			}
		}
	}

	/**
	 * @param string $sMsg
	 * @param bool   $bError
	 * @param bool   $bShowOnLogin
	 * @return $this
	 */
	public function setFlashAdminNotice( $sMsg, $bError = false, $bShowOnLogin = false ) {
		$this->getCon()
			 ->getAdminNotices()
			 ->addFlash(
				 sprintf( '[%s] %s', $this->getCon()->getHumanName(), $sMsg ),
				 $bError,
				 $bShowOnLogin
			 );
		return $this;
	}

	protected function isAdminOptionsPage() :bool {
		return is_admin() && !Services::WpGeneral()->isAjax() && $this->isThisModulePage();
	}

	/**
	 * @return bool
	 */
	public function isPremium() {
		return $this->getCon()->isPremiumActive();
	}

	/**
	 * @throws \Exception
	 */
	private function doSaveStandardOptions() {
		$aForm = $this->getAjaxFormParams( 'b64' ); // standard options use b64 and failover to lz-string

		foreach ( $this->getAllFormOptionsAndTypes() as $sKey => $sOptType ) {

			$sOptionValue = isset( $aForm[ $sKey ] ) ? $aForm[ $sKey ] : null;
			if ( is_null( $sOptionValue ) ) {

				if ( in_array( $sOptType, [ 'text', 'email' ] ) ) { //text box, and it's null, don't update
					continue;
				}
				elseif ( $sOptType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				}
				elseif ( $sOptType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
				elseif ( $sOptType == 'multiple_select' ) {
					$sOptionValue = [];
				}
			}
			else { //handle any pre-processing we need to.

				if ( $sOptType == 'text' || $sOptType == 'email' ) {
					$sOptionValue = trim( $sOptionValue );
				}
				if ( $sOptType == 'integer' ) {
					$sOptionValue = intval( $sOptionValue );
				}
				elseif ( $sOptType == 'password' ) {
					$sTempValue = trim( $sOptionValue );
					if ( empty( $sTempValue ) ) {
						continue;
					}

					$sConfirm = isset( $aForm[ $sKey.'_confirm' ] ) ? $aForm[ $sKey.'_confirm' ] : null;
					if ( $sTempValue !== $sConfirm ) {
						throw new \Exception( __( 'Password values do not match.', 'wp-simple-firewall' ) );
					}

					$sOptionValue = md5( $sTempValue );
				}
				elseif ( $sOptType == 'array' ) { //arrays are textareas, where each is separated by newline
					$sOptionValue = array_filter( explode( "\n", esc_textarea( $sOptionValue ) ), 'trim' );
				}
				elseif ( $sOptType == 'comma_separated_lists' ) {
					$sOptionValue = Services::Data()->extractCommaSeparatedList( $sOptionValue );
				}
				/* elseif ( $sOptType == 'multiple_select' ) { } */
			}

			// Prevent overwriting of non-editable fields
			if ( !in_array( $sOptType, [ 'noneditable_text' ] ) ) {
				$this->getOptions()->setOpt( $sKey, $sOptionValue );
			}
		}

		// Handle Import/Export exclusions
		if ( $this->isPremium() ) {
			( new Shield\Modules\Plugin\Lib\ImportExport\Options\SaveExcludedOptions() )
				->setMod( $this )
				->save( $aForm );
		}
	}

	protected function runWizards() {
		if ( $this->isWizardPage() && $this->hasWizard() ) {
			$oWiz = $this->getWizardHandler();
			if ( $oWiz instanceof ICWP_WPSF_Wizard_Base ) {
				$oWiz->init();
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isThisModulePage() {
		return $this->getCon()->isModulePage()
			   && Services::Request()->query( 'page' ) == $this->getModSlug();
	}

	/**
	 * @return bool
	 */
	public function isPage_Insights() {
		return Services::Request()->query( 'page' ) == $this->getCon()->getModule_Insights()->getModSlug();
	}

	/**
	 * @return bool
	 */
	public function isPage_InsightsThisModule() {
		return $this->isPage_Insights()
			   && Services::Request()->query( 'subnav' ) == $this->getSlug();
	}

	/**
	 * @return bool
	 */
	protected function isModuleOptionsRequest() {
		return Services::Request()->post( 'mod_slug' ) === $this->getModSlug();
	}

	/**
	 * @return bool
	 */
	protected function isWizardPage() {
		return ( $this->getCon()->getShieldAction() == 'wizard' && $this->isThisModulePage() );
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function prefix( $sSuffix = '', $sGlue = '-' ) {
		return $this->getCon()->prefix( $sSuffix, $sGlue );
	}

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		echo $this->renderModulePage();
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $aData
	 * @return string
	 */
	protected function renderModulePage( array $aData = [] ) :string {
		return $this->renderTemplate(
			'index.php',
			Services::DataManipulation()->mergeArraysRecursive( $this->getUIHandler()->getBaseDisplayData(), $aData )
		);
	}

	/**
	 * @return string
	 */
	protected function getContentWizardLanding() {
		$aData = $this->getUIHandler()->getBaseDisplayData();
		if ( $this->hasWizard() ) {
			$aData[ 'content' ][ 'wizard_landing' ] = $this->getWizardHandler()->renderWizardLandingSnippet();
		}
		return $this->renderTemplate( 'snippets/module-wizard-template.php', $aData );
	}

	protected function buildContextualHelp() {
		if ( !function_exists( 'get_current_screen' ) ) {
			require_once( ABSPATH.'wp-admin/includes/screen.php' );
		}
		$screen = get_current_screen();
		//$screen->remove_help_tabs();
		$screen->add_help_tab( [
			'id'      => 'my-plugin-default',
			'title'   => __( 'Default' ),
			'content' => 'This is where I would provide tabbed help to the user on how everything in my admin panel works. Formatted HTML works fine in here too'
		] );
		//add more help tabs as needed with unique id's

		// Help sidebars are optional
		$screen->set_help_sidebar(
			'<p><strong>'.__( 'For more information:' ).'</strong></p>'.
			'<p><a href="http://wordpress.org/support/" target="_blank">'._( 'Support Forums' ).'</a></p>'
		);
	}

	/**
	 * @param string $sWizardSlug
	 * @return string
	 * @uses nonce
	 */
	public function getUrl_Wizard( $sWizardSlug ) {
		$aDef = $this->getWizardDefinition( $sWizardSlug );
		if ( empty( $aDef[ 'min_user_permissions' ] ) ) { // i.e. no login/minimum perms
			$sUrl = Services::WpGeneral()->getHomeUrl();
		}
		else {
			$sUrl = Services::WpGeneral()->getAdminUrl( 'admin.php' );
		}

		return add_query_arg(
			[
				'page'          => $this->getModSlug(),
				'shield_action' => 'wizard',
				'wizard'        => $sWizardSlug,
				'nonwizard'     => wp_create_nonce( 'wizard'.$sWizardSlug )
			],
			$sUrl
		);
	}

	/**
	 * @return string
	 */
	public function getUrl_WizardLanding() {
		return $this->getUrl_Wizard( 'landing' );
	}

	/**
	 * @param string $sWizardSlug
	 * @return array
	 */
	public function getWizardDefinition( $sWizardSlug ) {
		$aDef = null;
		if ( $this->hasWizardDefinition( $sWizardSlug ) ) {
			$aW = $this->getWizardDefinitions();
			$aDef = $aW[ $sWizardSlug ];
		}
		return $aDef;
	}

	/**
	 * @return array
	 */
	public function getWizardDefinitions() {
		$aW = $this->getDef( 'wizards' );
		return is_array( $aW ) ? $aW : [];
	}

	public function hasWizard() :bool {
		return count( $this->getWizardDefinitions() ) > 0;
	}

	/**
	 * @param string $sWizardSlug
	 * @return bool
	 */
	public function hasWizardDefinition( $sWizardSlug ) {
		$aW = $this->getWizardDefinitions();
		return !empty( $aW[ $sWizardSlug ] );
	}

	/**
	 * @return bool
	 */
	public function getIsShowMarketing() {
		return apply_filters( $this->prefix( 'show_marketing' ), !$this->isPremium() );
	}

	/**
	 * @return string
	 */
	public function renderOptionsForm() {

		if ( $this->canDisplayOptionsForm() ) {
			$sTemplate = 'components/options_form/main.twig';
		}
		else {
			$sTemplate = 'subfeature-access_restricted';
		}

		try {
			return $this->getCon()
						->getRenderer()
						->setTemplate( $sTemplate )
						->setRenderVars( $this->getUIHandler()->getBaseDisplayData() )
						->setTemplateEngineTwig()
						->render();
		}
		catch ( \Exception $oE ) {
			return 'Error rendering options form: '.$oE->getMessage();
		}
	}

	/**
	 * @return bool
	 */
	public function canDisplayOptionsForm() {
		return $this->getOptions()->isAccessRestricted() ? $this->getCon()->isPluginAdmin() : true;
	}

	public function onWpEnqueueAdminJs() {
		$this->insertCustomJsVars_Admin();
	}

	/**
	 * Override this with custom JS vars for your particular module.
	 */
	public function insertCustomJsVars_Admin() {

		if ( $this->isThisModulePage() ) {
			wp_localize_script(
				$this->prefix( 'plugin' ),
				'icwp_wpsf_vars_base',
				[
					'ajax' => [
						'mod_options'          => $this->getAjaxActionData( 'mod_options' ),
						'mod_opts_form_render' => $this->getAjaxActionData( 'mod_opts_form_render' ),
					]
				]
			);
		}
	}

	/**
	 * @param array  $aData
	 * @param string $sSubView
	 */
	protected function display( $aData = [], $sSubView = '' ) {
	}

	/**
	 * @param array $aData
	 * @return string
	 * @throws \Exception
	 */
	public function renderAdminNotice( $aData ) {
		if ( empty( $aData[ 'notice_attributes' ] ) ) {
			throw new \Exception( 'notice_attributes is empty' );
		}

		if ( !isset( $aData[ 'icwp_admin_notice_template' ] ) ) {
			$aData[ 'icwp_admin_notice_template' ] = $aData[ 'notice_attributes' ][ 'notice_id' ];
		}

		if ( !isset( $aData[ 'notice_classes' ] ) ) {
			$aData[ 'notice_classes' ] = [];
		}
		if ( is_array( $aData[ 'notice_classes' ] ) ) {
			$aData[ 'notice_classes' ][] = $aData[ 'notice_attributes' ][ 'type' ];
			if ( empty( $aData[ 'notice_classes' ] )
				 || ( !in_array( 'error', $aData[ 'notice_classes' ] ) && !in_array( 'updated', $aData[ 'notice_classes' ] ) ) ) {
				$aData[ 'notice_classes' ][] = 'updated';
			}
		}
		$aData[ 'notice_classes' ] = implode( ' ', $aData[ 'notice_classes' ] );

		$aAjaxData = $this->getAjaxActionData( 'dismiss_admin_notice' );
		$aAjaxData[ 'hide' ] = 1;
		$aAjaxData[ 'notice_id' ] = $aData[ 'notice_attributes' ][ 'notice_id' ];
		$aData[ 'ajax' ][ 'dismiss_admin_notice' ] = json_encode( $aAjaxData );

		$bTwig = $aData[ 'notice_attributes' ][ 'twig' ];
		$sTemplate = $bTwig ? '/notices/'.$aAjaxData[ 'notice_id' ] : 'notices/admin-notice-template';
		return $this->renderTemplate( $sTemplate, $aData, $bTwig );
	}

	public function renderTemplate( string $template, array $data = [], bool $isTwig = false ) :string {
		if ( empty( $data[ 'unique_render_id' ] ) ) {
			$data[ 'unique_render_id' ] = 'noticeid-'.substr( md5( mt_rand() ), 0, 5 );
		}
		try {
			$oRndr = $this->getCon()->getRenderer();
			if ( $isTwig || preg_match( '#^.*\.twig$#i', $template ) ) {
				$oRndr->setTemplateEngineTwig();
			}

			$data[ 'strings' ] = Services::DataManipulation()
										 ->mergeArraysRecursive(
											 $this->getStrings()->getDisplayStrings(),
											 $data[ 'strings' ] ?? []
										 );

			$render = $oRndr->setTemplate( $template )
							->setRenderVars( $data )
							->render();
		}
		catch ( \Exception $oE ) {
			$render = $oE->getMessage();
			error_log( $oE->getMessage() );
		}

		return (string)$render;
	}

	/**
	 * @param array $aTransferableOptions
	 * @return array
	 */
	public function exportTransferableOptions( $aTransferableOptions ) {
		if ( !is_array( $aTransferableOptions ) ) {
			$aTransferableOptions = [];
		}
		$aTransferableOptions[ $this->getOptionsStorageKey() ] = $this->getOptions()->getTransferableOptions();
		return $aTransferableOptions;
	}

	public function collectOptionsForTracking() :array {
		$opts = $this->getOptions();
		$aOptionsData = $this->getOptions()->getOptionsForTracking();
		foreach ( $aOptionsData as $opt => $mValue ) {
			unset( $aOptionsData[ $opt ] );
			// some cleaning to ensure we don't have disallowed characters
			$opt = preg_replace( '#[^_a-z]#', '', strtolower( $opt ) );
			$sType = $opts->getOptionType( $opt );
			if ( $sType == 'checkbox' ) { // only want a boolean 1 or 0
				$aOptionsData[ $opt ] = (int)( $mValue == 'Y' );
			}
			else {
				$aOptionsData[ $opt ] = $mValue;
			}
		}
		return $aOptionsData;
	}

	public function getMainWpData() :array {
		return [
			'options' => $this->getOptions()->getTransferableOptions()
		];
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 * @param array  $aExportItems
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyExport( $aExportItems, $sEmail, $nPage = 1 ) {
		return $aExportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 * @param array  $aData
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyErase( $aData, $sEmail, $nPage = 1 ) {
		return $aData;
	}

	/**
	 * @return null|Shield\Modules\Base\ShieldOptions|mixed
	 */
	public function getOptions() {
		if ( !isset( $this->oOpts ) ) {
			$oCon = $this->getCon();
			$this->oOpts = $this->loadModElement( 'Options' );
			$this->oOpts->setPathToConfig( $oCon->getPath_ConfigFile( $this->getSlug() ) )
						->setRebuildFromFile( $oCon->getIsRebuildOptionsFromFile() )
						->setOptionsStorageKey( $this->getOptionsStorageKey() )
						->setIfLoadOptionsFromStorage( !$oCon->getIsResetPlugin() );
		}
		return $this->oOpts;
	}

	/**
	 * @return Shield\Modules\Base\WpCli
	 * @throws \Exception
	 */
	public function getWpCli() {
		if ( !isset( $this->oWpCli ) ) {
			$this->oWpCli = $this->loadModElement( 'WpCli' );
			if ( !$this->oWpCli instanceof Shield\Modules\Base\WpCli ) {
				throw new \Exception( 'WP-CLI not supported' );
			}
		}
		return $this->oWpCli;
	}

	/**
	 * @return null|Shield\Modules\Base\Strings
	 */
	public function getStrings() {
		return $this->loadStrings()->setMod( $this );
	}

	/**
	 * @return Shield\Modules\Base\UI
	 */
	public function getUIHandler() {
		if ( !isset( $this->oUI ) ) {
			$this->oUI = $this->loadModElement( 'UI' );
			if ( !$this->oUI instanceof Shield\Modules\Base\UI ) {
				// TODO: autoloader for base classes
				$this->oUI = $this->loadModElement( 'ShieldUI' );
			}
		}
		return $this->oUI;
	}

	/**
	 * @return Shield\Modules\Base\BaseReporting|mixed|false
	 */
	public function getReportingHandler() {
		if ( !isset( $this->oReporting ) ) {
			$this->oReporting = $this->loadModElement( 'Reporting' );
		}
		return $this->oReporting;
	}

	protected function loadAdminNotices() {
		$N = $this->loadModElement( 'AdminNotices' );
		if ( $N instanceof Shield\Modules\Base\AdminNotices ) {
			$N->run();
		}
	}

	protected function loadAjaxHandler() {
		$oAj = $this->loadModElement( 'AjaxHandler' );
		if ( !$oAj instanceof Shield\Modules\Base\AjaxHandlerBase ) {
			$this->loadModElement( 'AjaxHandlerShield' );
		}
	}

	/**
	 * @return Shield\Modules\Base\ShieldOptions|mixed
	 * @deprecated 10.1
	 */
	protected function loadOptions() {
		return $this->loadModElement( 'Options' );
	}

	protected function loadDebug() {
		$req = Services::Request();
		if ( $req->query( 'debug' ) && $req->query( 'mod' ) == $this->getModSlug()
			 && $this->getCon()->isPluginAdmin() ) {
			/** @var Shield\Modules\Base\Debug $debug */
			$debug = $this->loadModElement( 'Debug', true );
			$debug->run();
		}
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return $this->loadModElement( 'Strings', true );
	}

	/**
	 * @param $sClass
	 * @return \stdClass|mixed|false
	 * @deprecated 10.1
	 */
	private function loadClass( $sClass ) {
		$sC = $this->getNamespace().$sClass;
		return @class_exists( $sC ) ? new $sC() : false;
	}

	/**
	 * @param string $class
	 * @param false  $injectMod
	 * @return false|Shield\Modules\ModConsumer
	 */
	private function loadModElement( string $class, $injectMod = true ) {
		$element = false;
		try {
			$C = $this->findElementClass( $class, true );
			/** @var Shield\Modules\ModConsumer $element */
			$element = @class_exists( $C ) ? new $C() : false;
			if ( $injectMod && method_exists( $element, 'setMod' ) ) {
				$element->setMod( $this );
			}
		}
		catch ( \Exception $e ) {
		}
		return $element;
	}

	/**
	 * @param $sClass
	 * @return \stdClass|mixed|false
	 * @deprecated 10.1
	 */
	private function loadClassFromBase( $sClass ) {
		$sC = $this->getBaseNamespace().$sClass;
		return @class_exists( $sC ) ? new $sC() : false;
	}

	/**
	 * @param string $element
	 * @param bool   $bThrowException
	 * @return string|null
	 * @throws \Exception
	 */
	protected function findElementClass( string $element, $bThrowException = true ) {
		$theClass = null;

		foreach ( $this->getNamespaceRoots() as $NS ) {
			$maybe = $NS.$element;
			if ( @class_exists( $maybe ) ) {
				if ( ( new ReflectionClass( $maybe ) )->isInstantiable() ) {
					$theClass = $maybe;
					break;
				}
			}
		}

		if ( $bThrowException && is_null( $theClass ) ) {
			throw new \Exception( sprintf( 'Could not find class for element "%s".', $element ) );
		}
		return $theClass;
	}

	private function getBaseNamespace() {
		return $this->getModulesNamespace().'Base\\';
	}

	protected function getModulesNamespace() :string {
		return '\FernleafSystems\Wordpress\Plugin\Shield\Modules\\';
	}

	protected function getNamespace() :string {
		return $this->getModulesNamespace().$this->getNamespaceBase().'\\';
	}

	protected function getNamespaceRoots() :array {
		return [
			$this->getNamespace(),
			$this->getBaseNamespace()
		];
	}

	protected function getNamespaceBase() :string {
		return 'Base';
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * @return void
	 * @deprecated 8.4
	 */
	public function savePluginOptions() {
		$this->saveModOptions();
	}
}