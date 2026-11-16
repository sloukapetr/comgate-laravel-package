# Pravidla pro vývoj balíčku comgate-laravel-package

Balíček: `sloukapetr/comgate-laravel-package`, namespace `Sloukapetr\ComgateLaravelPackage`. Univerzální Laravel balíček pro Comgate Merchant REST API v2.0 a Status API. Podpora PHP `^8.2`, Laravel 11, 12 a 13.

Dokumentace Comgate: https://apidoc.comgate.cz/api/rest/, https://apidoc.comgate.cz/push-notifikace, https://apidoc.comgate.cz/stavy-plateb, https://apidoc.comgate.cz/zabezpeceni, https://help.comgate.eu/docs/opakovane-platby

## Struktura (drž se jí)
- `src/ComgateService.php` – jediná transportní vrstva. Všechna HTTP volání jdou přes `request()` / `statusRequest()`. Nevytvářej paralelního klienta.
- `src/Data/` – typované DTO (`CreatePayment`, `RecurringCharge`, `RefundRequest`, `CreatedPayment`, `ComgatePaymentStatus`, `ComgateWebhookNotification`, `ComgatePaymentAvailability`).
- `src/Enums/PaymentStatus.php` – jediné mapování stavů Comgate.
- `src/Support/` – `PaymentAmount`, `PaymentLabel`, `ReturnUrl`.
- `src/Exceptions/` – vše dědí z `ComgateRequestException` (kromě validačních výjimek payloadu).
- `src/Http/Controllers/ComgateWebhookController.php` – webhook.
- `src/Fakes/ComgateFake.php` – `Comgate::fake()`. Dědí z `ComgateService` a přepisuje jen `request()`.
- `tests/` – kontrakt chování (PHPUnit + Orchestra Testbench).

## Veřejné API a kompatibilita
- Veřejné API = facade `Comgate`, veřejné metody `ComgateService`, DTO, enumy, výjimky, události, config klíče.
- Dodržuj SemVer. Změna chování nebo signatury veřejného API = major verze, nebo nová volba v configu s výchozím chováním a záznamem v Upgrade Notes. Když si nejsi jistý, zeptej se Petra.
- Každá nová veřejná metoda: PHPDoc na facade (`@method`), odkaz na sekci dokumentace Comgate, test, README, `CHANGELOG.md` (sekce Unreleased).
- Stávající pole-based metody (`createPayment(array)` …) zůstávají kvůli kompatibilitě. Nové věci stav typově (DTO).
- `declare(strict_types=1);`, `final` u nových tříd, které nejsou určené k dědění, `readonly` vlastnosti u DTO.

## Comgate
- Částky jsou vždy `int` v haléřích/centech. Žádné `float` ve výpočtech (`getPriceInMainUnit()` je jen pro zobrazení).
- `label` max 16 znaků (`PaymentLabel`), návratové URL přes `ReturnUrl` (zachovat `${id}` a `${refId}`).
- Kontroluj `code` v odpovědi i při HTTP 200 (`guardAgainstApiError`). Nové chybové kódy mapuj v `mapApiException()` na konkrétní výjimku.
- `redirect` URL neparsovat ani neupravovat.
- Stav platby rozhoduje status API (`findPayment()`). Data z webhooku jsou jen spouštěč.
- `secret` se nesmí dostat do logů, výjimek ani do payloadu událostí a DTO, které balíček předává aplikaci.
- Webhook vrací 2xx jen při úspěchu. Při dočasné chybě (Comgate nedostupný) vrátit 5xx, Comgate notifikaci zopakuje.
- Opakované platby: balíček jen strhává (`chargeRecurring()`), plán, období, souhlasy a doklady patří do aplikace. Každé stržení má nový `refId`.

## Co balíček nedělá
- Žádné UI, Blade ani Livewire.
- Žádné migrace a modely objednávek nebo předplatného (stav plateb drží aplikace). Pokud by to někdy mělo přibýt, jen jako volitelná vrstva po dohodě s Petrem.
- Žádné odkazy na konkrétní aplikaci (it-elektro, ŘídímTo).

## Testy
- Po každé změně `composer test`. Všechny testy musí projít.
- HTTP vrstva přes `Http::fake()`, žádná skutečná volání Comgate.
- Nová veřejná metoda nebo okrajový případ = cílený test.
- Testy pojmenovávej `test_…` v duchu existujících testů (PHPUnit, ne Pest).

## Vydání
- Postup v `RELEASE_CHECKLIST.md`. Release notes do `RELEASE_NOTES_vX.Y.Z.md`.
- `composer validate --strict` musí projít.
- Necommituj a netaguj. To dělá Petr.
