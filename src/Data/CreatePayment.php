<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Data;

use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidPaymentPayloadException;
use Sloukapetr\ComgateLaravelPackage\Support\PaymentAmount;
use Sloukapetr\ComgateLaravelPackage\Support\PaymentLabel;
use Sloukapetr\ComgateLaravelPackage\Support\ReturnUrl;

use function config;

final class CreatePayment
{
    public const CATEGORY_OTHER = 'OTHER';

    public const CATEGORY_PHYSICAL = 'PHYSICAL_GOODS_ONLY';

    public const DELIVERY_ELECTRONIC = 'ELECTRONIC_DELIVERY';

    public const DELIVERY_HOME = 'HOME_DELIVERY';

    public const DELIVERY_PICKUP = 'PICKUP';

    public readonly string $refId;

    public readonly string $product;

    public readonly string $label;

    public readonly string $currency;

    public readonly string $country;

    public readonly string $lang;

    public readonly ?string $email;

    public readonly ?string $phone;

    public readonly ?string $urlPaid;

    public readonly ?string $urlCancelled;

    public readonly ?string $urlPending;

    /**
     * @param  string|null  $expirationTime  Comgate format, for example 30m, 10h or 2d. Between 30 minutes and 7 days.
     */
    public function __construct(
        public readonly int $price,
        string $refId,
        string $product,
        ?string $email = null,
        ?string $phone = null,
        string $currency = 'CZK',
        public readonly string $method = 'ALL',
        string $country = 'CZ',
        string $lang = 'cs',
        public readonly string $category = self::CATEGORY_OTHER,
        public readonly string $delivery = self::DELIVERY_ELECTRONIC,
        public readonly ?string $fullName = null,
        public readonly bool $initRecurring = false,
        public readonly bool $preauth = false,
        ?string $urlPaid = null,
        ?string $urlCancelled = null,
        ?string $urlPending = null,
        public readonly ?string $expirationTime = null,
        public readonly ?bool $test = null,
        public readonly ?string $account = null,
    ) {
        $this->refId = trim($refId);
        $this->product = trim((string) preg_replace('/\s+/u', ' ', $product));
        $this->label = PaymentLabel::fromProduct($this->product);
        $this->currency = strtoupper(trim($currency));
        $this->country = strtoupper(trim($country));
        $this->lang = strtolower(trim($lang));
        $this->email = self::blankToNull($email);
        $this->phone = self::blankToNull($phone);
        $this->urlPaid = self::placeholderUrl($urlPaid);
        $this->urlCancelled = self::placeholderUrl($urlCancelled);
        $this->urlPending = self::placeholderUrl($urlPending);

        $this->assertValid();
    }

    /**
     * One-off or initial recurring payment for a digital product: course, plan or subscription.
     */
    public static function digital(
        int $price,
        string $refId,
        string $product,
        ?string $email = null,
        ?string $phone = null,
        bool $initRecurring = false,
        ?string $urlPaid = null,
        ?string $urlCancelled = null,
        ?string $urlPending = null,
        ?string $fullName = null,
        ?string $method = null,
        ?string $currency = null,
        ?string $expirationTime = null,
        ?string $account = null,
    ): self {
        return new self(
            price: $price,
            refId: $refId,
            product: $product,
            email: $email,
            phone: $phone,
            currency: $currency ?? (string) config('comgate.defaults.currency', 'CZK'),
            method: $method ?? (string) config('comgate.defaults.method', 'ALL'),
            country: (string) config('comgate.defaults.country', 'CZ'),
            lang: (string) config('comgate.defaults.lang', 'cs'),
            category: (string) config('comgate.defaults.category', self::CATEGORY_OTHER),
            delivery: (string) config('comgate.defaults.delivery', self::DELIVERY_ELECTRONIC),
            fullName: $fullName,
            initRecurring: $initRecurring,
            urlPaid: $urlPaid,
            urlCancelled: $urlCancelled,
            urlPending: $urlPending,
            expirationTime: $expirationTime,
            account: $account,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'price' => $this->price,
            'curr' => $this->currency,
            'label' => $this->label,
            'refId' => $this->refId,
            'method' => $this->method,
            'country' => $this->country,
            'lang' => $this->lang,
            'category' => $this->category,
            'delivery' => $this->delivery,
            'name' => $this->product,
        ];

        if ($this->email !== null) {
            $payload['email'] = $this->email;
        }

        if ($this->phone !== null) {
            $payload['phone'] = $this->phone;
        }

        if ($this->fullName !== null && trim($this->fullName) !== '') {
            $payload['fullName'] = trim($this->fullName);
        }

        if ($this->initRecurring) {
            $payload['initRecurring'] = true;
        }

        if ($this->preauth) {
            $payload['preauth'] = true;
        }

        if ($this->urlPaid !== null) {
            $payload['url_paid'] = $this->urlPaid;
        }

        if ($this->urlCancelled !== null) {
            $payload['url_cancelled'] = $this->urlCancelled;
        }

        if ($this->urlPending !== null) {
            $payload['url_pending'] = $this->urlPending;
        }

        if ($this->expirationTime !== null) {
            $payload['expirationTime'] = $this->expirationTime;
        }

        if ($this->test !== null) {
            $payload['test'] = $this->test;
        }

        if ($this->account !== null && $this->account !== '') {
            $payload['account'] = $this->account;
        }

        return $payload;
    }

