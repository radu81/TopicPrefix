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

class TopicPrefix_PxCRUD
{
	public function __construct()
	{
		$this->db = database();
	}

	public function create($text, $board = null)
	{
		$board = $board === null ? 0 : $board;

		$this->db->insert('',
			'{db_prefix}topic_prefix_text',
			array(
				'prefix' => 'string-30',
				'id_board' => 'string',
			),
			array($text, $board),
			array('id_prefix')
		);
	}

	public function countAll()
	{
		return $this->count('all');
	}

	public function countByText($text)
	{
		return $this->count('text', $text);
	}

	public function countInBoard($text, $board)
	{
		return $this->count('text', array('text' => $text, 'board' => (int) $board));
	}

	public function getAll()
	{
		return $this->read('all', null);
	}

	public function getById($id)
	{
		$vals = $this->read('id', $id);

		if (empty($vals))
			return false;
		else
			return $vals[0];
	}

	public function getByText($text)
	{
		return $this->read('text', $text);
	}

	public function getInBoard($text, $board)
	{
		return $this->read('text', array('text' => $text, 'board' => (int) $board));
	}

	public function deleteById($id)
	{
		return $this->delete('id', $id);
	}

	public function deleteByText($text)
	{
		return $this->delete('text', $text);
	}

	public function deleteInBoards($text, $board)
	{
		return $this->delete('text', array('text' => $text, 'board' => (int) $board));
	}

	public function updateById($id, $newtext = null, $boards = array())
	{
		return $this->update($newtext, $boards, 'id', array('id' => $id));
	}

	public function updateByText($text, $newtext = null, $boards = array())
	{
		return $this->update($newtext, $boards, 'text', array('text' => $text));
	}

	protected function count($type, $value)
	{
		$request = $this->runQuery('
			SELECT COUNT(*)
			FROM {db_prefix}topic_prefix_text', $type, $value);
		list ($num) = $this->db->fetch_row($request);
		$this->db->free_result($request);

		return $num;
	}

	protected function fillUpdatePairs($text = null, $boards = array())
	{
		$pairs = array();
		if ($newtext !== null && $newtext !== '')
			$pairs['prefix'] = $newtext;
		if (!empty($boards))
			$pairs['boards'] = (array) $boards;

		return $pairs;
	}

	protected function update($newtext, $boards, $type, $value)
	{
		$known_types = array(
			'prefix' => 'string-30',
			'boards' => 'string',
		);

		$pairs = $this->fillUpdatePairs($newtext, $boards);
		$statement = array();
		foreach ($pairs as $key => $val)
		{
			if (isset($known_types[$key]))
			{
				$statement[] = $key . ' = {' . $known_types[$key] . ':' . $key . '}';
				$value[$key] = $val;
			}
		}

		if (empty($statement))
			return false;

		return $this->runQuery('
			UPDATE {db_prefix}topic_prefix_text
			SET ' . implode(', ', $statement), $type, $value);
	}

	protected function delete($type, $value)
	{
		return $this->runQuery('
			DELETE
			FROM {db_prefix}topic_prefix_text', $type, $value);
	}

	protected function read($type, $value)
	{
		$request = $this->runQuery('
			SELECT id_prefix, prefix, id_boards
			FROM {db_prefix}topic_prefix_text', $type, $value);

		$return = array();
		while ($row = $this->db->fetch_assoc($request))
			$return[] = $row;

		$this->db->free_result($request);

		return $return;
	}

	protected function runQuery($statement, $type, $value)
	{
		$known_types = array(
			'id' => 'id_prefix IN ({array_int:id})',
			'text' => 'prefix LIKE {string:prefix}',
			'all' => '1 = 1',
		);

		if (!isset($known_types[$type]))
			return false;

		return $this->db->query('', $statement . '
			WHERE ' . $known_types[$type] . (isset($value['board']) ? '
				AND FIND_IN_SET({int:board}, id_boards)' : ''),
			array(
				'id' => isset($value['id']) ? (array) $value['id'] : (array) $value,
				'prefix' => isset($value['text']) ? $value['text'] : $value,
				'board' => isset($value['board']) ? $value['board'] : 0,
				'boards' => isset($value['boards']) ? $value['boards'] : 0,
			)
		);
	}
}