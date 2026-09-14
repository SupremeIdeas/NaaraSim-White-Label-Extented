<?php

namespace App\Services\Updater;

use RuntimeException;

/** A project-intake action could not proceed (e.g. not licensed yet, or a
 *  deploy timeline set before the intake was reviewed). */
class WhiteLabelProjectIntakeException extends RuntimeException
{
}
