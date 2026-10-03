<?php

namespace App\Support\Appearance;

/** A refused appearance change (locked by the operator, throttled, or an invalid value). Message is user-safe. */
class AppearanceException extends \RuntimeException {}
