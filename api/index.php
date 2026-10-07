<?php

declare(strict_types=1);

/**
 * Vercel PHP runtime entry point for Bagisto/Laravel.
 *
 * The `vercel-php` runtime invokes this file for every request that
 * `vercel.json` routes to it. It simply forwards to the standard
 * Laravel front controller so the application keeps a single
 * bootstrapping path across environments.
 */
require __DIR__.'/../public/index.php';
