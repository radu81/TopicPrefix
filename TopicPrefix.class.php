<?php
/**
 * Topics Prefix
 *
 * @author  emanuele
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.2
 */

if (!defined('ELK'))
	die('No access...');

class TopicPrefix
{
	protected $_currentTopics = null;
	protected $_prefixes = array();

	public function __construct()
	{
		require_once(SOURCEDIR . '/TopicPrefix.integrate.php');
	}

	public function __get($method)
	{
		switch ($method)
		{
			case 'pm':
				return new TopicPrefix_PxCRUD();
			case 'tm':
				return new TopicPrefix_TcCRUD();
		}
	}

	public function loadAll()
	{
		global $context, $topic, $board;

		// This has a meaning only for first posts
		// that means new topics or editing the first message
		if (!$context['is_first_post'])
			return;

		// All the template stuff
		loadTemplate('TopicPrefix');
		loadLanguage('TopicPrefix');
		Template_Layers::getInstance()->addAfter('pickprefix', 'postarea');

		// If we are editing a message, we may want to know the old prefix
		if (isset($_REQUEST['msg']))
		{
			$prefix = $this->getTopicPrefixes($topic);
		}

		$context['available_prefixes'] = $this->loadPrefixes(isset($prefix['id_prefix']) ? $prefix['id_prefix'] : null, $board);
	}

	public function updateTopicPrefix($topic, $prefix_id)
	{
		$this->tm->updateByPrefixTopic($topic, $prefix_id);
	}

	public function loadPrefixes($default = null, $board = null, $for_edit = false)
	{
		global $txt, $scripturl;

		$boards = (array) $board;
		$boards_used = array();

		if ($this->_currentTopics !== null)
			$this->_prefixes = $this->tm->getByTopic($this->_currentTopics);

		$px = $this->pm->getAll();

		if (!$for_edit)
		{
			$prefixes = array(0 => array(
				'selected' => true,
				'text' => $txt['topicprefix_noprefix'],
				'id_boards' => array(),
			));
		}
		else
			$prefixes = array();

		foreach ($px as $row)
		{
			$id_boards = array();
			if (!$for_edit && empty($row['id_boards']))
				continue;

			// I could use find_in_set, but I may also change my mind later...
			$id_boards = explode(',', $row['id_boards']);

			if (!empty($boards) && count(array_intersect($boards, $id_boards)) == 0)
				continue;

			$boards_used = array_filter(array_unique(array_merge($boards_used, $id_boards)));

			$prefixes[$row['id_prefix']] = array(
				'selected' => in_array($row['id_prefix'], $this->_prefixes),
				'text' => $row['prefix'],
				'id_boards' => $id_boards,
				'edit_url' => $for_edit ? $scripturl . '?action=admin;area=postsettings;sa=prefix;do=editboards;pid=' . $row['id_prefix'] : '',
			);

			if ($default == $row['id_prefix'])
				$prefixes[0]['selected'] = false;
		}

		if ($board === null && !empty($boards_used))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$boardsdetail = fetchBoardsInfo(array('boards' => $boards_used));
			$num_boards = countBoards(null, array('include_redirects' => false));

			foreach ($prefixes as $key => $value)
			{
				if ($num_boards == count($value['id_boards']))
					$bdet = array(0 => array('name' => $txt['all_boards']));
				else
				{
					$bdet = array();
					foreach ($value['id_boards'] as $id)
					{
						if (!empty($id))
							$bdet[] = $boardsdetail[$id];
					}
				}
				$prefixes[$key]['boards'] = $bdet;
			}
		}

