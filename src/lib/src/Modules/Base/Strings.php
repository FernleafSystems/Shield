<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Strings {

	use ModConsumer;

	/**
	 * @return string[]
	 */
	public function getDisplayStrings() {
		return Services::DataManipulation()->mergeArraysRecursive(
			[
				'see_help_video'    => __( 'Watch Help Video' ),
				'btn_save'          => __( 'Save Options' ),
				'btn_options'       => __( 'Options' ),
				'btn_help'          => __( 'Help' ),
				'btn_wizards'       => $this->getMod()->hasWizard() ? __( 'Wizards' ) : __( 'No Wizards' ),
				'back_to_dashboard' => sprintf( __( 'Back To %s Dashboard', 'wp-simple-firewall' ), $this->getCon()
																										 ->getHumanName() ),
				'go_to_settings'    => __( 'Settings', 'wp-simple-firewall' ),
				'on'                => __( 'On', 'wp-simple-firewall' ),
				'off'               => __( 'Off', 'wp-simple-firewall' ),
				'more_info'         => __( 'Info', 'wp-simple-firewall' ),
				'blog'              => __( 'Blog', 'wp-simple-firewall' ),
				'save_all_settings' => __( 'Save All Settings', 'wp-simple-firewall' ),
				'options_title'     => __( 'Options', 'wp-simple-firewall' ),
				'options_summary'   => __( 'Configure Module', 'wp-simple-firewall' ),
				'actions_title'     => __( 'Actions and Info', 'wp-simple-firewall' ),
				'actions_summary'   => __( 'Perform actions for this module', 'wp-simple-firewall' ),
				'help_title'        => __( 'Help', 'wp-simple-firewall' ),
				'help_summary'      => __( 'Learn More', 'wp-simple-firewall' ),
				'pro_only_option'   => __( 'Pro Only' ),
				'go_pro_option'     => sprintf( '<a href="%s" target="_blank">%s</a>',
					'https://icwp.io/shieldgoprofeature', __( 'Please upgrade to Pro to control this option', 'wp-simple-firewall' ) ),

				'aar_title'                    => __( 'Plugin Access Restricted', 'wp-simple-firewall' ),
				'aar_what_should_you_enter'    => __( 'This security plugin is restricted to administrators with the Security Access Key.', 'wp-simple-firewall' ),
				'aar_must_supply_key_first'    => __( 'Please provide the Security Access Key to manage this plugin.', 'wp-simple-firewall' ),
				'aar_to_manage_must_enter_key' => __( 'To manage this plugin you must enter the access key.', 'wp-simple-firewall' ),
				'aar_enter_access_key'         => __( 'Enter Access Key', 'wp-simple-firewall' ),
				'aar_submit_access_key'        => __( 'Submit Security Admin Key', 'wp-simple-firewall' ),
				'aar_forget_key'               => __( "Forgotten Key", 'wp-simple-firewall' )
			],
			$this->getAdditionalDisplayStrings()
		);
	}

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() {
		return [];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [];
	}

	/**
	 * @param string $sKey
	 * @return string[]
	 */
	public function getAuditMessage( $sKey ) {
		$aMsg = $this->getAuditMessages();
		return isset( $aMsg[ $sKey ] ) ? $aMsg[ $sKey ] : [];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sOptKey ) );
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

			case 'section_user_messages' :
				$sTitle = __( 'User Messages', 'wp-simple-firewall' );
				$sTitleShort = __( 'Messages', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Customize the messages displayed to the user.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use this section if you need to communicate to the user in a particular manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Hint', 'wp-simple-firewall' ), sprintf( __( 'To reset any message to its default, enter the text exactly: %s', 'wp-simple-firewall' ), 'default' ) )
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}
}