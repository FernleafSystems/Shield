<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanPtg
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanPtg extends Base {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		$nTs = Services::Request()->ts();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oIt = ( new Shield\Scans\PTGuard\ConvertVosToResults() )->convertItem( $oEntry );
			$aE = $oEntry->getRawData();
			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'status' ] = $oIt->is_different ? 'Modified' : ( $oIt->is_missing ? 'Missing' : 'Unrecognised' );
			$aE[ 'ignored' ] = ( $oEntry->ignored_at > 0 && $nTs > $oEntry->ignored_at ) ? 'Yes' : 'No';
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * Since we can't select items by slug directly from the scan results DB
	 * we have to post-filter the results.
	 * @param Shield\Databases\Scanner\EntryVO[] $aEntries
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function postSelectEntriesFilter( $aEntries ) {
		$aParams = $this->getParams();

		if ( !empty( $aParams[ 'fSlug' ] ) ) {
			$oSlugResults = ( new Shield\Scans\PTGuard\ConvertVosToResults() )
				->convert( $aEntries )
				->getResultsSetForSlug( $aParams[ 'fSlug' ] );

			foreach ( $oSlugResults->getAllItems() as $oItem ) {
				foreach ( $aEntries as $key => $oEntry ) {
					if ( $oItem->hash !== $oEntry->hash ) {
						unset( $aEntries[ $key ] );
					}
				}
			}
		}

		return array_values( $aEntries );
	}

	/**
	 * @return Shield\Tables\Render\ScanUfc
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanUfc();
	}
}