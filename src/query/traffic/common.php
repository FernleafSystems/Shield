<?php

if ( trait_exists( 'ICWP_WPSF_Query_TrafficEntry_Common', false ) ) {
	return;
}

trait ICWP_WPSF_Query_TrafficEntry_Common {

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $bBinaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByIp( $bBinaryIp ) {
		if ( inet_ntop( $bBinaryIp ) !== false ) {
			$this->addWhereEquals( 'ip', $bBinaryIp );
		}
		return $this;
	}

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $bBinaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByNotIp( $bBinaryIp ) {
		if ( inet_ntop( $bBinaryIp ) !== false ) {
			$this->addWhere( 'ip', $bBinaryIp, '!=' );
		}
		return $this;
	}

	/**
	 * @param bool $bIsLoggedIn - true is logged-in, false is not logged-in
	 * @return $this
	 */
	public function filterByIsLoggedIn( $bIsLoggedIn ) {
		return $this->addWhere( 'uid', 0, $bIsLoggedIn ? '>' : '=' );
	}

	/**
	 * @param bool $bIsTransgression
	 * @return $this
	 */
	public function filterByIsTransgression( $bIsTransgression ) {
		return $this->addWhere( 'trans', 0, $bIsTransgression ? 1 : 0 );
	}

	/**
	 * @param string $sTerm
	 * @return $this
	 */
	public function filterByPathContains( $sTerm ) {
		return $this->addWhereSearch( 'path', $sTerm );
	}

	/**
	 * @param int $nId
	 * @return $this
	 */
	public function filterByUserId( $nId ) {
		return $this->addWhereEquals( 'uid', (int)$nId );
	}

	/**
	 * @param string $sCode
	 * @return $this
	 */
	public function filterByResponseCode( $sCode ) {
		if ( is_numeric( $sCode ) ) {
			$sCode = (string)$sCode;
			if ( $sCode === '0' || preg_match( '#^[0-5]{1}[0-9]{2}$#', $sCode ) ) {
				$this->addWhereEquals( 'code', $sCode );
			}
		}
		return $this;
	}
}