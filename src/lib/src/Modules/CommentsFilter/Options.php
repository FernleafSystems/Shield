<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\Options {

	/**
	 * @return string[]
	 */
	public function getDbColumns_Spam() {
		return $this->getDef( 'spambot_comments_filter_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Spam() {
		return $this->getCon()->prefixOption( $this->getDef( 'spambot_comments_filter_table_name' ) );
	}
}