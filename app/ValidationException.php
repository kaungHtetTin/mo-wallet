<?php
namespace App;

final class ValidationException extends \RuntimeException
{
    public function __construct(public array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
