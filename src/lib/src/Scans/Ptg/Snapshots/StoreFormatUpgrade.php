<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Snapshots;

/**
 * Class StoreFormatUpgrade
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Snapshots
 */
class StoreFormatUpgrade {

	/**
	 * @var Store
	 */
	private $oStore;

	public function run() {
		$aSnapData = $this->getStore()->getSnapData();

		$bStoreRequired = false;
		foreach ( $aSnapData as $sSlug => $aSnap ) {
			$sSnapVersion = isset( $aSnap[ 'meta' ][ 'snap_version' ] ) ? isset( $aSnap[ 'meta' ][ 'snap_version' ] ) : '0.0';

			if ( version_compare( $sSnapVersion, '7.0.0', '<' ) ) {
				$aSnapData[ $sSlug ] = $this->upgradeSnap_Pre700( $aSnap );
				$bStoreRequired = true;
			}
		}

		if ( $bStoreRequired ) {
			try {
				$this->getStore()
					 ->deleteSnapshots()
					 ->setSnapData( $aSnapData )
					 ->save();
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @param array $aSnap
	 * @return array
	 */
	private function upgradeSnap_Pre700( $aSnap ) {
		$sNormAbs = wp_normalize_path( ABSPATH );
		$aSnap[ 'meta' ][ 'snap_version' ] = '7.0.0';
		foreach ( $aSnap[ 'hashes' ] as $sOldPath => $sFileHash ) {
			$sNewPath = str_replace( $sNormAbs, '', wp_normalize_path( $sOldPath ) );
			$aSnap[ 'hashes' ][ $sNewPath ] = $sFileHash;
			unset( $aSnap[ 'hashes' ][ $sOldPath ] );
		}
		return $aSnap;
	}

	/**
	 * @return Store
	 */
	private function getStore() {
		return $this->oStore;
	}

	/**
	 * @param Store $oStore
	 * @return $this
	 */
	public function setStore( $oStore ) {
		$this->oStore = $oStore;
		return $this;
	}
}