<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanBase extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {
		return sprintf( '<code>%s</code>', $aItem[ 'path' ] );
	}

	protected function extra_tablenav( $which ) {
		echo '';
	}

	/**
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array_merge( parent::get_table_classes(), [ 'scan-table' ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'       => 'File',
			'status'     => 'Status',
			'created_at' => 'Discovered',
		);
	}
}