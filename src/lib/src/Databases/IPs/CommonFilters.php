<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

trait CommonFilters {

	/**
	 * @param string $ip
	 * @return $this
	 */
	public function filterByIp( $ip ) {
		return $this->addWhereEquals( 'ip', $ip );
	}

	/**
	 * @param bool $bIsBlocked
	 * @return $this
	 */
	public function filterByBlocked( $bIsBlocked ) {
		return $this->addWhere( 'blocked_at', 0, $bIsBlocked ? '>' : '=' );
	}

	/**
	 * @return $this
	 */
	public function filterByBlacklist() {
		return $this->filterByLists( [
			ModCon::LIST_AUTO_BLACK,
			ModCon::LIST_MANUAL_BLACK
		] );
	}

	/**
	 * @return $this
	 */
	public function filterByWhitelist() {
		return $this->filterByList( ModCon::LIST_MANUAL_WHITE );
	}

	/**
	 * @param bool $bIsRange
	 * @return $this
	 */
	public function filterByIsRange( $bIsRange ) {
		return $this->addWhereEquals( 'is_range', $bIsRange ? 1 : 0 );
	}

	public function filterByLabel( string $label ) :self {
		return $this->addWhereEquals( 'label', $label );
	}

	/**
	 * @param string $nLastAccessAfter
	 * @return $this
	 */
	public function filterByLastAccessAfter( $nLastAccessAfter ) {
		return $this->addWhereNewerThan( $nLastAccessAfter, 'last_access_at' );
	}

	/**
	 * @param string $nLastAccessBefore
	 * @return $this
	 */
	public function filterByLastAccessBefore( $nLastAccessBefore ) {
		return $this->addWhereOlderThan( $nLastAccessBefore, 'last_access_at' );
	}

	/**
	 * @param string $sList
	 * @return $this
	 */
	public function filterByList( $sList ) {
		if ( !empty( $sList ) && is_string( $sList ) ) {
			$this->filterByLists( [ $sList ] );
		}
		return $this;
	}

	/**
	 * @param array $aLists
	 * @return $this
	 */
	public function filterByLists( $aLists ) {
		if ( !empty( $aLists ) ) {
			$this->addWhereIn( 'list', $aLists );
		}
		return $this;
	}
}