<?php

declare(strict_types=1);

namespace App\Controller\Api\Concerns;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traducciones dominio {@see TranslatorInterface} `api` (claves `error.*`, `http.*`).
 * Requiere propiedad {@see TranslatorInterface} $translator en la clase que use el trait.
 *
 * @property-read TranslatorInterface $translator
 */
trait ApiTranslatorTrait
{
    protected function apiTrans(string $errorKey, array $parameters = []): string
    {
        return $this->translator->trans('error.'.$errorKey, $parameters, 'api');
    }

    protected function apiHttpLine(int $status): string
    {
        $key = 'http.'.$status;
        $label = $this->translator->trans($key, [], 'api');

        return $label !== $key ? $label : (Response::$statusTexts[$status] ?? 'Error');
    }
}
