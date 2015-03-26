<?php

/**
 * A basic custom CLI application
 *
 */

include('classes/clia.php');
include('classes/somecli.php');

/**
 * Create a new CLI application.
 */
$cli = new SomeCLI();

/**
 * Run the CLI with your own parameters.
 */
$cli->run();

/**
 * Exit the CLI, shutting down the program, and exit with the exit code.
 */
$cli->shutdown();

