<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Count', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/traffic_entry_base.php' );

class ICWP_WPSF_Query_TrafficEntry_Count extends ICWP_WPSF_Query_TrafficEntry_Base {

	public function __construct() {
		$this->init();
	}

	/**
	 * @return ICWP_WPSF_TrafficEntryVO[]|stdClass[]
	 */
	public function all() {
		return $this->query_retrieve();
	}

	/**
	 * @param int $nId
	 * @return ICWP_WPSF_TrafficEntryVO|stdClass
	 */
	public function retrieveById( $nId ) {
		$aItems = $this->query_retrieve( $nId );
		return array_shift( $aItems );
	}

	/**
	 * @param int $nId
	 * @return ICWP_WPSF_TrafficEntryVO[]|stdClass[]
	 */
	protected function query_retrieve( $nId = null ) {
		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`deleted_at` = 0
				%s
			ORDER BY `created_at` DESC
			%s
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			is_null( $nId ) ? '' : "AND `id` = ".esc_sql( (int)$nId ),
			$this->hasLimit() ? sprintf( 'LIMIT %s', $this->getLimit() ) : ''
		);

		$aData = $this->loadDbProcessor()
					  ->selectCustom( $sQuery, OBJECT_K );

		if ( $this->isResultsAsVo() ) {
			$aData = array_map(
				function ( $oResult ) {
					return ( new ICWP_WPSF_TrafficEntryVO() )->setRawData( $oResult );
				},
				$aData
			);
		}
		return $aData;
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_TrafficEntryVO.php' );
	}
}