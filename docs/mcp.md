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
| `create-company` | role v CRM | založí firmu včetně kontaktních osob |
| `list-calculations` | role v CRM | seznam kalkulací |
| `get-calculation` | role v CRM | detail kalkulace včetně položek |
| `create-calculation` | role v CRM | vytvoří kalkulaci a vrátí veřejnou URL pro zákazníka |
| `update-calculation` | role v CRM | upraví kalkulaci; s `items` nahradí všechny položky |
| `list-projects` | role v CRM | seznam projektů (filtr podle názvu, stavu, firmy) |
| `get-project` | role v CRM | detail projektu včetně seznamů úkolů a úkolů |
| `create-project` | role v CRM | založí projekt |
| `update-project` | role v CRM | upraví projekt; mění jen vyplněná pole |
| `create-todolist` | role v CRM | založí v projektu seznam úkolů, volitelně rovnou s úkoly |
| `create-todolist-from-calculation` | role v CRM | převede položky kalkulace na úkoly v projektu |
| `update-todo` | role v CRM | dokončení, přiřazení řešitele, termín nebo název úkolu |

Server i nástroje jsou v [app/Mcp/](../app/Mcp/). Ukládání kalkulace sdílí web i MCP
přes [SaveCalculation](../app/Actions/SaveCalculation.php), takže se logika nemůže rozejít.
Stejně tak převod kalkulace na úkoly sdílí web i MCP přes
[CreateTodolistFromCalculation](../app/Actions/CreateTodolistFromCalculation.php).

### Zanoření

Položky kalkulace i úkoly se zanořují stejným způsobem: každé položce dáš `key`
a podřízené nastavíš `parent_key` na klíč rodiče. Při převodu kalkulace na úkoly se
zanoření zachová automaticky — vybereš-li podpoložku bez rodiče, nadřazené položky se
do seznamu doplní, aby úkol nezůstal osiřelý.

## Nasazení na produkci

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate          # tabulky oauth_* + passport_keys
php artisan config:cache && php artisan route:cache
```

### OAuth klíče

Passport klíče **negenerujeme na disk ani do env** – vytváří je admin v administraci
(**Nastavení → MCP / API klíče**, tlačítko „Vygenerovat klíče“) a ukládají se do tabulky
`passport_keys` v databázi (privátní klíč zašifrovaný přes `APP_KEY`). Díky tomu přežijí
redeploy i smazání volume. Za běhu se načtou do configu těsně před sestavením OAuth serveru
(`App\Providers\AppServiceProvider::loadPassportKeysFromDatabase`), takže mají přednost před
`config/passport.php`.

Dokud admin klíče nevygeneruje, vrací `/mcp` chybu a žádný klient se nepřipojí – to je první
krok po nasazení. Přegenerování klíčů zneplatní vydané tokeny (klienti se přihlásí znovu).

Generování / stav řeší [PassportKeyController](../app/Http/Controllers/PassportKeyController.php)
a [GeneratePassportKeys](../app/Actions/GeneratePassportKeys.php).

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