		return $prefixes;
	}

	protected function _countBoards()
	{
		$db = database();
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND '
		);
		list ($count) = $db->fetch_row($request);
		$db->free_result($request);

		return $count;
	}

	public function setTopic($topics)
	{
		$this->_is_array = is_array($this->_currentTopics);

		if (!$this->_is_array)
			$this->_currentTopics = array($this->_currentTopics);

		$this->_currentTopics = $topics;
	}

	public function getTopicPrefixes($topics)
	{
		$is_array = is_array($topics);
		if (empty($topics))
			return array();

		if (!$is_array)
			$topics = array($topics);

		$prefixes = $this->tm->getByTopic($topics, 'load');

		if ($is_array)
			return $prefixes;
		else
			return array_shift($prefixes);
	}

	public function getPrefixDetails($prefix_id)
	{
		if (empty($prefix_id))
			return $this->_prefixDefaults();

		$pxd = $this->pm->getById($prefix_id);
		$text = $pxd['prefix'];

		// An empty prefix cannot exists (well, a prefix 0 could), so out of here!
		if (empty($text) && $text == '')
			return false;

		$count = $this->tm->countByPrefix($prefix_id);

		return array('id' => $pxd['id_prefix'], 'text' => $text, 'count' => $count, 'boards' => explode(',', $pxd['id_boards']));
	}

	protected function _prefixDefaults()
	{
		return array(
			'id_prefix' => 0,
			'text' => '',
			'boards' => array(),
			'style' => $this->_defaultStyle($this->_maxId())
		);
	}

	protected function _maxId()
	{
		$db = database();
		$request = $db->query('', '
			SELECT MAX(id_prefix) + 1
			FROM {db_prefix}topic_prefix_text');
		list ($max) = $db->fetch_row($request);
		$db->free_result($request);

		return $max;
	}

	public function getStyle($prefix_id, $theme_dir)
	{
		$file = $theme_dir . '/css/custom.css';
		$style = $this->_fetchStyle($file, $prefix_id);

		if ($style === false || $style === true)
			return $this->_defaultStyle($prefix_id);
		else
			return $style;
	}

	protected function _fetchStyle($file, $prefix_id)
	{
		if (!file_exists($file))
			return false;

		if (!is_writable($file))
			return false;

		$string = file_get_contents($file);
		preg_match('~\.prefix_id_' . $prefix_id . '[\n\s]\{.*?\}~s', $string, $match);
		if (!empty($match[0]))
			return $match[0];
		else
			return true;
	}

	protected function _defaultStyle($prefix_id)
	{
		global $txt;

		return '.prefix_id_' . $prefix_id . ' {
	/* ' . $txt['topicprefix_yourstylehere'] . ' */
}';
	}

	public function getPrefixedTopics($prefix_id, $id_member, $start, $limit, $sort_by, $sort_column, $indexOptions)
	{
		$db = database();
		$topics = array();

		$request = $db->query('', '
			SELECT t.id_topic
			FROM {db_prefix}topic_prefix AS p
				LEFT JOIN {db_prefix}topics AS t ON (p.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)' . ($sort_by === 'last_poster' ? '
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' : (in_array($sort_by, array('starter', 'subject')) ? '
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)' : '')) . ($sort_by === 'starter' ? '
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' : '') . ($sort_by === 'last_poster' ? '
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' : '') . '
			WHERE {query_see_board}' . ($indexOptions['board'] === null ? '' : '
				AND b.id_board = {int:board}') . '
				AND p.id_prefix = {int:current_prefix}' . (!$indexOptions['only_approved'] ? '' : '
				AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
			ORDER BY ' . ($indexOptions['include_sticky'] ? 't.is_sticky' . ($indexOptions['fake_ascending'] ? '' : ' DESC') . ', ' : '') . $sort_column . ($indexOptions['ascending'] ? '' : ' DESC') . '
			LIMIT {int:start}, {int:maxindex}',
			array(
				'current_prefix' => $prefix_id,
				'current_member' => $id_member,
				'board' => $indexOptions['board'],
				'is_approved' => 1,
				'id_member_guest' => 0,
				'start' => $start,
				'maxindex' => $limit,
			)
		);
		$topic_ids = array();
		while ($row = $db->fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		$db->free_result($request);

		// And now, all you ever wanted on message index...
		// and some you wish you didn't! :P
		if (!empty($topic_ids))
		{
			// If empty, no preview at all
			if (empty($indexOptions['previews']))
				$preview_bodies = '';
			// If -1 means everything
			elseif ($indexOptions['previews'] === -1)
				$preview_bodies = ', ml.body AS last_body, mf.body AS first_body';
			// Default: a SUBSTRING
			else
				$preview_bodies = ', SUBSTRING(ml.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS last_body, SUBSTRING(mf.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS first_body';

			$request = $db->query('substring', '
				SELECT
					t.id_topic, t.num_replies, t.locked, t.num_views, t.num_likes, t.is_sticky, t.id_poll, t.id_previous_board,
					' . ($id_member == 0 ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
					t.id_last_msg, t.approved, t.unapproved_posts, t.id_redirect_topic, t.id_first_msg,
					ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
					ml.poster_name AS last_member_name, ml.id_member AS last_id_member, ml.smileys_enabled AS last_smileys,
					IFNULL(meml.real_name, ml.poster_name) AS last_display_name,
					mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
					mf.poster_name AS first_member_name, mf.id_member AS first_id_member, mf.smileys_enabled AS first_smileys,
					IFNULL(memf.real_name, mf.poster_name) AS first_display_name
					' . $preview_bodies . '
					' . (!empty($indexOptions['include_avatars']) ? ' ,meml.avatar ,IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, meml.email_address' : '') .
					(!empty($indexOptions['custom_selects']) ? ' ,' . implode(',', $indexOptions['custom_selects']) : '') . '
				FROM {db_prefix}topic_prefix AS p
					LEFT JOIN {db_prefix}topics AS t ON (p.id_topic = t.id_topic)
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
					LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . ($id_member == 0 ? '' : '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})') . (!empty($indexOptions['include_avatars']) ? '
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)' : '') . '
				WHERE t.id_topic IN ({array_int:topic_list})' . (!$indexOptions['only_approved'] ? '' : '
					AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
				ORDER BY ' . ($indexOptions['include_sticky'] ? 'is_sticky' . ($indexOptions['fake_ascending'] ? '' : ' DESC') . ', ' : '') . $sort_column . ($indexOptions['ascending'] ? '' : ' DESC') . '
				LIMIT {int:start}, ' . '{int:maxindex}',
				array(
					'current_prefix' => $prefix_id,
					'current_member' => $id_member,
					'topic_list' => $topic_ids,
					'is_approved' => 1,
					'find_set_topics' => implode(',', $topic_ids),
					'start' => $start,
					'maxindex' => $limit,
				)
			);

			// Lets take the results
			while ($row = $db->fetch_assoc($request))
				$topics[$row['id_topic']] = $row;

			$db->free_result($request);
		}

		return $topics;

	}

	public function getAllPrefixes($start, $limit, $sort, $sort_dir)
	{
		$db = database();

		$request = $db->query('', '
			SELECT p.id_prefix, pt.prefix, COUNT(p.id_topic) as num_topics
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board AND {query_see_board})
				LEFT JOIN {db_prefix}topic_prefix AS p ON (t.id_topic = p.id_topic)
				LEFT JOIN {db_prefix}topic_prefix_text AS pt ON (pt.id_prefix = p.id_prefix)
			GROUP BY p.id_prefix
			HAVING num_topics > 0
			ORDER BY {raw:sort} {raw:dir}
			LIMIT {int:start}, {int:limit}',
			array(
				'sort' => $sort,
				'dir' => $sort_dir,
				'start' => $start,
				'limit' => $limit,
			)
		);
		$return = array();
		while ($row = $db->fetch_assoc($request))
			$return[$row['id_prefix']] = $row;
		$db->free_result($request);

		return $return;
	}

	public function countAllPrefixes()
	{
		return $this->pm->countAll();
	}

	public function newPrefix($text, $boards, $style, $themedir)
	{
		$db = database();

		list ($text, $boards) = $this->_validateQueryParams($text, $boards);

		if (empty($text))
			return false;

		$db->insert('insert',
			'{db_prefix}topic_prefix_text',
			array('prefix' => 'string-30', 'id_boards' => 'string'),
			array($text, implode(',', $boards)),
			array('id_prefix')
		);

		$id = $db->insert_id('{db_prefix}topic_prefix_text');

		$this->_storeStyle($id, $style, $themedir);

		return $id;
	}

	public function updatePrefix($id, $text = null, $boards = null, $style = null, $themedir = null)
	{
		$db = database();

		if (empty($id))
			return false;

		list ($text, $boards) = $this->_validateQueryParams($text, $boards);

		if (empty($text) && empty($boards) && empty($style))
			return false;

		$update = array();
		if ($text !== null)
			$update[] = 'prefix = {string:text}';

		if ($boards !== null)
			$update[] = 'id_boards = {string:boards}';
		else
			$boards = array();

		if (!empty($update))
		{
			$db->query('', '
				UPDATE {db_prefix}topic_prefix_text
				SET
					' . implode(',', $update) . '
				WHERE id_prefix = {int:current_id}',
				array(
					'text' => $text,
					'boards' => implode(',', $boards),
					'current_id' => $id
				)
			);
		}

		if ($style !== null)
			$this->_storeStyle($id, $style, $themedir);

		return $id;
	}

	protected function _validateQueryParams($text, $boards)
	{
		// @todo move to validation class
		if ($text !== null)
		{
			$text = trim(Util::htmlspecialchars($text));
			if (empty($text))
				return false;
		}

		if ($boards !== null)
			$boards = array_unique(array_filter(array_map('intval', $boards)));

		return array($text, $boards);
	}

	protected function _unWrap($style)
	{
		$style = trim($style);
		$style = trim(substr($style, strpos($style, '{') + 1));
		$style = trim(substr($style, 0, strpos($style, '}')));

		return $style;
	}

	public function styleToArray($style)
	{
		$style = $this->_unWrap($style);
		$styles = explode(';', $style);
		$return = array();
		foreach ($styles as $val)
		{
			$elem = explode(':', $val);
			if (count($elem) !== 2)
				continue;
			$k = trim($elem[0]);
			$v = trim($elem[1]);
			if (!empty($k) && !empty($v))
				$return[trim($elem[0])] = trim($elem[1]);
		}

		return $return;
	}

	protected function _wrapStyle($prefix_id, $style)
	{
		$style = trim($style);

		if (empty($style))
			return $style;

		$class = trim(substr($style, 0, strpos($style, '{')));

		if ($class === '.prefix_id_' . $prefix_id)
			return $style;
		else
			return '.prefix_id_' . $prefix_id . ' {
	' . $style . '
}';
	}

	protected function _storeStyle($prefix_id, $style, $themedir)
	{
		$style = $this->_wrapStyle($prefix_id, $style);

		if ($style == $this->_defaultStyle($prefix_id))
			return false;

		$file = $themedir . '/css/custom.css';
		$old_style = $this->_fetchStyle($file, $prefix_id);

		if ($old_style === false)
			return false;

		$file_content = file_get_contents($file);

		if ($old_style === true)
			$new_content = $file_content . "\n\n" . $style;
		else
			$new_content = str_replace($old_style, $style, $file_content);
		file_put_contents($file, $new_content);

		return true;
	}
}