<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

/**
 * Class BuildMedia
 * @package FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot
 */
class BuildMedia extends BuildPosts {

	/**
	 * @param array $aParams
	 * @return array[]
	 */
	protected function retrieve( $aParams = [] ) {
		$aItems = parent::retrieve( $aParams );
		return $aItems;
	}

	/**
	 * @return array
	 */
	protected function getBaseParameters() {
		$aParams = parent::getBaseParameters();
		$aParams[ 'post_type' ] = 'attachment';
		unset( $aParams[ 'post_status' ] );
		return $aParams;
	}
}