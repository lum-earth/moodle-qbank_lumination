# Lumination AI Question Generator for Moodle (qbank_lumination)

Rules for coding agents in this repository. Keep this file short and true; update a fact in the same change that makes it untrue.

## What this is

A Moodle 5.0+ question bank plugin. Its "Generate with AI" tab sends a document to the AI Tutor API (`POST /quiz`, then poll), turns the result into Moodle XML (`classes/quiz_xml.php`) and imports it with Moodle's own XML importer into a new category (`classes/importer.php`), in an adhoc task (`classes/task/generate_questions.php`). Pages: `generate.php` (form), `status.php` (progress).

It requires `lum-earth/moodle-aiprovider_lumination` (the hub): the API key and client come from `\aiprovider_lumination\api_client::for_site()`. CI installs it with `moodle-plugin-ci add-plugin`.

- The real `/quiz` result puts options, `correct_answer` (a letter), `explanation` and `hint` under `question_json`, and labels options "A) ...". `tests/fixtures/quiz_result.json` has that shape; keep it in step with the API.

## Checks

CI (`.github/workflows/ci.yml`, moodle-plugin-ci) must pass on every supported branch: lint, code checker and PHPDoc with zero warnings, validate, PHPUnit. Local dev: `docker-compose.yml` in the parent workspace runs Moodle 5.2 on port 8081 with this folder mounted at `public/question/bank/lumination`.

## Moodle coding rules that CI enforces

- `defined('MOODLE_INTERNAL') || die();` only in files with side effects (`version.php`, `db/*.php`, form files that `require_once` formslib). Never in class files, tests or pages that load `config.php`.
- Multi-line `if` conditions: first expression on the line after `(`, closing `)` on its own line. Multi-line calls: one argument per line.
- Test classes are `final`. `else if`, not `elseif`. Inline comments end with `.`, `!` or `?`. Lines up to 132 characters (lang files excepted).
- No empty `catch` blocks; no `debugging()` in code that tests run.
- Lang files are plain sorted `$string[...] = '...';` lines. Keep `lang/es` in step with `lang/en`.

## Boundaries

- Never push, move or delete a Git tag, and never publish a release; the maintainer does that.
- Ask before calls to the real AI Tutor API (they spend credits) and before any other networked side effect.
- Never commit a key, a password or a personal path.
- Listing names and descriptions follow the Marketplace guide in the maintainer's workspace (`docs/marketplace-listing-guide.md`).
