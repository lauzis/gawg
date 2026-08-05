<?php
defined( 'ABSPATH' ) || exit;

/**
 * GAWG's general log.
 *
 * Distinct from GAWG_History, which records what happened to one participant
 * and lives in that participant's post meta — a permanent, per-entrant trail
 * that answers "what was done about this person". This is the operational view:
 * site-wide, time-ordered files that answer "why did the emails stop going
 * out". They complement each other and neither replaces the other.
 *
 * A thin facade over the shared logger in lauzis/wp-plugin-packages, so this
 * degrades to silence rather than fataling if the package is ever missing.
 */
class GAWG_Logs {

	const SLUG = 'gawg';

	/**
	 * No 'enabled' is passed: the component reads the logs_enabled field from
	 * the schema GAWG_Settings registers. Off until switched on, so a site that
	 * has never opened Settings writes nothing.
	 *
	 * @return \Lauzis\WpPackages\Logs\Logger|null
	 */
	private static function logger() {
		if ( ! class_exists( 'WpPackages_Registry' ) ) {
			return null;
		}

		return WpPackages_Registry::logger( self::SLUG, array( 'dir' => GAWG_LOG_PATH ) );
	}

	/**
	 * @param string $action  Short label, e.g. 'mail' or 'entries'.
	 * @param string $message Human-readable message.
	 * @param array  $context Key-value context, appended as JSON.
	 */
	public static function add( $action, $message = '', array $context = array() ) {
		$logger = self::logger();

		return $logger ? $logger->add( $action, $message, $context ) : false;
	}

	/**
	 * Records a failure. Always reaches PHP's error log, whatever the setting
	 * says — a giveaway that silently stops emailing is a fairness problem, not
	 * just a bug, so those are never entirely invisible.
	 */
	public static function error( $action, $message = '', array $context = array() ) {
		$logger = self::logger();

		if ( $logger ) {
			$logger->error( $action, $message, $context );
		}
	}

	/** True when file logging is switched on in Settings. */
	public static function enabled() {
		$logger = self::logger();

		return $logger ? $logger->isEnabled() : false;
	}

	/**
	 * Daily log files, newest first.
	 *
	 * @return array[] Each: ['file', 'name', 'date', 'count'].
	 */
	public static function files() {
		$logger = self::logger();

		return $logger ? $logger->files() : array();
	}

	/** Entries for one day, newest first. */
	public static function read( $date = null ) {
		$logger = self::logger();

		if ( ! $logger ) {
			return array();
		}

		if ( null === $date ) {
			return $logger->read();
		}

		$file = $logger->dir() . self::SLUG . '-' . $date . '.log';

		if ( ! is_readable( $file ) ) {
			return array();
		}

		$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		return $lines ? array_reverse( $lines ) : array();
	}

	/** Deletes every daily log file. */
	public static function clear() {
		$logger = self::logger();

		return $logger ? $logger->clear() : false;
	}
}
