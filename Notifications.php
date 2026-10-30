<?php

namespace MHM\WordPress\AttachmentFromFtp;

/**
 * Email notifications about the import process.
 *
 * Events are not emailed one by one. They are collected, grouped by kind and
 * title, and sent to the site administrator as a single digest email. At most
 * one email is sent per hour, no matter how many events occur.
 */
class Notifications
{
	const OPTION = 'mhm_attachment_from_ftp_notifications';
	const LOCK = 'mhm_attachment_from_ftp_notifications_lock';

	/**
	 * Minimum number of seconds between two emails.
	 */
	const INTERVAL = 3600;

	/**
	 * Maximum number of different event groups which are kept for the next email.
	 */
	const MAX_GROUPS = 50;

	/**
	 * Maximum length of the example text which is included for each group.
	 */
	const SAMPLE_LENGTH = 500;

	/**
	 * Record an event, and send the digest email if the last one was sent more than an hour ago.
	 *
	 * @param string $kind    'error', 'success' or 'regular'.
	 * @param string $title   The event name, as written to the log file.
	 * @param string $content The event details, as written to the log file.
	 */
	public static function record($kind, $title, $content = '')
	{
		if (!self::isWanted($kind)) {
			return;
		}

		if (!self::lock()) {
			return;
		}

		$state = self::getState();
		$key = $kind . '|' . $title;
		$now = time();
		$sample = mb_substr((string) $content, 0, self::SAMPLE_LENGTH);

		if (isset($state['groups'][$key])) {
			$state['groups'][$key]['count']++;
			$state['groups'][$key]['last'] = $now;
			$state['groups'][$key]['last_sample'] = $sample;
		} elseif (count($state['groups']) < self::MAX_GROUPS) {
			$state['groups'][$key] = [
				'kind' => $kind,
				'title' => $title,
				'count' => 1,
				'first' => $now,
				'last' => $now,
				'first_sample' => $sample,
				'last_sample' => $sample,
			];
		} else {
			$state['dropped'] = (int) ($state['dropped'] ?? 0) + 1;
		}

		update_option(self::OPTION, $state, false);
		self::unlock();

		self::flush();
	}

	/**
	 * Send the digest email if there are collected events and the last email is more than an hour old.
	 *
	 * @return bool True if an email was sent.
	 */
	public static function flush()
	{
		$state = self::getState();

		if (empty($state['groups']) || (time() - (int) $state['last_sent']) < self::INTERVAL) {
			return false;
		}

		if (!self::lock()) {
			return false;
		}

		// Read the state again now that it can't change, and check again.
		$state = self::getState();
		$sent = false;

		if (!empty($state['groups']) && (time() - (int) $state['last_sent']) >= self::INTERVAL) {
			$sent = (bool) wp_mail(
				get_option('admin_email'),
				self::subject($state),
				self::body($state)
			);

			// Also when sending fails: wait for the next interval, instead of trying again every minute.
			$state['last_sent'] = time();
			if ($sent) {
				$state['groups'] = [];
				$state['dropped'] = 0;
			}
			update_option(self::OPTION, $state, false);
		}

		self::unlock();

		return $sent;
	}

	/**
	 * Should an event of this kind be emailed, according to the plugin options?
	 *
	 * "Regular" notifications cover all events.
	 */
	private static function isWanted($kind)
	{
		$options = get_option('mhm_attachment_from_ftp');
		if (!is_array($options)) {
			return false;
		}

		if (!empty($options['notify_regular'])) {
			return true;
		}

		return 'error' === $kind ? !empty($options['notify_error']) : ('success' === $kind && !empty($options['notify_success']));
	}

	private static function getState()
	{
		$state = get_option(self::OPTION);

		return array_merge(['last_sent' => 0, 'groups' => [], 'dropped' => 0], is_array($state) ? $state : []);
	}

	private static function subject($state)
	{
		$total = array_sum(wp_list_pluck($state['groups'], 'count')) + (int) $state['dropped'];

		return sprintf(
			/* translators: 1: site name, 2: number of events */
			_n('[%1$s] Attachments from FTP: %2$d event', '[%1$s] Attachments from FTP: %2$d events', $total, 'mhm_attachment_from_ftp'),
			wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
			$total
		);
	}

	private static function body($state)
	{
		$labels = [
			'error' => 'ERROR',
			'success' => 'SUCCESS',
			'regular' => 'INFO',
		];
		$lines = [
			sprintf('Events recorded by the plugin “Attachments from FTP” on %s.', home_url()),
			'',
		];

		foreach ($state['groups'] as $group) {
			$lines[] = sprintf(
				'[%s] %s: %d time%s',
				$labels[$group['kind']] ?? strtoupper($group['kind']),
				$group['title'],
				$group['count'],
				1 === (int) $group['count'] ? '' : 's'
			);
			$lines[] = '  First: ' . wp_date('Y-m-d H:i:s', $group['first']) . ', last: ' . wp_date('Y-m-d H:i:s', $group['last']);
			$lines[] = '  Details of the first event: ' . self::oneLine($group['first_sample']);
			if ($group['count'] > 1 && $group['last_sample'] !== $group['first_sample']) {
				$lines[] = '  Details of the last event: ' . self::oneLine($group['last_sample']);
			}
			$lines[] = '';
		}

		if (!empty($state['dropped'])) {
			$lines[] = sprintf('Another %d event(s) of other types are not listed here. See the log.', $state['dropped']);
			$lines[] = '';
		}

		$lines[] = 'Full log: ' . admin_url('upload.php?page=mhm_attachment_from_ftp_log');
		$lines[] = 'You receive at most one such email per hour. The notifications can be changed at ' . admin_url('options-general.php?page=mhm_attachment_from_ftp');

		return implode("\n", $lines);
	}

	private static function oneLine($text)
	{
		return trim(preg_replace('/\s+/', ' ', (string) $text));
	}

	/**
	 * A simple lock, so that two import runs can't change the state or send the email at the same time.
	 * add_option() fails if the option already exists, which makes it atomic.
	 */
	private static function lock()
	{
		for ($try = 0; $try < 10; $try++) {
			if (add_option(self::LOCK, time(), '', 'no')) {
				return true;
			}

			// Remove locks which are left over from a request which was interrupted.
			wp_cache_delete(self::LOCK, 'options');
			if (time() - (int) get_option(self::LOCK) > 120) {
				delete_option(self::LOCK);
				continue;
			}

			usleep(100000);
		}

		return false;
	}

	private static function unlock()
	{
		delete_option(self::LOCK);
	}
}
