<?php

namespace MHM\WordPress\AttachmentFromFtp;

/**
 * Admin page "Media » Import log", which shows the entries of the plugin's log files, newest first.
 *
 * The files are read from the end, so that a large file (the error log can reach many megabytes)
 * does not need to be loaded completely to show a page of entries.
 */
class LogPage
{
	const PAGE = 'mhm_attachment_from_ftp_log';
	const PER_PAGE = 100;

	/**
	 * Number of bytes which are read at a time.
	 */
	const CHUNK_SIZE = 1048576;

	/**
	 * The beginning of each entry. Entries are written as: date('r') TAB title TAB content (which may have several lines).
	 */
	const ENTRY_START = '[A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} [+-]\d{4}\t';

	/**
	 * Maximum number of characters of an entry's details which are shown.
	 */
	const DETAILS_LENGTH = 5000;

	/**
	 * Events whose details start with the ID of an Attachment (as the first value of the logged array).
	 * A preview of the image is shown for these in the success log.
	 */
	const ATTACHMENT_EVENTS = [
		'mhm-attachment-from-ftp/attachment_created',
		'mhm-attachment-from-ftp/attachment_updates',
		'mhm-attachment-from-ftp/title_description_overwritten',
		'mhm-attachment-from-ftp/updated_attachment_metadata',
	];

	public function __construct()
	{
		add_action('admin_menu', [$this, 'createAdminMenu']);
	}

	public function createAdminMenu()
	{
		add_submenu_page(
			'upload.php',
			__('Import log', 'mhm_attachment_from_ftp'),
			__('Import log', 'mhm_attachment_from_ftp'),
			'manage_options',
			self::PAGE,
			[$this, 'logPage']
		);
	}

	public static function logs()
	{
		return [
			'error' => [
				'label' => __('Errors', 'mhm_attachment_from_ftp'),
				'file' => WP_CONTENT_DIR . '/mhm_attachment_from_ftp.error.log',
			],
			'success' => [
				'label' => __('Success', 'mhm_attachment_from_ftp'),
				'file' => WP_CONTENT_DIR . '/mhm_attachment_from_ftp.success.log',
			],
		];
	}

	public function logPage()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$logs = self::logs();
		$current = isset($_GET['log'], $logs[$_GET['log']]) ? sanitize_key($_GET['log']) : 'error';
		$file = $logs[$current]['file'];

