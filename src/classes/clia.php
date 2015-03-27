<?php

class CLIA {
	const EXIT_CODE_OK = 0;
	const EXIT_CODE_NOTCLI = 1;
	const EXIT_CODE_CMD_NOT_SUCCESS = 2;
	const EXIT_CODE_NO_COMMAND = 3;
	const EXIT_CODE_CANNOT_EXEC = 4;
	const EXIT_CODE_NO_DB = 5;

	public static $APP_NAME = 'Core';
	public static $APP_VERSION = '0.1';

	/**
	 * @var string
	 */
	public $command;

	/**
	 * @var array
	 */
	public $commands = [];

	/**
	 * @var array
	 */
	public $options = [];

	/**
	 * @var int
	 */
	public $verbose = 0;

	/**
	 * @var bool
	 */
	public $quiet = false;

	/**
	 * @var int
	 */
	public $code;

	/**
	 * @var array
	 */
	public $args;

	/**
	 * @var array
	 */
	public $argv;

	/**
	 * @var int
	 */
	public $argc;

	/**
	 * @var bool
	 */
	public $db_set = false;

	/**
	 * @var string database host
	 */
	protected $db_host;

	/**
	 * @var string database username
	 */
	protected $db_user;

	/**
	 * @var string database password
	 */
	protected $db_pass;

	/**
	 * @var string database db name
	 */
	protected $db_name;

	/**
	 * @var bool
	 */
	protected $app_run = false;

	/**
	 * @param bool $register_initial when turned on the default commands are not registered.
	 *
	 * @throws Exception if not running from command line interface.
	 */
	public function __construct($register_initial = true) {
		if (PHP_SAPI != 'cli') {
			$this->println('This script can only be run from the command line interface.');
			$this->println('It does not offer a GUI.');

			throw new Exception('This application can only be run via the Command Line Interface.');
		}

		$this->argv = $GLOBALS['argv'];
		$this->argc = $GLOBALS['argc'];

		if ($this->argc < 2) {
			$this->command = null;
			$this->args = null;
			$this->code = self::EXIT_CODE_NO_COMMAND;
		} else {
			$this->command = strtolower(trim($this->argv[1]));
			$this->args = array_slice($this->argv, 2, $this->argc);
			$this->code = self::EXIT_CODE_OK;
		}

		if ($register_initial) {
			$this->registerCommand('help', 'show this command output', 'cmd_help');
			$this->registerCommand('version', 'print application information', 'cmd_version');
			$this->registerCommand('verbose', 'increase verbosity', 'cmd_verbose', true);
			$this->registerCommand('quiet', 'do not print anything at all', 'cmd_quiet', true);

			$this->registerOption('verbose', 'v', ['verbose']);
			$this->registerOption('help', 'h', ['help']);
			$this->registerOption('quiet', 'q', ['quiet', 'silent']);
		}
	}

	/**
	 * @param string $command
	 * @param string $description
	 * @param string $handler
	 * @param bool $hidden
	 *
	 * @return bool
	 * @throws Exception if registering duplicate commands
	 */
	public function registerCommand($command, $description, $handler, $hidden = false) {
		$command = strtolower($command);
		$handleCmd = 'cmd_' . str_replace('cmd_', '', $handler);

		if (method_exists($this, $handleCmd)) {
			if (!$this->isCommand($command)) {
				$this->commands[$command] = ['command' => $command, 'description' => $description, 'handler' => $handleCmd, 'hidden' => $hidden];
				return true;
			} else {
				throw new Exception("Trying to register duplicate command '" . $command . "'.");
			}
		}

		return false;
	}

	/**
	 * @param string $command
	 * @param string $opts
	 * @param array $longopts
	 *
	 * @return bool
	 */
	public function registerOption($command, $opts, array $longopts) {
		$command = strtolower($command);

		if ($this->isCommand($command) && !$this->isOption($command)) {
			$this->options[$command] = ['command' => $command, 'opts' => $opts, 'longopts' => $longopts];
			return true;
		}

		return false;
	}