    private function assertValid(): void
    {
        if ($this->refId === '') {
            throw new InvalidPaymentPayloadException('refId is required. Use the internal payment id or a variable symbol.');
        }

        if (mb_strlen($this->refId) > 255) {
            throw new InvalidPaymentPayloadException('refId cannot be longer than 255 characters.');
        }

        PaymentAmount::assert($this->currency, $this->price, $this->initRecurring);

        if ($this->email === null && $this->phone === null) {
            throw new InvalidPaymentPayloadException('Comgate requires a payer email or a phone number.');
        }

        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPaymentPayloadException('Payer email is not valid.');
        }

        if ($this->phone !== null && preg_match('/^\+[0-9]{8,15}$/', $this->phone) !== 1) {
            throw new InvalidPaymentPayloadException('Payer phone must be in international format, for example +420777112233.');
        }

        if ($this->method === '') {
            throw new InvalidPaymentPayloadException('Payment method cannot be empty. Use ALL or a specific method id.');
        }

        if (! in_array($this->category, [self::CATEGORY_OTHER, self::CATEGORY_PHYSICAL], true)) {
            throw new InvalidPaymentPayloadException('Category must be OTHER or PHYSICAL_GOODS_ONLY.');
        }

        if (! in_array($this->delivery, [self::DELIVERY_ELECTRONIC, self::DELIVERY_HOME, self::DELIVERY_PICKUP], true)) {
            throw new InvalidPaymentPayloadException('Delivery must be ELECTRONIC_DELIVERY, HOME_DELIVERY or PICKUP.');
        }

        if (preg_match('/^[a-z]{2}$/', $this->lang) !== 1) {
            throw new InvalidPaymentPayloadException('Language must be an ISO 639-1 code.');
        }

        if ($this->country !== 'ALL' && preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new InvalidPaymentPayloadException('Country must be ALL or an ISO 3166 alpha-2 code.');
        }

        if ($this->initRecurring && $this->preauth) {
            throw new InvalidPaymentPayloadException('A payment cannot be both an initial recurring payment and a pre-authorization.');
        }

        $this->assertExpiration();
    }

    private function assertExpiration(): void
    {
        if ($this->expirationTime === null) {
            return;
        }

        if (preg_match('/^([1-9]\d*)([mhd])$/', $this->expirationTime, $matches) !== 1) {
            throw new InvalidPaymentPayloadException('expirationTime must look like 30m, 10h or 2d.');
        }

        $amount = (int) $matches[1];
        $minutes = match ($matches[2]) {
            'm' => $amount,
            'h' => $amount * 60,
            'd' => $amount * 1440,
            default => 0,
        };

        if ($minutes < 30 || $minutes > 7 * 1440) {
            throw new InvalidPaymentPayloadException('expirationTime must be between 30 minutes and 7 days.');
        }
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function placeholderUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return ReturnUrl::withPlaceholders($url);
    }
}
