<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\Options {

	/**
	 * @return int
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'audit_trail_auto_clean' );
	}

	/**
	 * @return int
	 */
	public function getDefaultMaxEntries() {
		return $this->getDef( 'audit_trail_default_max_entries' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledChangeTracking() {
		return !$this->isOpt( 'enable_change_tracking', 'disabled' );
	}

	/**
	 * @return int
	 */
	public function getCTSnapshotsPerWeek() {
		return (int)$this->getOpt( 'ct_snapshots_per_week', 7 );
	}

	/**
	 * @return int
	 */
	public function getCTMaxSnapshots() {
		return (int)$this->getOpt( 'ct_max_snapshots', 28 );
	}

	/**
	 * @return int
	 */
	public function getCTSnapshotInterval() {
		return WEEK_IN_SECONDS/$this->getCTSnapshotsPerWeek();
	}

	/**
	 * @return int
	 */
	public function getCTLastSnapshotAt() {
		return $this->getOpt( 'ct_last_snapshot_at' );
	}

	/**
	 * @return bool
	 */
	public function isCTSnapshotDue() {
		return ( Services::Request()->ts() - $this->getCTLastSnapshotAt() > $this->getCTSnapshotInterval() );
	}

	/**
	 * @return bool
	 */
	public function isEnabledAuditing() {
		return $this->isAuditEmails()
			   || $this->isAuditPlugins()
			   || $this->isAuditThemes()
			   || $this->isAuditPosts()
			   || $this->isAuditShield()
			   || $this->isAuditUsers()
			   || $this->isAuditWp();
	}

	/**
	 * @return bool
	 */
	public function isAuditEmails() {
		return $this->isOpt( 'enable_audit_context_emails', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditPlugins() {
		return $this->isOpt( 'enable_audit_context_plugins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditPosts() {
		return $this->isOpt( 'enable_audit_context_posts', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditShield() {
		return $this->isOpt( 'enable_audit_context_wpsf', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditThemes() {
		return $this->isOpt( 'enable_audit_context_themes', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditUsers() {
		return $this->isOpt( 'enable_audit_context_users', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAuditWp() {
		return $this->isOpt( 'enable_audit_context_wordpress', 'Y' );
	}
}