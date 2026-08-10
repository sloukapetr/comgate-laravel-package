<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

class ComgatePaymentAvailability
{
    /**
     * @param  array<string, mixed>  $gateway
     * @param  array<string, scalar>  $filters
     * @param  array<int, string>  $requiredMethods
     * @param  array<int, string>  $availableMethods
     * @param  array<int, string>  $usableMethods
     * @param  array<int, string>  $usableRequiredMethods
     * @param  array<int, string>  $missingRequiredMethods
     * @param  array<int, array<string, mixed>>  $outages
     * @param  array<int, string>  $reasonCodes
     * @param  array<string, bool>  $checks
     */
    public function __construct(
        public readonly bool $available,
        public readonly string $summary,
        public readonly string $summaryKey,
        public readonly array $gateway,
        public readonly array $filters,
        public readonly array $requiredMethods,
        public readonly array $availableMethods,
        public readonly array $usableMethods,
        public readonly array $usableRequiredMethods,
        public readonly array $missingRequiredMethods,
        public readonly array $outages,
        public readonly array $reasonCodes,
        public readonly array $checks,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function hasReasonCode(string $code): bool
    {
        return in_array($code, $this->reasonCodes, true);
    }

    public function summaryFor(string $locale = 'en'): string
    {
        return $this->translateKey($this->summaryKey, $locale);
    }

    /**
     * @return array<int, string>
     */
    public function reasonMessages(string $locale = 'en'): array
    {
        return array_map(fn (string $code): string => $this->translateKey($code, $locale), $this->reasonCodes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'summary' => $this->summary,
            'summaryKey' => $this->summaryKey,
            'gateway' => $this->gateway,
            'filters' => $this->filters,
            'requiredMethods' => $this->requiredMethods,
            'availableMethods' => $this->availableMethods,
            'usableMethods' => $this->usableMethods,
            'usableRequiredMethods' => $this->usableRequiredMethods,
            'missingRequiredMethods' => $this->missingRequiredMethods,
            'outages' => $this->outages,
            'reasonCodes' => $this->reasonCodes,
            'reasons' => $this->reasonMessages(),
            'checks' => $this->checks,
        ];
    }

    private function translateKey(string $key, string $locale): string
    {
        $messages = [
            'en' => [
                'availability.ok' => 'Payment creation is currently available for the provided filters.',
                'availability.gateway_outage' => 'Payment creation is currently unavailable because the Comgate gateway reports an outage.',
                'availability.no_methods' => 'No payment methods are currently available for the provided filters.',
                'availability.required_methods_missing' => 'Required payment methods are not available for the provided filters.',
                'availability.all_methods_outage' => 'All currently available payment methods are affected by a Comgate outage.',
                'availability.required_methods_outage' => 'All required payment methods are currently affected by a Comgate outage.',
                'availability.unavailable' => 'Payment creation is currently unavailable for the provided filters.',
            ],
            'cs' => [
                'availability.ok' => 'Vytvoření platby je pro zadané filtry aktuálně dostupné.',
                'availability.gateway_outage' => 'Vytvoření platby je aktuálně nedostupné, protože Comgate gateway hlásí výpadek.',
                'availability.no_methods' => 'Pro zadané filtry nejsou aktuálně dostupné žádné platební metody.',
                'availability.required_methods_missing' => 'Požadované platební metody nejsou pro zadané filtry dostupné.',
                'availability.all_methods_outage' => 'Všechny aktuálně dostupné platební metody jsou zasažené výpadkem Comgate.',
                'availability.required_methods_outage' => 'Všechny požadované platební metody jsou aktuálně zasažené výpadkem Comgate.',
                'availability.unavailable' => 'Vytvoření platby je pro zadané filtry aktuálně nedostupné.',
            ],
        ];

        return $messages[$locale][$key] ?? $messages['en'][$key] ?? $key;
    }
}