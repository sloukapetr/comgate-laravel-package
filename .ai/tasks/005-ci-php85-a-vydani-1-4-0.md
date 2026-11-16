# 005 – CI pro PHP 8.5 a vydání 1.4.0

## Cíl
CI testuje i kombinaci, na které běží produkční aplikace (PHP 8.5 + Laravel 13), a úkoly 001–004 vyjdou jako 1.4.0.

## Kontext
- `.github/workflows/ci.yml` dnes testuje PHP 8.2/L11, 8.2/L12 a 8.3/L13.
- Aplikace it-elektro běží na PHP 8.5 a Laravelu 13.

## Kroky
1. Do matice přidat `php: '8.5'`, `laravel: '13'`, `testbench: '^11.0'` (a případně 8.4/L13). Když 8.5 v `shivammathur/setup-php` ještě není, dát `continue-on-error` jen pro tento řádek a napsat to do odpovědi.
2. `.gitattributes`: přidat `/.ai export-ignore`, `/.cursor export-ignore`, `/AGENTS.md export-ignore`, `/RELEASE_* export-ignore`.
3. Projít `RELEASE_CHECKLIST.md`.
4. `CHANGELOG.md`: Unreleased → `[1.4.0] - <datum>`. Upgrade Notes: `webhook.verify_status` (výchozí zapnuté), nové volby `force_ipv4`, `merchants`, `webhook.verify_ip`, deprecated `ComgateWebhookNotification::$secret`.
5. `RELEASE_NOTES_v1.4.0.md` ve stejném stylu jako v1.3.0.
6. README: sekce Configuration doplnit o všechny nové config klíče a env proměnné.

## Hotovo když
- `composer validate --strict` a `composer test` projdou lokálně.
- README odpovídá `config/comgate.php` (každý klíč je zdokumentovaný).

## Ruční kroky pro Petra
- Commit, `git tag v1.4.0`, `git push --tags`, release na GitHubu, případně refresh na Packagistu.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