		$total = $this->countEntries($file);
		$pages = max(1, (int) ceil($total / self::PER_PAGE));
		$paged = min(max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1), $pages);
		$entries = $this->readEntries($file, ($paged - 1) * self::PER_PAGE, self::PER_PAGE);

		$base_url = admin_url('upload.php?page=' . self::PAGE . '&log=' . $current);

		echo '<div class="wrap"><h1>' . esc_html__('Import log', 'mhm_attachment_from_ftp') . '</h1>';

		echo '<h2 class="nav-tab-wrapper">';
		foreach ($logs as $key => $log) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s <span class="count">(%4$s)</span></a>',
				esc_url(admin_url('upload.php?page=' . self::PAGE . '&log=' . $key)),
				$key === $current ? ' nav-tab-active' : '',
				esc_html($log['label']),
				esc_html(number_format_i18n($this->countEntries($log['file'])))
			);
		}
		echo '</h2>';

		if (empty($entries)) {
			echo '<p>' . esc_html__('There are no entries in this log.', 'mhm_attachment_from_ftp') . '</p></div>';
			return;
		}

		$this->pagination($base_url, $paged, $pages, $total);

		$show_preview = 'success' === $current;
		if ($show_preview) {
			// Load all the attachments of this page with a single query.
			$attachment_ids = array_filter(array_map([$this, 'attachmentId'], $entries));
			if ($attachment_ids) {
				_prime_post_caches(array_unique($attachment_ids), false, false);
			}
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>'
			. ($show_preview ? '<th scope="col" style="width: 100px">' . esc_html__('Preview', 'mhm_attachment_from_ftp') . '</th>' : '') . '
			<th scope="col" style="width: 12em">' . esc_html__('Date', 'mhm_attachment_from_ftp') . '</th>
			<th scope="col" style="width: 22em">' . esc_html__('Event', 'mhm_attachment_from_ftp') . '</th>
			<th scope="col">' . esc_html__('Details', 'mhm_attachment_from_ftp') . '</th>
		</tr></thead><tbody>';

		foreach ($entries as $entry) {
			$timestamp = strtotime($entry['date']);
			echo '<tr>' . ($show_preview ? '<td>' . $this->preview($this->attachmentId($entry)) . '</td>' : '') . '<td>' . esc_html($timestamp ? wp_date('Y-m-d H:i:s', $timestamp) : $entry['date']) . '</td>
				<td>' . esc_html($entry['title']) . '</td>
				<td>' . $this->details($entry['content']) . '</td></tr>';
		}

		echo '</tbody></table>';

		$this->pagination($base_url, $paged, $pages, $total);

		echo '</div>';
	}

	/**
	 * The ID of the Attachment which an entry is about, or 0 if it is not about a single Attachment.
	 */
	private function attachmentId($entry)
	{
		if (in_array($entry['title'], self::ATTACHMENT_EVENTS, true) && preg_match('/^Array\s*\(\s*\[0\]\s*=>\s*(\d+)\s/', $entry['content'], $matches)) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * A thumbnail of an Attachment, linked to its edit screen. Always escaped.
	 */
	private function preview($attachment_id)
	{
		if (!$attachment_id) {
			return '';
		}

		$image = 'attachment' === get_post_type($attachment_id) ? wp_get_attachment_image($attachment_id, 'thumbnail', false, ['style' => 'max-width: 80px; height: auto; display: block']) : '';
		if (!$image) {
			return '<span class="description">' . esc_html(sprintf(
				/* translators: %d: Attachment ID */
				__('Attachment %d: no image', 'mhm_attachment_from_ftp'),
				$attachment_id
			)) . '</span>';
		}

		return '<a href="' . esc_url(get_edit_post_link($attachment_id)) . '" title="' . esc_attr(sprintf(
			/* translators: %d: Attachment ID */
			__('Attachment %d', 'mhm_attachment_from_ftp'),
			$attachment_id
		)) . '">' . $image . '</a>';
	}

	private function pagination($base_url, $paged, $pages, $total)
	{
		$from = ($paged - 1) * self::PER_PAGE + 1;
		$to = min($total, $paged * self::PER_PAGE);

		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html(sprintf(
			/* translators: 1: first entry number, 2: last entry number, 3: total number of entries */
			__('%1$s–%2$s of %3$s entries', 'mhm_attachment_from_ftp'),
			number_format_i18n($from),
			number_format_i18n($to),
			number_format_i18n($total)
		)) . '</span>';

		$links = paginate_links([
			'base' => $base_url . '&paged=%#%',
			'format' => '',
			'current' => $paged,
			'total' => $pages,
			'prev_text' => '‹',
			'next_text' => '›',
			'type' => 'plain',
		]);

		if ($links) {
			echo ' <span class="pagination-links">' . $links . '</span>';
		}

		echo '</div></div>';
	}

	/**
	 * The details of an entry: a short preview, and the full text (up to a maximum) in a collapsible block.
	 * Always escaped.
	 */
	private function details($content)
	{
		$content = trim($content);
		if ('' === $content) {
			return '';
		}

		$preview = trim(preg_replace('/\s+/', ' ', mb_substr($content, 0, 200)));
		if (mb_strlen($content) <= 200 && false === strpos($content, "\n")) {
			return esc_html($content);
		}

		$full = mb_substr($content, 0, self::DETAILS_LENGTH);
		if (mb_strlen($content) > self::DETAILS_LENGTH) {
			$full .= "\n… (" . sprintf(
				/* translators: %s: number of characters which are not shown */
				__('%s more characters', 'mhm_attachment_from_ftp'),
				number_format_i18n(mb_strlen($content) - self::DETAILS_LENGTH)
			) . ')';
		}

		return '<details><summary>' . esc_html($preview) . '…</summary><pre style="white-space: pre-wrap; word-break: break-all">' . esc_html($full) . '</pre></details>';
	}

	/**
	 * Count the entries in a log file. The result is cached until the file changes.
	 */
	public function countEntries($file)
	{
		if (!is_readable($file) || !($size = filesize($file))) {
			return 0;
		}

		$cache_key = 'mhm_aff_logcount_' . md5($file);
		$cached = get_transient($cache_key);
		if (is_array($cached) && $cached['size'] === $size && $cached['mtime'] === filemtime($file)) {
			return $cached['count'];
		}

		$count = 0;
		$handle = fopen($file, 'rb');
		$buffer = '';
		$pattern = '/^' . self::ENTRY_START . '/m';

		while (!feof($handle)) {
			$buffer .= fread($handle, self::CHUNK_SIZE);

			// Only count complete lines. The rest is counted together with the next chunk.
			$last_newline = strrpos($buffer, "\n");
			if (false === $last_newline && !feof($handle)) {
				continue;
			}
			$complete = false === $last_newline ? $buffer : substr($buffer, 0, $last_newline + 1);
			$buffer = false === $last_newline ? '' : substr($buffer, $last_newline + 1);

			$count += (int) preg_match_all($pattern, $complete);
		}
		fclose($handle);

		set_transient($cache_key, ['size' => $size, 'mtime' => filemtime($file), 'count' => $count], DAY_IN_SECONDS);

		return $count;
	}

	/**
	 * Read entries from the end of a log file.
	 *
	 * @param string $file   The log file.
	 * @param int    $offset How many of the newest entries to skip.
	 * @param int    $limit  How many entries to return.
	 *
	 * @return array[] Entries (date, title, content), newest first.
	 */
	public function readEntries($file, $offset, $limit)
	{
		if (!is_readable($file) || !($position = filesize($file))) {
			return [];
		}

		$handle = fopen($file, 'rb');
		$pattern = '/^' . self::ENTRY_START . '/m';
		$wanted = $offset + $limit;
		$found = 0;
		$entries = [];
		$carry = '';

		while ($position > 0 && $found < $wanted) {
			$length = min(self::CHUNK_SIZE, $position);
			$position -= $length;
			fseek($handle, $position);
			$buffer = fread($handle, $length) . $carry;

			if (!preg_match_all($pattern, $buffer, $matches, PREG_OFFSET_CAPTURE)) {
				// No entry starts in here, so this is all part of an earlier entry.
				$carry = $buffer;
				continue;
			}

			$starts = array_column($matches[0], 1);
			$end = strlen($buffer);

			for ($i = count($starts) - 1; $i >= 0; $i--) {
				if ($found >= $offset && $found < $wanted) {
					$entries[] = $this->parseEntry(substr($buffer, $starts[$i], $end - $starts[$i]));
				}
				$found++;
				$end = $starts[$i];
			}

			// What comes before the first entry start belongs to an entry which begins in an earlier chunk.
			$carry = substr($buffer, 0, $starts[0]);
		}

		fclose($handle);

		return $entries;
	}

	private function parseEntry($raw)
	{
		$parts = explode("\t", rtrim($raw, "\r\n"), 3);

		return [
			'date' => $parts[0],
			'title' => $parts[1] ?? '',
			'content' => $parts[2] ?? '',
		];
	}
}

new LogPage();
