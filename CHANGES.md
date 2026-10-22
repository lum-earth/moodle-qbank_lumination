# Changelog

## v0.1.0 (2026-10-22)

- First release. A Generate with AI tab in the question bank writes multiple-choice questions from a PDF, DOCX or TXT document.
- Questions are imported with Moodle's own XML importer into a new category, with an explanation and a hint, in the order the API wrote them (labelled A, B, C) so the explanations match.
- Runs in the background with a progress page; the document is deleted after the job.
- Uses the Lumination AI Provider's API key.