	/**
	 * @param bool $allow_rerun allow re-running of the application.
	 *
	 * @throws Exception if the application has already run before.
	 */
	public function run($allow_rerun = false) {
		if ($this->app_run && !$allow_rerun)
			throw new Exception("The application has already ran before. Cannot run again.");

		if ($this->command !== null && substr($this->command, 0, 1) == '-') {
			foreach ($this->argv as $arg) {
				if ($this->isCommand($arg)) {
					$this->command = $arg;
					break;
				}
			}
		}

		$this->app_run = true;
		$optionarg = $this->isOptionArg($this->command);

		if ($this->command === null) {
			$this->println('No command was supplied. Nothing to execute.');
			$this->newline();

			$this->cmd_help();
			$this->code = self::EXIT_CODE_NO_COMMAND;
		} else if (!$this->isCommand($this->command) && !$optionarg) {
			$this->println('The command "' . $this->command . '" was not recognized. Nothing to execute.');
			$this->newline();

			$this->cmd_help();
			$this->code = self::EXIT_CODE_NO_COMMAND;
		} else {
			foreach ($this->options as $option) {
				$this->processOption($option);
			}

			if (!$optionarg) {
				$cmd = $this->commands[$this->command]['handler'];
				$this->$cmd();
			}
		}
	}

	/**
	 * exits the script.
	 */
	public function shutdown() {
		$this->newline();

		if ($this->code == self::EXIT_CODE_OK) {
			$this->printverbose("No errors occured. Exiting cleanly..");
		} else {
			$this->println("Errors occured. Exit code: " . $this->code);
		}

		exit($this->code);
	}


	/**
	 * default command: display help
	 */
	public function cmd_help() {
		$this->println(static::$APP_NAME . ' CLI v' . static::$APP_VERSION);

		if (count($this->options) == 0 && count($this->commands) == 0) {
			$this->newline();
			$this->println('This application does not support any commands or options.');
		} else {
			$this->newline();
			if (count($this->commands) == 0) {
				$this->println('This application has no commands associated with it.');
			} else {
				$this->println('Supported commands: ');

				$_commands = $this->commands;
				asort($_commands);

				foreach ($_commands as $command) {
					if ($command['hidden'])
						continue;

					$len = (int)((strlen($command['command']) + 2) / 8);
					$tabsize = str_repeat("\t", 4 - $len);
					$this->println('  ' . $command['command'] . $tabsize . $command['description']);
				}
			}

			$this->newline();
			if (count($this->options) == 0) {
				$this->println('This application has no options associated with it.');
			} else {
				$this->println('Supported options: ');

				$_options = $this->options;

				foreach ($_options as $key => $value) {
					$_options[$key]['opts'] = str_replace(':', '', $_options[$key]['opts']);
					$_options[$key]['longopts'] = str_replace(':', '', $_options[$key]['longopts']);
				}

				asort($_options);
				foreach ($_options as $option) {
					$opts = [];

					for ($i = 0; $i < strlen($option['opts']); $i++) {
						$opts[] = $option['opts'][$i];
					}

					$o = '-' . implode(', -', $opts) . ', --' . implode(', --', $option['longopts']);

					$len = (int)((strlen($o) + 2) / 8);
					$tabsize = str_repeat("\t", 4 - $len);
					$description = $this->commands[$option['command']]['description'];

					$this->println('  ' . $o . $tabsize . $description);
				}
			}
		}
	}

	public function cmd_version() {
		$this->println(static::$APP_NAME . ' CLI v' . static::$APP_VERSION);
	}

	/**
	 * default command: turn verbosity on
	 */
	public function cmd_verbose() {
		$this->verbose++;
	}

	/**
	 * Default command: turn quiet on
	 *
	 * (if verbose is set, this takes precedence)
	 */
	public function cmd_quiet() {
		$this->quiet = true;
	}

	/**
	 * Prints a newline
	 */
	public function newline() {
		if (!$this->isQuiet())
			echo "\n";
	}

	/**
	 * @param string $message print message to terminal
	 */
	public function prnt($message) {
		if (!$this->isQuiet())
			echo $message;
	}

	/**
	 * @param string $message
	 */
	public function println($message) {
		$this->prnt($message);
		$this->newline();
	}

	/**
	 * @param string $message
	 * @param int $verbosity requires verbosity to print
	 */
	public function printverbose($message, $verbosity = 1) {
		if ($this->verbose >= $verbosity) {
			$this->println($message);
		}
	}

	/**
	 * @param string $message
	 */
	public function debug($message) {
		$this->printverbose($message, 2);
	}

	/**
	 * @param string $command
	 *
	 * @return bool
	 */
	public function isCommand($command) {
		$command = strtolower($command);

		return array_key_exists($command, $this->commands);
	}

