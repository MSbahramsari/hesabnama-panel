<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class MoadianConfigurationException extends RuntimeException implements ShouldntReport {}
