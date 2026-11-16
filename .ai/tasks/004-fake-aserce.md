# 004 – aserce a simulace webhooku v `Comgate::fake()`

## Cíl
Aplikace píše testy plateb čitelně, bez ručního procházení `calls()`.

## Kontext
- `ComgateFake` dnes nahrává volání (`calls()`) a vrací nastavené odpovědi (`setResponse()`), ale nemá aserce.
- Po úkolu 001 webhook volá status API, takže aplikace potřebuje jednoduše nasimulovat „zaplaceno“.

## Kroky
1. Aserce na `ComgateFake` (PHPUnit `Assert`):
   - `assertPaymentCreated(?callable $callback = null)`
   - `assertRecurringCharged(?callable $callback = null)`
   - `assertRefunded(?string $transId = null, ?int $amount = null)`
   - `assertPaymentCancelled(?string $transId = null)`
   - `assertStatusChecked(?string $transId = null)`
   - `assertNothingSent()`
   - callback dostane payload volání a vrací bool
2. Pohodlné odpovědi: `willReturnStatus(string $transId, PaymentStatus $status, int $price, string $curr = 'CZK')` nastaví odpověď pro `status`.
3. Helper `webhookPayload(string $transId, PaymentStatus $status, array $overrides = []): array` – vrátí platný payload notifikace se secretem z configu. Aplikace pak volá `$this->post(route('comgate.webhook'), Comgate::fake()->webhookPayload(...))`.
4. README sekce Testing s příkladem celého toku: vytvoření platby → `willReturnStatus(PAID)` → POST webhooku → aserce na doménový stav.
5. `CHANGELOG.md` (Unreleased).

## Hotovo když (`composer test`)
- Každá aserce má test na úspěch i selhání (`expectException(AssertionFailedError::class)`).
- Test celého toku přes webhook s `Comgate::fake()` bez `Http::fake()`.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
