<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\CrowdSourced;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Submit\{
	PreSubmit,
	Submit
};

class SubmitHashes {

	use ModConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $asset;

	/**
	 * @var string[]
	 */
	private $hashes;

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	public function run( $asset ) {
		$this->asset = $asset;

		if ( $this->canSubmitAsset() ) {
			$this->hashes = ( new Build\BuildHashesForAsset() )
				->setHashAlgo( 'sha1' )
				->buildNormalised( $asset );

			if ( !empty( $this->hashes ) && $this->isSubmitRequired() ) {
				$this->submit();
			}
		}
	}

	private function canSubmitAsset() :bool {
		return preg_match( '#^[0-9.]$#', $this->asset->Version )
			   && preg_match( '#^[0-9a-z]+[0-9a-z_\-]+$#i', $this->asset->slug );
	}

	private function isSubmitRequired() :bool {
		$response = ( new PreSubmit() )
			->setHashes( $this->hashes )
			->query();
		return is_array( $response ) && !empty( $response[ 'hashes' ] ) && $response[ 'hashes' ][ 'submit_required' ];
	}

	private function submit() :bool {
		$sub = ( new Submit() )->setHashes( $this->hashes );
		$response = $this->asset->asset_type === 'plugin' ? $sub->submitPlugin( $this->asset ) : $sub->submitTheme( $this->asset );
		return !empty( $response ) && empty( $response[ 'error' ] );
	}
}