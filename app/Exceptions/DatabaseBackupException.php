<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de respaldo cuyo mensaje puede mostrarse directamente al usuario.
 */
class DatabaseBackupException extends RuntimeException {}
