# 002 – vynucené IPv4 a rozlišení síťových chyb

## Cíl
Volání API projde IP whitelistem Comgate i na serveru s IPv6. Aplikace pozná chybu, kterou má smysl opakovat.

## Kontext
- Comgate povoluje volání jen z IP adres zadaných v portálu (formát `IPv4` nebo `IPv4/MASKA`, https://apidoc.comgate.cz/zabezpeceni). Server s IPv4 i IPv6 může volat přes IPv6 a whitelist pak volání odmítne.
- `ComgateService::http()` dnes nenastavuje IP verzi.
- Timeout nebo nedostupné spojení dnes vyhodí `Illuminate\Http\Client\ConnectionException`, která nedědí z `ComgateRequestException`. Aplikace tak musí chytat dvě různé výjimky.

## Kroky
1. Config `comgate.force_ipv4` (env `COMGATE_FORCE_IPV4`, výchozí `false`, aby se nezměnilo chování stávajících instalací). Při `true` přidat do `http()` i `statusHttp()` `withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])`.
2. Nová výjimka `ComgateConnectionException extends ComgateRequestException` (statusCode `0`). V `request()` a `statusRequest()` chytat `ConnectionException` a vyhodit ji s původní výjimkou jako `previous`.
3. Metoda `ComgateRequestException::isRetryable(): bool` – `true` pro `ComgateConnectionException` a HTTP 5xx (`statusCode() >= 500`), jinak `false`.
4. README: sekce o IP whitelistu (portál Comgate → propojení obchodu → povolené IP, kdy zapnout `force_ipv4`) a o `isRetryable()` v jobech.
5. `CHANGELOG.md` (Unreleased).

## Nedělat
- Neměnit výchozí hodnotu `force_ipv4` na `true` bez souhlasu Petra.

## Hotovo když (`composer test`)
- `force_ipv4 = true` → request má curl volbu `CURLOPT_IPRESOLVE` (ověř přes `Http::fake` a callback na `PendingRequest`, nebo testem metody `http()` přes podtřídu).
- `Http::fake` s `throw new ConnectionException` → `ComgateConnectionException`, `isRetryable() === true`.
- HTTP 503 → `isRetryable() === true`. API kód 1309 s HTTP 200 → `false`.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
