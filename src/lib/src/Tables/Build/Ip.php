<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Ip
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class Ip extends BaseBuild {

	/**
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$aParams = $this->getParams();

		/** @var IPs\Select $oSelector */
		$oSelector = $this->getWorkingSelector();
		$oSelector->filterByLists( $aParams[ 'fLists' ] );
		if ( Services::IP()->isValidIp( $aParams[ 'fIp' ] ) ) {
			$oSelector->filterByIp( $aParams[ 'fIp' ] );
		}

		$oSelector->setOrderBy( 'last_access_at', 'DESC', true );
		$oSelector->setOrderBy( 'created_at', 'DESC', false );

		return $this;
	}

	protected function getCustomParams() :array {
		return [
			'fLists' => '',
			'fIp'    => '',
		];
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$srvIP = Services::IP();

		$nTransLimit = $opts->getOffenseLimit();
		$you = $srvIP->getRequestIp();
		$aEntries = [];

		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var IPs\EntryVO $oEntry */
			$aE = $oEntry->getRawDataAsArray();
			$bBlocked = $oEntry->blocked_at > 0 || $oEntry->transgressions >= $nTransLimit;
			$aE[ 'last_trans_at' ] = Services::Request()
											 ->carbon( true )
											 ->setTimestamp( $oEntry->last_access_at )
											 ->diffForHumans();
			$aE[ 'last_access_at' ] = $this->formatTimestampField( $oEntry->last_access_at );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'blocked' ] = $bBlocked ? __( 'Yes' ) : __( 'No' );
			$aE[ 'expires_at' ] = $this->formatTimestampField( $oEntry->last_access_at + $opts->getAutoExpireTime() );
			$aE[ 'is_you' ] = $srvIP->checkIp( $you, $oEntry->ip );
			$aE[ 'ip' ] = sprintf( '%s%s',
				$this->getIpAnalysisLink( $oEntry->ip ),
				$aE[ 'is_you' ] ? ' <span class="small">('.__( 'You', 'wp-simple-firewall' ).')</span>' : ''
			);

			$aEntries[ $nKey ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\WpListTable\IpBlack|Tables\Render\WpListTable\IpWhite
	 */
	protected function getTableRenderer() {
		$aLists = $this->getParams()[ 'fLists' ];
		if ( empty( $aLists ) || in_array( ModCon::LIST_MANUAL_WHITE, $aLists ) ) {
			$sTable = new Tables\Render\WpListTable\IpWhite();
		}
		else {
			$sTable = new Tables\Render\WpListTable\IpBlack();
		}
		return $sTable;
	}
}