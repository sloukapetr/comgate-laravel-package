# 001 – webhook: ověření stavu přes status API a bez secretu v událostech

## Cíl
Události `ComgatePaymentPaid` / `ComgatePaymentFailed` se vyvolají jen podle stavu ověřeného přes status API. Do aplikace se nedostane `secret`.

## Kontext
- `ComgateWebhookController` dnes vyvolá `ComgatePaymentPaid` podle pole `status` z notifikace. Comgate výslovně píše, že notifikaci lze podvrhnout a stav je potřeba ověřit voláním status API (https://apidoc.comgate.cz/push-notifikace).
- `handleWebhook()` vrací celý payload včetně `secret` a ten jde do `$payload` událostí i do `ComgateWebhookNotification::$secret` a `toArray()`. Když ho aplikace zaloguje nebo uloží, secret unikne.
- Pro stav `AUTHORIZED` (předautorizace) dnes žádná typová událost není.
- `findPayment()` vrací `ComgatePaymentStatus` s enumem `PaymentStatus`.

## Kroky
1. Config `comgate.webhook.verify_status` (env `COMGATE_WEBHOOK_VERIFY_STATUS`, výchozí `true`).
2. V controlleru po ověření secretu:
   - když je `verify_status` zapnuté a notifikace má `transId`, zavolat `findPayment($transId)` (u druhého obchodu přes správné credentials, viz úkol 003 – zatím výchozí účet)
   - typovou událost vybírat podle **ověřeného** stavu, ne podle notifikace
   - když se ověřený stav liší od notifikace, zalogovat warning (bez secretu) a řídit se ověřeným stavem
   - `ComgateRequestException` z `findPayment` (nedostupnost, 5xx, timeout) → odpověď HTTP 503 bez detailů, žádná typová událost. Comgate notifikaci zopakuje.
   - `PaymentNotFoundException` → warning, odpověď 200 (opakování nepomůže), žádná typová událost
3. Události `ComgateWebhookReceived`, `ComgatePaymentPaid`, `ComgatePaymentFailed` dostanou novou nullable vlastnost `?ComgatePaymentStatus $verifiedStatus` (přidat na konec konstruktoru s defaultem `null`, kvůli kompatibilitě).
4. Nová událost `ComgatePaymentAuthorized` (stejný tvar) pro ověřený stav `AUTHORIZED`.
5. Odstranit `secret` z payloadu: `handleWebhook()` vrátí payload bez klíče `secret`. `ComgateWebhookNotification::$secret` ponech kvůli kompatibilitě, ale vždy `null`, a označ `@deprecated`.
6. README sekce Webhooks: ověřování stavu, `verifiedStatus`, chování při nedostupnosti, doporučení `matchesAmount()` proti částce objednávky. Upozornit, že události nesou `Request`, takže listener s `ShouldQueue` je nevhodný. Doporučený vzor: synchronní listener, který si uloží `transId` a dispatchne vlastní job.
7. `CHANGELOG.md` (Unreleased) včetně Upgrade Notes: s `verify_status = true` webhook volá status API a v testech aplikace je potřeba `Comgate::fake()` nebo `Http::fake()`.

## Nedělat
- Neměnit odpověď při úspěchu (`code=0&message=OK`, HTTP 200).
- Neodstraňovat existující události ani vlastnosti.

## Hotovo když (`composer test`)
- Notifikace `PAID`, status API vrátí `PAID` → `ComgatePaymentPaid` s vyplněným `verifiedStatus`.
- Notifikace `PAID`, status API vrátí `PENDING` → žádná typová událost, warning v logu.
- Notifikace `CANCELLED`, status API `CANCELLED` → `ComgatePaymentFailed`.
- Status `AUTHORIZED` → `ComgatePaymentAuthorized`.
- Status API vrátí HTTP 500 → odpověď 503, žádná typová událost.
- `verify_status = false` → dosavadní chování (podle notifikace).
- Payload žádné události ani `toArray()` notifikace neobsahuje `secret`.
- Stávající testy webhooku upravené tak, aby fakeovaly status API.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
