<?php

class SomeCLI extends CLIA {
	const APP_NAME = 'Example Application';
	const APP_VERSION = '0.1';

	/**
	 * @param bool $register_initial when turned on the default commands are not registered.
	 *
	 * @throws Exception if not running from command line interface.
	 */
	public function __construct($register_initial) {
		if ($register_initial) {
			$this->registerCommand('printone', 'prints "one" to the terminal', 'printone');
		}
	}

	/**
	 * Commands are prepended with 'cmd_', this prevents
	 * running internal methods.
	 */
	public function cmd_printone() {
		$this->println("one");
	}
}