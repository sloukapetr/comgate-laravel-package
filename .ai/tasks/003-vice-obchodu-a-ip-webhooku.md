# 003 – webhook pro více obchodů a volitelná kontrola IP

## Cíl
Webhook funguje i pro druhý obchod Comgate (`withCredentials()`). Volitelně ověří, že požadavek přišel z IP Comgate.

## Kontext
- `withCredentials()` umí volat API za druhý obchod, ale `verifyWebhookSignature()` porovnává jen se `comgate.secret`. Notifikace druhého obchodu tak skončí 401.
- Notifikace obsahuje `merchant` (ID obchodu).
- Comgate zveřejňuje IP pro push notifikace na `https://payments.comgate.cz/ips-v4` a doporučuje seznam načítat zhruba jednou denně (https://apidoc.comgate.cz/zabezpeceni).

## Kroky
1. Config `comgate.merchants` (pole, výchozí `[]`): `['<merchant_id>' => ['secret' => '...', 'test' => bool|null]]`. Výchozí obchod (`merchant_id` + `secret`) platí dál.
2. Metoda `forMerchant(string $merchantId): static` – vrátí klienta pro výchozí obchod nebo pro obchod z `merchants` (přes `withCredentials`). Neznámé ID → `ComgateRequestException`.
3. `verifyWebhookSignature()`: když payload obsahuje `merchant`, porovnat secret toho obchodu. Bez `merchant` dosavadní chování. Neznámý merchant → `false`.
4. Ověření stavu z úkolu 001 volat přes `forMerchant($notification->merchant)`.
5. Config `comgate.webhook.verify_ip` (výchozí `false`) a `comgate.webhook.ips_url` (výchozí `https://payments.comgate.cz/ips-v4`). Při zapnutí ověřit `$request->ip()` přes `IpUtils::checkIp` proti seznamu z cache (24 h). Když se seznam nepodaří stáhnout, kontrolu přeskočit a zalogovat warning. IP mimo seznam → 403.
6. README: více obchodů (config + webhook), kontrola IP, upozornění na trusted proxies a Cloudflare (bez správného `trustProxies` je `$request->ip()` IP proxy).
7. `CHANGELOG.md` (Unreleased).

## Nedělat
- Neměnit chování pro instalace s jedním obchodem.

## Hotovo když (`composer test`)
- Notifikace s `merchant` z `merchants` a jeho secretem → 200. Se secretem výchozího obchodu → 401.
- `forMerchant()` posílá Basic auth správného obchodu.
- `verify_ip`: IP v rozsahu → 200, mimo → 403, nedostupný seznam → 200 + warning.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
