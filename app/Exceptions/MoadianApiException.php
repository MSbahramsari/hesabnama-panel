<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class MoadianApiException extends RuntimeException implements ShouldntReport {}
