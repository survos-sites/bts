# Below the Surface

A Symfony application for exploring archaeological finds from Amsterdam's
North/South metro line. `Amst` is the catalog entity; its generated repository is
used by API Platform, SearchBundle and the administrative CRUD screens.

## Setup

Requires PHP 8.5 with SQLite/PostgreSQL PDO drivers, Composer and Symfony CLI.
Meilisearch is
optional for the Doctrine-backed catalog search; it is needed for Meili indexing.

```bash
composer install
# Create .env.local with APP_SECRET and DATABASE_URL.
# For an existing local SQLite archive:
# DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
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
by Git. The checked-out local archive currently contains 27,500 finds; that is
not a claim that the full upstream dataset has been imported.

```bash
castor app:download
castor app:dictionary
php bin/console import:convert data/amst.csv --dataset=amst --no-debug
php bin/console import:entities Amst data/amst.jsonl --limit=50
```

Use `--help` before a full load; import options belong to the installed ImportBundle.
The older `castor load` / `castor app:build` helpers also update Meili settings and
run translation, so they require those services. They are not just database loads.
Both upstream CSVs were refetched on 2026-11-01 and matched the local files
byte-for-byte (139,189 rows each). The `website` flag identifies published finds:
the conversion listener generates source citations and image URLs only when it
is set. Unpublished finds stay in the catalog with null citation/image fields.
The source CSV has no citation URL column; those URLs are generated locally.

Do not regenerate `Amst` as part of an ordinary import: it contains translation
property hooks and workflow integration.

The current catalog search uses `doctrine://default` and needs no external search
server or indexing step. Meili metadata and tooling remain installed for optional
index experiments; `MEILI_SERVER`, `MEILI_API_KEY`, `MEILI_SEARCH_KEY` and
`MEILI_PREFIX` configure that separate backend.

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

DuckDB catalog experiments will follow this baseline release. The intended model
is writable local builds and published read-only S3 snapshots, without changing
`Amst` or its existing repository.
