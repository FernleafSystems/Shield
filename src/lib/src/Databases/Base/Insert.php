<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends BaseQuery {

	/**
	 * @var array
	 */
	protected $aInsertData;

	/**
	 * @return array
	 */
	public function getInsertData() {
		return is_array( $this->aInsertData ) ? $this->aInsertData : array();
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) {
		return $this->setInsertData( $oEntry->getRawDataAsArray() )->query() === 1;
	}

	/**
	 * @param array $aData
	 * @return $this
	 */
	protected function setInsertData( $aData ) {
		$this->aInsertData = $aData;
		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		$aData = $this->getInsertData();

		if ( !is_array( $aData ) ) {
			$aData = array();
		}
		$aData = array_merge(
			array( 'created_at' => Services::Request()->ts(), ),
			$aData
		);
		if ( !isset( $aData[ 'updated_at' ] ) && $this->getDbH()->hasColumn( 'updated_at' ) ) {
			$aData[ 'updated_at' ] = Services::Request()->ts();
		}

		return $this->setInsertData( $aData );
	}

	/**
	 * @return bool|int
	 */
	public function query() {
		try {
			$this->verifyInsertData();
			$bSuccess = Services::WpDb()
								->insertDataIntoTable(
									$this->getDbH()->getTable(),
									$this->getInsertData()
								);
		}
		catch ( \Exception $oE ) {
			$bSuccess = false;
		}
		return $bSuccess;
	}

	/**
	 * Offset never applies
	 *
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}