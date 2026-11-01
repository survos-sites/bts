# Below the Surface

A Symfony application for exploring archaeological finds from Amsterdam's
North/South metro line. `Amst` is the catalog entity; its generated repository is
used by API Platform, SearchBundle and the administrative CRUD screens.

## Setup

Requires PHP 8.5, `ext-pdo_duckdb` with DuckDB 2.x, Composer and Symfony CLI.
PDO SQLite is used only when copying the original local archive into DuckDB.

```bash
composer install
# Create .env.local with APP_SECRET and DATABASE_URL.
# DATABASE_URL="duckdb://_/%kernel.project_dir%/var/duckdb/bts.duckdb"
php bin/console cache:clear
php bin/console assets:install public
php bin/console importmap:install
symfony server:start -d
```

During shared bundle development, link installed Survos packages to mono:

```bash
php ../mono/link .
```

The public homepage is `/{locale}/` (for example `/en/` or `/nl/`), using
TablerBundle. It explains the project, credits the source, and links to the
original CSV downloads, documentation, reuse terms, code and related work.
Navigation is built by `src/Menu/AppMenu.php` using Tabler's standard menu slots;
route labels are translated in `translations/routing.en.yaml` and `routing.nl.yaml`.
SearchBundle browsing is at `/{locale}/search`, with keyword search,
project/category facets, sorting and pagination. EasyAdmin remains at `/{locale}/admin` for edit
and workflow experiments. The public API is GET-only:

- `/api/amsts.jsonld` — paginated collection.
- `/api/amsts/{code}.jsonld` — one find, e.g. `NZC1-00001MTL001`.
- `/api/docs` — API Platform documentation.

## Data loading

`castor.php` describes the existing pipeline. Download the Dutch/English source
CSVs, align them into a translation dictionary, convert/profile the Dutch CSV,
then import its JSONL through the existing entity mapping. Data files are ignored
by Git. The original local archive contains 27,500 finds. The DuckDB catalog selects
its 4,424 published finds. The full upstream CSV has 19,977 published finds out
of 139,189 records; this local catalog is still a partial import.

```bash
castor app:download
castor app:dictionary
php bin/console import:convert data/amst.csv --dataset=amst --output=data/amst.jsonl --no-debug
php bin/console import:entities Amst data/amst.jsonl --limit=50
```

Use `--help` before a full load; import options belong to the installed ImportBundle.
`castor load` uses the local `DatasetSource` description and imports converted
JSONL through Doctrine. It no longer configures a Meili index. `castor app:build`
also attempts a schema update and runs translation; for DuckDB use the explicit
fresh-schema steps below rather than the older schema-update helper.
Both upstream CSVs were refetched on 2026-11-01 and matched the local files
byte-for-byte (139,189 rows each). The `website` flag identifies published finds:
the existing conversion callback skips records where it is zero by setting the
event row to null. Published records keep their source citations and image URLs.
The archive-copy tool applies the same selection; the original archive is intact.
The source CSV has no citation URL column; those URLs are generated locally.

Do not regenerate `Amst` as part of an ordinary import: it contains translation
property hooks and workflow integration.

The current catalog search uses `doctrine://default` against the local DuckDB
connection and needs no external search server or indexing step. Meili, its PHP client, old dashboard and indexing hooks have been removed.
Ordinary EasyAdmin remains for local edit testing. Result cards link to the
original source when a citation exists. The `{}` button opens an andypf JSON
viewer with null/empty values removed recursively, while preserving zero and
false values.

## Translations

Source text is Dutch. Babel stores backing text on `Amst`, and translations in
`str` / `str_tr`. `data/amst_dictionary.json` aligns Dutch source strings with
English text from the upstream CSV. The application's translation service listens
for translation events and maintains locale JSONL dictionaries.

```bash
php bin/console babel:stats
php bin/console babel:translate en --dry-run
php bin/console babel:translate en
```

The existing `TranslationService` answers English translation events from the
supplied dictionary, with two upstream omissions corrected in
`resources/translation-overrides.en.json`. `babel:translate en` fills blank
`StrTranslation` entities; it does not overwrite Dutch backing text or existing
translations. All 2,189 source strings in the current local archive have English
translations after this command. Repeating it is a no-op.

Babel's redundant provider fallback is disabled in this app: the listener owns
provider selection. English dictionary misses are reported and left untranslated;
other locales may use the listener's configured external provider. Viewing the
catalog never requests new translations. Inspect configuration before running
large translation jobs for other languages.

