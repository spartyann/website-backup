<?php

namespace App;

class ExceptionWithCustomTrace extends \RuntimeException
{
    public static function withTrace(
        string $message,
        array $trace,
        int $code = 0,
        ?\Throwable $previous = null
    ): self {
        $e = new self($message, $code, $previous);

        // Seul moyen d'injecter une trace custom
        $prop = new \ReflectionProperty(\Exception::class, 'trace');
		
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

        $prop->setValue($e, $trace);

        return $e;
    }
}
