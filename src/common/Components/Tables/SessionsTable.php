<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class SessionsTable extends ICWP_BaseTable {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		return $this->getActionButton_Delete( $aItem[ 'id' ] );
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		return sprintf( '%s<br />%s', $aItem[ 'wp_username' ], $aItem[ 'ip' ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'details'          => 'Details',
			'logged_in_at'     => 'Logged In',
			'last_activity_at' => 'Last Activity',
			'is_secadmin'      => 'Security Admin',
			'actions'          => $this->getColumnHeader_Actions(),
		);
	}
}