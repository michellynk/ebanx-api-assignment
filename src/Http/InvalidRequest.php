<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

/**
 * Requisição mal formada (JSON inválido, campo faltando, tipo errado).
 * Fica em Http, e não em Exceptions, porque é um erro de formato, não de regra.
 */
final class InvalidRequest extends InvalidArgumentException
{
}
