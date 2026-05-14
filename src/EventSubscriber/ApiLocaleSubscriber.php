<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Establece Request::locale para la API según Accept-Language (o query ?locale=).
 * El frontend Angular envía `Accept-Language` en todas las peticiones a `/api/`
 * (repo **FalconCare**: `src/app/interceptors/locale.interceptor.ts` + `LanguageService`).
 * Idiomas soportados: ca, es, en, fr (alineados con ngx-translate).
 *
 * Contrato verificado en `tests/Controller/Api/ApiAcceptLanguageTest.php`.
 */
final class ApiLocaleSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public const SUPPORTED_LOCALES = ['ca', 'es', 'en', 'fr'];

    public function __construct(
        #[Autowire(service: 'translator')]
        private LocaleAwareInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Después de LocaleListener (16) y LocaleAwareListener (15): si no, el núcleo vuelve a fijar el locale por defecto.
            // LocaleAwareListener ya copió ese locale al traductor; hay que volver a sincronizar tras cambiar Request::locale.
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $fromQuery = $request->query->get('locale');
        if (\is_string($fromQuery) && $fromQuery !== '') {
            $normalized = $this->normalizeLocale($fromQuery);
            if ($normalized !== null) {
                $this->applyLocale($request, $normalized);

                return;
            }
        }

        foreach ($request->getLanguages() as $lang) {
            $normalized = $this->normalizeLocale($lang);
            if ($normalized !== null) {
                $this->applyLocale($request, $normalized);

                return;
            }
        }
    }

    private function applyLocale(Request $request, string $normalized): void
    {
        $request->setLocale($normalized);
        $this->translator->setLocale($normalized);
    }

    private function normalizeLocale(string $raw): ?string
    {
        $trimmed = strtolower(trim($raw));
        if ($trimmed === '') {
            return null;
        }

        $primary = preg_match('/^([a-z]{2})(?:[-_][a-z]+)?$/i', $trimmed, $m) === 1
            ? strtolower($m[1])
            : $trimmed;

        return \in_array($primary, self::SUPPORTED_LOCALES, true) ? $primary : null;
    }
}
