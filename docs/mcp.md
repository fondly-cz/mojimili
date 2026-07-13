# MCP server (Claude Desktop → CRM)

CRM vystavuje MCP server na `POST /mcp`, chráněný OAuth 2.1 (Laravel Passport).
Claude se tak připojí pod tvým vlastním CRM účtem a má přesně stejná práva jako v UI.

## Nástroje

| Nástroj | Kdo smí | Co dělá |
| --- | --- | --- |
| `list-services` | role v CRM | katalog služeb včetně ID, ceny a periody platby |
| `create-service` | admin | přidá službu do katalogu |
| `update-service` | admin | upraví existující službu |
| `list-companies` | role v CRM | firmy a jejich kontaktní osoby (pro `company_id`) |
| `list-calculations` | role v CRM | seznam kalkulací |
| `get-calculation` | role v CRM | detail kalkulace včetně položek |
| `create-calculation` | role v CRM | vytvoří kalkulaci a vrátí veřejnou URL pro zákazníka |

Server i nástroje jsou v [app/Mcp/](../app/Mcp/). Ukládání kalkulace sdílí web i MCP
přes [SaveCalculation](../app/Actions/SaveCalculation.php), takže se logika nemůže rozejít.

## Nasazení na produkci

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate          # tabulky oauth_*
php artisan passport:keys    # jen při prvním nasazení, klíče nejsou v gitu
php artisan config:cache && php artisan route:cache
```

Klíče `storage/oauth-*.key` jsou v `.gitignore`. Musí přežít deploy (persistentní volume),
jinak se po každém nasazení odpojí všichni klienti. Alternativně je vlož do env jako
`PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` (viz `config/passport.php`).

`APP_URL` musí být produkční HTTPS doména — používá se jako OAuth issuer i pro veřejné
odkazy na kalkulace. Bez HTTPS se Claude nepřipojí.

## Připojení z Claude Desktop

1. Settings → Connectors → **Add custom connector**
2. URL: `https://<produkcni-domena>/mcp`
3. Claude se sám zaregistruje (`POST /oauth/register`), přesměruje tě na přihlášení do CRM
   a zobrazí schvalovací obrazovku (`resources/views/mcp/authorize.blade.php`).
4. Po schválení jsou nástroje dostupné v konverzaci.

Access token platí 30 dní, refresh token 60 dní (nastaveno v `AppServiceProvider`).

## Ladění

```bash
php artisan mcp:inspector mcp   # MCP Inspector proti lokálnímu serveru
php artisan test tests/Feature/Mcp
```