## Workflow

The native Symfony workflow is `AmstWorkflow`, declared by attributes in
`src/Workflow/AmstWFDefinition.php`. Its initial place is `new`; transition `do`
moves a find to `done`, stored in `Amst::marking`. `AmstWorkflow.php` provides the
guard/transition hooks. The current transition hook has no additional action.
Persisting the marking requires flushing the entity manager.

```bash
php bin/console workflow:dump AmstWorkflow
php vendor/bin/phpunit tests/NativeWorkflowTest.php
```

## Validation

```bash
php vendor/bin/phpunit
php bin/console lint:container
php bin/console lint:twig templates
php bin/console lint:yaml config
```

The November 2026 refresh updates Composer dependencies, adds TablerBundle and
SearchBundle, replaces the empty EasyAdmin homepage, and retains `Amst` and its
repository. The SQLite baseline was checked first: 27,500 finds, 526 keyword
matches for `munt`, collection/item API responses, and the native workflow.

## Local DuckDB experiment

The `1.0` tag preserves the SQLite baseline. Branch `codex/duckdb-catalog` keeps
`Amst`, its repository and the existing single Doctrine entity manager, and
switches `DATABASE_URL` to a writable local DuckDB file. SearchBundle continues
using its Doctrine adapter; Elasticsearch and Meilisearch are not used for
catalog queries. Meili dependencies and integration have been removed in this branch.
Vector search is a later stage of the DuckDB experiment.

Install `ext-pdo_duckdb` with DuckDB 2.x before `composer install`; Composer does
not install the native extension. The DBAL driver and DuckDB bundle are Composer
dependencies. During development, link mono to include BabelBundle's DuckDB
upsert support and the fix preventing blank provider stubs from hiding supplied
English translations.

To build a fresh local database from an existing archive:

```bash
mkdir -p var/duckdb
DATABASE_URL='duckdb://_/%kernel.project_dir%/var/duckdb/bts.duckdb' php bin/console doctrine:schema:create
DATABASE_URL='duckdb://_/%kernel.project_dir%/var/duckdb/bts.duckdb' php bin/duckdb-copy.php "$PWD/var/data.db"
```

Use a new target filename when rebuilding. The copy tool requires empty target
tables, reads the archive in a read-only transaction, verifies row counts and
advances sequences beyond imported IDs. New FetchBundle tables start empty;
queued Messenger jobs are not duplicated. It checkpoints the finished DuckDB
file. The application then uses DuckDB directly, including Babel's `str` and
`str_tr` tables; SQLite is only the source archive for this conversion.

```dotenv
DATABASE_URL="duckdb://_/%kernel.project_dir%/var/duckdb/bts.duckdb"
```

A local `.env.local` override takes precedence over `.env`; update both when
switching an existing checkout. The original archive remains in `var/data.db`.

`src/Doctrine` contains the compatibility proven in the Symfony demo: Doctrine
JSON uses VARCHAR to avoid double decoding PDO's native JSON values, generated
IDs use sequences, and schema creation orders tables with inline foreign keys.
Sequence IDs also have server defaults for Babel's direct DBAL inserts. DuckDB
does not implement SQL cascade actions; ORM mappings stay intact, while the
schema listener removes unsupported SQL actions. Raw SQL deletes remain
restricted by foreign keys. General schema update/migration parity is not yet
claimed; the repeatable path here is a fresh schema and archive copy.

```bash
php tests/Integration/duckdb-read-parity.php \
  'sqlite:///%kernel.project_dir%/var/data.db' \
  'duckdb://_/%kernel.project_dir%/var/duckdb/bts.duckdb'
php tests/Integration/duckdb-edit-check.php
```

The read check compares API payloads, search cards, pagination and both locales
against the published subset of the original SQLite archive.
The edit check inserts, updates and deletes a temporary real Amst entity and
verifies that English hydration survives each flush; it cleans up its record.
Babel upserts may add missing provider stubs for the existing source strings.

For deployment, a pinned FrankenPHP Docker image is the preferred packaging for
PHP 8.5, `pdo_duckdb` and DuckDB extensions. PHP-FPM can also load the driver;
FrankenPHP is a packaging choice, not an engine requirement. S3 publication and
read-only runtime checks remain a separate next step; no fsn1 app is provisioned
by this local experiment. x10's S3 endpoint is SeaweedFS on port 8333. WD-backed
RustFS on port 9000 is never used.
