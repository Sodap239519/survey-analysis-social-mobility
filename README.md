# survey-analysis-social-mobility

Project for collecting household survey data and visualizing social mobility across five capitals:
- ทุนมนุษย์ (human)
- ทุนกายภาพ (physical)
- ทุนการเงิน (financial)
- ทุนธรรมชาติ (natural)
- ทุนทางสังคม (social)

Features:
- Admin form for new survey entries (Laravel + Vue)
- Import legacy data from Excel (.xlsx)
- Dashboard (Bento style) showing distribution by capital and poverty level
- Calculation:
  - Each capital normalized to 0–100
  - Aggregate score mapped to X in [1.0, 4.0] for poverty levels
  - Show mobility (improved / same / decreased) by comparing old vs new

Structure:
- /docs      -> store survey.docx (anonymized if PII)
- /data      -> store sample old_responses.xlsx (anonymized)
- /backend   -> Laravel app
- /frontend  -> Vue (Bento style) SPA

How to contribute:
1. Clone repo
2. Place sample files in /data and /docs (or upload via GitHub web)
3. Follow setup instructions in backend/README.md

Note about privacy: If your data contains PII, please anonymize before pushing to a public repo.
