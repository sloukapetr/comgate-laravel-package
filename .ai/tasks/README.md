# Úkoly – comgate-laravel-package

Jeden úkol = jeden soubor `.ai/tasks/NNN-nazev.md` = jeden commit. Části: Cíl, Kontext, Kroky, Nedělat, Hotovo když, Ruční kroky pro Petra, Po dokončení.

Stavy: `todo` · `doing` · `done` · `blocked`

| # | Úkol | Stav |
|---|------|------|
| 001 | Webhook: ověření stavu přes status API, bez secretu v událostech, `ComgatePaymentAuthorized` | todo |
| 002 | Vynucené IPv4 a rozlišení síťových chyb (`isRetryable()`) | todo |
| 003 | Webhook pro více obchodů a volitelná kontrola IP | todo |
| 004 | Aserce a simulace webhooku v `Comgate::fake()` | todo |
| 005 | CI pro PHP 8.5 a vydání 1.4.0 | todo |

Pořadí: 001 → 002 → 003 → 004 → 005 (003 navazuje na 001).