	/**
	 * @param string $option
	 *
	 * @return bool
	 */
	public function isOption($option) {
		$option = strtolower($option);

		return array_key_exists($option, $this->options);
	}

	/**
	 * Check if option is in the list of available options
	 *
	 * @param string $option
	 *
	 * @return bool
	 */
	public function isOptionArg($option) {
		$option = strtolower($option);

		if (substr($option, 0, 1) == '-') {

			$opt = ltrim($option, '-');
			foreach ($this->options as $_option) {
				if (substr_count($_option['opts'], $opt) != 0 || in_array($opt, $_option['longopts'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return boolean
	 */
	public function hasAppRun() {
		return $this->app_run;
	}

	/**
	 * @return array
	 */
	public function getArgs() {
		return $this->args;
	}

	/**
	 * @return int
	 */
	public function getCode() {
		return $this->code;
	}

	/**
	 * @param int $code
	 */
	protected function setCode($code) {
		$this->code = $code;
	}

	/**
	 * @return string
	 */
	public function getCommand() {
		return $this->command;
	}

	/**
	 * @return array
	 */
	public function getCommands() {
		return $this->commands;
	}

	/**
	 * Set database information to allow for commands to be executed
	 * that involve the database. Such as importing from a .sql file. (CLI::importSQL)
	 *
	 * @param string $db_host
	 * @param string $db_user
	 * @param string $db_pass
	 * @param string $db_name
	 */
	public function setDB($db_host, $db_user, $db_pass, $db_name) {
		$this->db_set = true;

		$this->db_host = $db_host;
		$this->db_user = $db_user;
		$this->db_pass = $db_pass;
		$this->db_name = $db_name;
	}

	/**
	 * @return boolean
	 */
	public function isDbSet() {
		return $this->db_set;
	}

	/**
	 * @return bool
	 */
	public function isOk() {
		return $this->code == self::EXIT_CODE_OK;
	}

	/**
	 * @return boolean
	 */
	public function isQuiet() {
		return $this->quiet;
	}

	/**
	 * @return int
	 */
	public function getVerbose() {
		return $this->verbose;
	}

	/**
	 * @param array $option
	 *
	 * @return bool
	 */
	protected function processOption(array $option) {
		$name = $option['command'];
		$short = $option['opts'];
		$long = $option['longopts'];

		if (!empty(getopt($short, $long))) {
			if ($this->verbose) {
				$o = empty($short) ? $long[0] : $short;

				$this->println('Processing option: ' . $o);
			}

			$cmd = $this->commands[$name]['handler'];
			$this->$cmd();
			return true;
		}

		return false;
	}

	/**
	 * Import an SQL file.
	 *
	 * @param string $file
	 *
	 * @return bool|array each line of output returned by the query
	 */
	protected function importSQL($file) {
		if ($this->requireDB() === false) {
			$this->code = self::EXIT_CODE_CMD_NOT_SUCCESS;
			return false;
		}

		$command = 'mysql -h' . $this->db_host . ' -u' . $this->db_user . ' -p' . $this->db_pass . ' < ' . $file;
		exec($command, $output, $return);

		if ($this->verbose) {
			$this->newline();
			$this->println('Imported file: ' . $file);
		}

		if ($return != 0) {
			$this->code = self::EXIT_CODE_CMD_NOT_SUCCESS;
		}

		return $output;
	}

	/**
	 * @param string $query
	 *
	 * @return bool|array each line of output returned by the query
	 */
	protected function sql($query) {
		if ($this->requireDB() === false) {
			$this->code = self::EXIT_CODE_CMD_NOT_SUCCESS;
			return false;
		}

		$command = 'mysql -h' . $this->db_host . ' -u' . $this->db_user . ' -p' . $this->db_pass . ' -D' . $this->db_name .
			' --execute="' . str_replace('"', '\"', $query) . '"';
		exec($command, $output, $return);

		if ($this->verbose) {
			$this->newline();
			$this->println('Executed Query: ' . $query);
		}

		if ($return != 0) {
			$this->code = self::EXIT_CODE_CMD_NOT_SUCCESS;
		}

		return $output;
	}

	/**
	 * Check is setDB was called prior to using SQL related methods.
	 *
	 * @return bool
	 */
	protected function requireDB() {
		if ($this->db_set === false) {
			$this->debug("A database is required to be set. Call self::setDB() first.");

			$this->code = self::EXIT_CODE_NO_DB;
			return false;
		}

		return true;
	}
}