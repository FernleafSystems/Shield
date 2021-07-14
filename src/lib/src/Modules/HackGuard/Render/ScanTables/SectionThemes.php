<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class SectionThemes extends SectionPluginThemesBase {

	public function render() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/themes/index.twig',
						$this->buildRenderData()
					);
	}

	protected function buildRenderData() :array {
		$mod = $this->getMod();

		$themes = $this->buildThemesData();
		ksort( $themes );

		$problems = [];
		$active = [];
		foreach ( $themes as $key => $item ) {
			if ( $item[ 'flags' ][ 'has_issue' ] ) {
				unset( $themes[ $key ] );
				$problems[] = $item;
			}
			elseif ( $item[ 'info' ][ 'active' ] ) {
				unset( $themes[ $key ] );
				$active[] = $item;
			}
		}

		return [
			'ajax'    => [
				'scantable_action' => $mod->getAjaxActionData( 'scantable_action', true ),
			],
			'strings' => [
				'author'                => __( 'Author' ),
				'version'               => __( 'Version' ),
				'name'                  => __( 'Name' ),
				'install_dir'           => __( 'Install Dir', 'wp-simple-firewall' ),
				'file_integrity_status' => __( 'File Integrity Status', 'wp-simple-firewall' ),
				'status_good'           => __( 'Good', 'wp-simple-firewall' ),
				'status_warning'        => __( 'Warning', 'wp-simple-firewall' ),
				'abandoned'             => __( 'Abandoned', 'wp-simple-firewall' ),
				'vulnerable'            => __( 'Vulnerable', 'wp-simple-firewall' ),
				'vulnerable_known'      => __( 'Known vulnerabilities are present.', 'wp-simple-firewall' ),
				'vulnerable_update'     => __( "You should upgrade to the latest available version or remove it if none are available.", 'wp-simple-firewall' ),
				'update_available'      => __( 'Update Available', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'upgrade' => Services::WpGeneral()->getAdminUrl_Updates()
			],
			'vars'    => [
				'themes' => array_values( $problems )
			]
		];
	}

	private function buildThemesData() :array {
		return array_map(
			function ( $item ) {
				return $this->buildThemeData( $item );
			},
			Services::WpThemes()->getThemesAsVo()
		);
	}

	private function buildThemeData( WpThemeVo $theme ) :array {
		$carbon = Services::Request()->carbon();

		$abandoned = $this->getAbandoned()->getItemForSlug( $theme->stylesheet );
		$guardFilesData = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForTheme( $theme );

		$vulnerabilities = $this->getVulnerabilities()->getItemsForSlug( $theme->file );

		$data = [
			'info'  => [
				'type'         => 'theme',
				'active'       => $theme->active,
				'name'         => $theme->Name,
				'slug'         => $theme->slug,
				'description'  => $theme->Description,
				'version'      => $theme->Version,
				'author'       => $theme->Author,
				'author_url'   => $theme->AuthorURI,
				'file'         => $theme->stylesheet,
				'dir'          => '/'.str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $theme->getInstallDir() ) ),
				'abandoned_at' => empty( $abandoned ) ? 0
					: $carbon->setTimestamp( $abandoned->last_updated_at )->diffForHumans(),
			],
			'flags' => [
				'has_update'      => $theme->hasUpdate(),
				'is_abandoned'    => !empty( $abandoned ),
				'has_guard_files' => !empty( $guardFilesData ),
				'is_vulnerable'   => !empty( $vulnerabilities ),
				'is_wporg'        => $theme->isWpOrg(),
			]
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'is_abandoned' ]
										  || $data[ 'flags' ][ 'has_guard_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		return $data;
	}
}