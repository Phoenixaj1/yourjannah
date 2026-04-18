<?php
/**
 * Community Engagement — Backward compatibility wrapper.
 *
 * All gamification logic has moved to the yn-jannah-gamification plugin.
 * This file defines fallback functions only if the plugin is not active.
 * When the plugin IS active, it defines these functions first (plugins_loaded),
 * so the function_exists() checks here will skip them.
 *
 * @package YourJannah
 * @since   3.11.0
 * @deprecated Use yn-jannah-gamification plugin instead.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// If the gamification plugin is active, all functions are already defined.
// Nothing to do here — the plugin handles everything.
