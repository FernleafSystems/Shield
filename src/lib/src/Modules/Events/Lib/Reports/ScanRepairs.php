<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class ScanRepairs extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$aAlerts = [];

		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var DBEvents\Select $oSelEvts */
		$oSelEvts = $oMod->getDbHandler_Events()->getQuerySelector();
		/** @var Events\Strings $oStrings */
		$oStrings = $oMod->getStrings();

		/** @var Options $oHGOptions */
		$oHGOptions = $this->getCon()->getModule_HackGuard()->getOptions();

		$aEventKeys = [
			'ip_offense',
			'ip_blocked',
			'conn_kill',
			'firewall_block',
			'bottrack_404',
			'bottrack_fakewebcrawler',
			'bottrack_linkcheese',
			'bottrack_loginfailed',
			'bottrack_logininvalid',
			'bottrack_xmlrpc',
			'spam_block_bot',
			'spam_block_recaptcha',
			'spam_block_human',
		];

		$oRep = $this->getReport();

		$aCounts = [];
		foreach ( $oHGOptions->getScanSlugs() as $sScan ) {
			try {
				$sEvt = $sScan.'_item_repair_success';
				$nCount = $oSelEvts
					->filterByEvent( $sEvt )
					->filterByBoundary( $oRep->interval_start_at, $oRep->interval_end_at )
					->count();
				if ( $nCount > 0 ) {
					$aCounts[ $sScan ] = [
						'count' => $nCount,
						'name'  => $oStrings->getEventName( $sEvt ),
					];
				}
			}
			catch ( \Exception $oE ) {
			}
		}

		if ( count( $aCounts ) > 0 ) {
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/events/info_keystats.twig',
				[
					'vars'    => [
						'counts' => $aCounts
					],
					'strings' => [
						'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
					],
				]
			);
		}

		return $aAlerts;
	}
}