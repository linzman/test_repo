<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 *
 */

namespace Icewind\SMB;


class NotifyHandler implements INotifyHandler {
	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var string
	 */
	private $path;

	private $listening = true;

	/**
	 * @param Connection $connection
	 * @param string $path
	 */
	public function __construct(Connection $connection, $path) {
		$this->connection = $connection;
		$this->path = $path;
	}

	/**
	 * Get all changes detected since the start of the notify process or the last call to getChanges
	 *
	 * @return Change[]
	 */
	public function getChanges() {
		if (!$this->listening) {
			return [];
		}
		stream_set_blocking($this->connection->getOutputStream(), 0);
		$lines = [];
		while (($line = $this->connection->readLine())) {
			$lines[] = $line;
		}
		stream_set_blocking($this->connection->getOutputStream(), 1);
		return array_values(array_filter(array_map([$this, 'parseChangeLine'], $lines)));
	}

	/**
	 * Listen actively to all incoming changes
	 *
	 * Note that this is a blocking process and will cause the process to block forever if not explicitly terminated
	 *
	 * @param callable $callback
	 */
	public function listen($callback) {
		if ($this->listening) {
			$this->connection->read(function ($line) use ($callback) {
				return $callback($this->parseChangeLine($line));
			});
		}
	}

	private function parseChangeLine($line) {
		$code = (int)substr($line, 0, 4);
		if ($code === 0) {
			return null;
		}
		$subPath = str_replace('\\', '/', substr($line, 5));
		if ($this->path === '') {
			return new Change($code, $subPath);
		} else {
			return new Change($code, $this->path . '/' . $subPath);
		}
	}

	public function stop() {
		$this->listening = false;
		$this->connection->close();
	}

	public function __destruct() {
		$this->stop();
	}
}
