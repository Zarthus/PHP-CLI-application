<?php

/**
 * A basic CLI application
 *
 */

include('classes/clia.php');

/**
 * Create a new CLI application.
 */
$cli = new CLIA();

/**
 * Run the CLI with your own parameters.
 */
$cli->run();

/**
 * Exit the CLI, shutting down the program, and exit with the exit code.
 */
$cli->shutdown();

