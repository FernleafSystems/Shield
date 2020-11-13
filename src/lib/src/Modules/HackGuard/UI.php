<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aUiTrack = $mod->getUiTrack();
		if ( empty( $aUiTrack[ 'selected_scans' ] ) ) {
			$aUiTrack[ 'selected_scans' ] = $opts->getScanSlugs();
		}

		// Can Scan Checks:
		$aReasonCantScan = $mod->getScansCon()->getReasonsScansCantExecute();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $mod->getDbHandler_ScanResults()->getQuerySelector();
		$data = [
			'ajax'         => [
				'scans_start'           => $mod->getAjaxActionData( 'scans_start', true ),
				'scans_check'           => $mod->getAjaxActionData( 'scans_check', true ),
				'render_table_scan'     => $mod->getAjaxActionData( 'render_table_scan', true ),
				'bulk_action'           => $mod->getAjaxActionData( 'bulk_action', true ),
				'item_asset_deactivate' => $mod->getAjaxActionData( 'item_asset_deactivate', true ),
				'item_asset_reinstall'  => $mod->getAjaxActionData( 'item_asset_reinstall', true ),
				'item_delete'           => $mod->getAjaxActionData( 'item_delete', true ),
				'item_ignore'           => $mod->getAjaxActionData( 'item_ignore', true ),
				'item_repair'           => $mod->getAjaxActionData( 'item_repair', true ),
				'item_action'           => $mod->getAjaxActionData( 'item_action', true ),
			],
			'flags'        => [
				'is_premium' => $this->getCon()->isPremiumActive(),
				'can_scan'   => count( $aReasonCantScan ) === 0,
			],
			'strings'      => [
				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'click_see_results'     => __( 'Click a scan to see its results', 'wp-simple-firewall' ),
				'title_scan_site_now'   => __( 'Scan Your Site Now', 'wp-simple-firewall' ),
				'title_scan_now'        => __( 'Scan Your Site Now', 'wp-simple-firewall' ),
				'subtitle_scan_now'     => __( 'Run the selected scans on your site now to get the latest results', 'wp-simple-firewall' ),
				'more_items_longer'     => __( 'The more scans that are selected, the longer the scan may take.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'scanselect'            => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'scanselect_file_areas' => __( 'Select File Scans To Run', 'wp-simple-firewall' ),
				'scanselect_assets'     => __( 'Select Scans For Plugins and Themes', 'wp-simple-firewall' ),
				'select_view_results'   => __( 'View Scan Results', 'wp-simple-firewall' ),
				'select_what_to_scan'   => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'clear_suppression'     => __( 'Remove Notification Suppression', 'wp-simple-firewall' ),
				'clear_suppression_sub' => __( 'Allow notification emails to be resent (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'no_entries_to_display' => __( "The previous scan either didn't detect any items that require your attention or they've already been repaired.", 'wp-simple-firewall' ),
				'scan_progress'         => __( 'Scan Progress', 'wp-simple-firewall' ),
				'reason_not_call_self'  => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
			],
			'vars'         => [
				'initial_check'       => $mod->getScanQueueController()->hasRunningScans(),
				'cannot_scan_reasons' => $aReasonCantScan,
				'related_hrefs'       => [
					[
						'href'  => $this->getCon()->getModule_HackGuard()->getUrl_AdminPage(),
						'title' => __( 'Scan Settings', 'wp-simple-firewall' ),
					],
				]
			],
			'scan_results' => [
			],
			'aggregate'    => [
				'flags'   => [
					'has_items' => true,
				],
				'hrefs'   => [
					'options' => $mod->getUrl_DirectLinkToSection( 'section_scan_options' )
				],
				'vars'    => [
				],
				'strings' => [
					'title'    => __( 'File Scan', 'wp-simple-firewall' ),
					'subtitle' => __( "Results of all file scans", 'wp-simple-firewall' )
				],
				'count'   => $oSelector->filterByScans( [ 'ptg', 'mal', 'wcf', 'ufc' ] )
									   ->filterByNotIgnored()
									   ->count()
			],
			'file_locker'  => $this->getFileLockerVars(),
			'scans'        => [
				'apc' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => true,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Discover plugins that may have been abandoned by their authors", 'wp-simple-firewall' )
					],
				],
				'wcf' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => false,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Detect changes to core WordPress files when compared to the official distribution", 'wp-simple-firewall' ),
					],
				],
				'ufc' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => false,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Detect files which aren't part of the official WordPress.org distribution", 'wp-simple-firewall' )
					],
				],
				'mal' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => false,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Detect files that may be infected with malware", 'wp-simple-firewall' )
					],
				],
				'ptg' => $this->getInsightVarsScan_Ptg(),
				'wpv' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => true,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Be alerted to plugins and themes with known security vulnerabilities", 'wp-simple-firewall' )
					],
				],
			],
		];

		/** @var Strings $strings */
		$strings = $mod->getStrings();
		$name = $strings->getScanNames();
		foreach ( $data[ 'scans' ] as $slug => &$scData ) {
			try {
				$scon = $mod->getScanCon( $slug );
			}
			catch ( \Exception $e ) {
				continue;
			}
			$lastScanAt = $scon->getLastScanAt();

			$scData[ 'flags' ][ 'is_available' ] = $scon->isScanningAvailable();
			$scData[ 'flags' ][ 'is_restricted' ] = !$scon->isScanningAvailable();
			$scData[ 'flags' ][ 'is_enabled' ] = $scon->isEnabled();
			$scData[ 'flags' ][ 'is_selected' ] = $scon->isScanningAvailable() && in_array( $slug, $aUiTrack[ 'selected_scans' ] );
			$scData[ 'vars' ][ 'last_scan_at_ts' ] = $lastScanAt;
			$scData[ 'flags' ][ 'has_last_scan' ] = $lastScanAt > 0;
			$scData[ 'vars' ][ 'last_scan_at' ] = sprintf(
				__( 'Last Scan: %s', 'wp-simple-firewall' ),
				( $lastScanAt > 0 ) ?
					Services::Request()->carbon()->setTimestamp( $lastScanAt )->diffForHumans()
					: __( 'Never', 'wp-simple-firewall' )
			);
			$scData[ 'strings' ][ 'title' ] = $name[ $slug ];
			$scData[ 'hrefs' ][ 'options' ] = $mod->getUrl_DirectLinkToSection( 'section_scan_'.$slug );
			$scData[ 'hrefs' ][ 'please_enable' ] = $mod->getUrl_DirectLinkToSection( 'section_scan_'.$slug );
			$scData[ 'count' ] = $oSelector->countForScan( $slug );
		}

		return $data;
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {
		$aOptParams = parent::buildOptionForUi( $aOptParams );
		if ( $aOptParams[ 'key' ] === 'file_locker' && !Services::Data()->isWindows() ) {
			$aOptParams[ 'value_options' ][ 'root_webconfig' ] .= sprintf( ' (%s)', __( 'unavailable', 'wp-simple-firewall' ) );
		}
		return $aOptParams;
	}

	/**
	 * @return array
	 */
	protected function getFileLockerVars() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$oLockCon = $mod->getFileLocker();
		$oLockLoader = ( new Lib\FileLocker\Ops\LoadFileLocks() )->setMod( $mod );
		$aProblemLocks = $oLockLoader->withProblems();
		$aGoodLocks = $oLockLoader->withoutProblems();

		return [
			'ajax'    => [
				'filelocker_showdiff'   => $mod->getAjaxActionData( 'filelocker_showdiff', true ),
				'filelocker_fileaction' => $mod->getAjaxActionData( 'filelocker_fileaction', true ),
			],
			'flags'   => [
				'is_enabled'    => $oLockCon->isEnabled(),
				'is_restricted' => !$this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'options'       => $mod->getUrl_DirectLinkToSection( 'section_realtime' ),
				'please_enable' => $mod->getUrl_DirectLinkToSection( 'section_realtime' ),
			],
			'vars'    => [
				'file_locks' => [
					'good' => $aGoodLocks,
					'bad'  => $aProblemLocks,
				],
			],
			'strings' => [
				'title'         => __( 'File Locker', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Results of file locker monitoring', 'wp-simple-firewall' ),
				'please_select' => __( 'Please select a file to review.', 'wp-simple-firewall' ),
			],
			'count'   => count( $aProblemLocks )
		];
	}

	/**
	 * @return array
	 */
	private function getInsightVarsScan_Ptg() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $mod->getDbHandler_ScanResults()->getQuerySelector();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aPtgResults */
		$aPtgResults = $oSelector->filterByNotIgnored()
								 ->filterByScan( 'ptg' )
								 ->query();

		return [
			'flags'   => [
				'has_items'   => $mod->isPtgEnabled() ? !empty( $aPtgResults ) : false,
				'has_plugins' => !empty( $aPlugins ),
				'has_themes'  => !empty( $aThemes ),
				'show_table'  => false,
			],
			'hrefs'   => [],
			'vars'    => [],
			'strings' => [
				'subtitle'            => __( "Detects unauthorized changes to plugins/themes", 'wp-simple-firewall' ),
				'files_with_problems' => __( 'Files with problems', 'wp-simple-firewall' ),
				'root_dir'            => __( 'Root directory', 'wp-simple-firewall' ),
				'date_snapshot'       => __( 'Snapshot taken', 'wp-simple-firewall' ),
				'reinstall'           => __( 'Re-Install', 'wp-simple-firewall' ),
				'deactivate'          => __( 'Deactivate and Ignore', 'wp-simple-firewall' ),
				'accept'              => __( 'Accept', 'wp-simple-firewall' ),
				'update'              => __( 'Upgrade', 'wp-simple-firewall' ),
			]
		];
	}

	protected function getSectionWarnings( string $section ) :array {
		$aWarnings = [];

		switch ( $section ) {

			case 'section_realtime':
				$bCanHandshake = $this->getCon()
									  ->getModule_Plugin()
									  ->getShieldNetApiController()
									  ->canHandshake();
				if ( !$bCanHandshake ) {
					$aWarnings[] = sprintf( __( 'Not available as your site cannot handshake with ShieldNET API.', 'wp-simple-firewall' ), 'OpenSSL' );
				}
//				if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
//					$aWarnings[] = sprintf( __( 'Not available because the %s extension is not available.', 'wp-simple-firewall' ), 'OpenSSL' );
//				}
//				if ( !Services::WpFs()->isFilesystemAccessDirect() ) {
//					$aWarnings[] = sprintf( __( "Not available because PHP/WordPress doesn't have direct filesystem access.", 'wp-simple-firewall' ), 'OpenSSL' );
//				}
				break;
		}

		return $aWarnings;
	}

	protected function getSettingsRelatedLinks() :array {
		$modInsights = $this->getCon()->getModule_Insights();
		return [
			[
				'href'  => $modInsights->getUrl_SubInsightsPage( 'scans' ),
				'title' => __( 'Run Scans', 'wp-simple-firewall' ),
			]
		];
	}
}