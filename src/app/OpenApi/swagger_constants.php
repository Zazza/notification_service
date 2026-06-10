<?php

/**
 * Swagger/OpenAPI constants for IDE autocompletion.
 *
 * L5-Swagger defines these at scan time via config,
 * this file ensures the IDE knows about them too.
 */

if (!defined('L5_SWAGGER_CONST_HOST')) {
    define('L5_SWAGGER_CONST_HOST', env('L5_SWAGGER_CONST_HOST', 'http://localhost'));
}
