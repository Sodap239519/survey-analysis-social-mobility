"""
capital_diff.py
===============
Python reference implementation for Before–After capital score comparison.

This script mirrors the logic in App\\Services\\CompareHouseholdSurveyLogic (PHP/Laravel)
and can be used for:
  - Offline batch analysis of the legacy CSV + after-survey export
  - Validating the PHP service output
  - Unit testing the core diff logic independently of the database

Usage (standalone):
    python capital_diff.py --before data/before.csv --after data/after.csv --out diff_output.json

Usage (as a library):
    from capital_diff import compare_household, xscale_to_normalized

Column mapping (legacy CSV → capital slug):
    Index 44  → 'human'     (ทุนมนุษย์)
    Index 55  → 'physical'  (ทุนกายภาพ)
    Index 69  → 'financial' (ทุนการเงิน)   # PENDING MAPPING MANUAL if CSV layout changes
    Index 78  → 'natural'   (ทุนธรรมชาติ)
    Index 87  → 'social'    (ทุนทางสังคม)

Before scores in the CSV are on the X scale [1.0, 4.0].
After scores in SurveyResponse are already normalized to [0, 100].
Conversion: normalized_0_100 = (x - 1.0) / 3.0 * 100

X index formula: X = 1.0 + (avg_normalized / 100.0) * 3.0
Poverty levels:
    Level 1: 1.00 ≤ X < 1.75
    Level 2: 1.75 ≤ X < 2.50
    Level 3: 2.50 ≤ X < 3.25
    Level 4: 3.25 ≤ X ≤ 4.00
"""

from __future__ import annotations

import argparse
import csv
import json
import sys
from dataclasses import dataclass, field, asdict
from typing import Dict, List, Optional, Tuple

# ─── Capital metadata ─────────────────────────────────────────────────────────

CAPITALS: Dict[str, Dict] = {
    "human":     {"label": "ทุนมนุษย์",    "raw_data_col": 44},
    "physical":  {"label": "ทุนกายภาพ",    "raw_data_col": 55},
    "financial": {"label": "ทุนการเงิน",    "raw_data_col": 69},  # PENDING MAPPING MANUAL
    "natural":   {"label": "ทุนธรรมชาติ",   "raw_data_col": 78},
    "social":    {"label": "ทุนทางสังคม",   "raw_data_col": 87},
}

HOUSE_CODE_COL = 0  # รหัสบ้าน (11-digit household code)

# Column names in after-survey export that hold the 0–100 score per capital.
# These match the score_* columns in the survey_responses table.
AFTER_SCORE_COLS: Dict[str, str] = {
    "human":     "score_human",
    "physical":  "score_physical",
    "financial": "score_financial",
    "natural":   "score_natural",
    "social":    "score_social",
}


# ─── Data classes ─────────────────────────────────────────────────────────────

@dataclass
class CapitalResult:
    label: str
    before: Optional[float]
    after: Optional[float]
    diff: Optional[float]


@dataclass
class ComparisonSummary:
    avg_before: Optional[float]
    avg_after: Optional[float]
    avg_diff: Optional[float]
    x_before: Optional[float]
    x_after: Optional[float]
    x_diff: Optional[float]
    poverty_level_before: Optional[int]
    poverty_level_after: Optional[int]
    poverty_level_diff: Optional[int]


@dataclass
class HouseholdComparison:
    house_code: str
    before_source: str          # 'legacy_import' or 'survey_response'
    after_found: bool
    capitals: Dict[str, CapitalResult] = field(default_factory=dict)
    summary: Optional[ComparisonSummary] = None


# ─── Core functions ────────────────────────────────────────────────────────────

def xscale_to_normalized(x: float) -> float:
    """Convert X scale [1.0, 4.0] to normalized [0.0, 100.0]."""
    x_clamped = max(1.0, min(4.0, x))
    return round((x_clamped - 1.0) / 3.0 * 100.0, 4)


def normalized_to_xscale(n: float) -> float:
    """Convert normalized [0.0, 100.0] to X scale [1.0, 4.0]."""
    return round(1.0 + (n / 100.0) * 3.0, 4)


def poverty_level(x: float) -> int:
    """Map aggregate X score (1.0–4.0) to poverty level (1–4)."""
    if x < 1.75:
        return 1
    if x < 2.50:
        return 2
    if x < 3.25:
        return 3
    return 4


def extract_before_scores(row: List[str]) -> Dict[str, Optional[float]]:
    """
    Extract capital scores from a legacy CSV row (X scale → 0–100 normalized).

    Parameters
    ----------
    row : list of str
        A single CSV data row (0-indexed).

    Returns
    -------
    dict mapping slug → normalized score (float) or None if missing/invalid.
    """
    scores: Dict[str, Optional[float]] = {}
    for slug, meta in CAPITALS.items():
        col = meta["raw_data_col"]
        try:
            value_str = row[col] if col < len(row) else ""
            if not value_str.strip():
                # PENDING MAPPING MANUAL: column may be absent in this CSV layout
                scores[slug] = None
                continue
            x = float(value_str)
            scores[slug] = xscale_to_normalized(x)
        except (ValueError, IndexError):
            # PENDING MAPPING MANUAL: could not parse value at this column
            scores[slug] = None
    return scores


def extract_after_scores(row: Dict[str, str]) -> Dict[str, Optional[float]]:
    """
    Extract capital scores from an after-survey export row (already 0–100 normalized).

    Parameters
    ----------
    row : dict
        A CSV row read with DictReader (column header → value).

    Returns
    -------
    dict mapping slug → normalized score (float) or None if missing/invalid.
    """
    scores: Dict[str, Optional[float]] = {}
    for slug, col_name in AFTER_SCORE_COLS.items():
        value_str = row.get(col_name, "").strip()
        if not value_str:
            scores[slug] = None
            continue
        try:
            scores[slug] = round(float(value_str), 4)
        except ValueError:
            # PENDING MAPPING MANUAL: unexpected value format
            scores[slug] = None
    return scores


def build_capital_results(
    before_scores: Dict[str, Optional[float]],
    after_scores: Dict[str, Optional[float]],
) -> Dict[str, CapitalResult]:
    """Build per-capital comparison dict from before and after score maps."""
    results: Dict[str, CapitalResult] = {}
    for slug, meta in CAPITALS.items():
        before = before_scores.get(slug)
        after = after_scores.get(slug)
        diff = round(after - before, 4) if before is not None and after is not None else None
        results[slug] = CapitalResult(label=meta["label"], before=before, after=after, diff=diff)
    return results


def build_summary(capitals: Dict[str, CapitalResult]) -> ComparisonSummary:
    """Compute aggregate summary statistics from per-capital results."""
    before_vals = [c.before for c in capitals.values() if c.before is not None]
    after_vals  = [c.after  for c in capitals.values() if c.after  is not None]

    avg_before = round(sum(before_vals) / len(before_vals), 4) if before_vals else None
    avg_after  = round(sum(after_vals)  / len(after_vals),  4) if after_vals  else None
    avg_diff   = round(avg_after - avg_before, 4) if avg_before is not None and avg_after is not None else None

    x_before = normalized_to_xscale(avg_before) if avg_before is not None else None
    x_after  = normalized_to_xscale(avg_after)  if avg_after  is not None else None
    x_diff   = round(x_after - x_before, 4) if x_before is not None and x_after is not None else None

    level_before = poverty_level(x_before) if x_before is not None else None
    level_after  = poverty_level(x_after)  if x_after  is not None else None
    level_diff   = (level_after - level_before) if level_before is not None and level_after is not None else None

    return ComparisonSummary(
        avg_before=avg_before, avg_after=avg_after, avg_diff=avg_diff,
        x_before=x_before, x_after=x_after, x_diff=x_diff,
        poverty_level_before=level_before,
        poverty_level_after=level_after,
        poverty_level_diff=level_diff,
    )


def compare_household(
    house_code: str,
    before_row: Optional[List[str]],
    after_row: Optional[Dict[str, str]],
) -> HouseholdComparison:
    """
    Run Before–After comparison for a single household.

    Parameters
    ----------
    house_code : str
        11-digit household code (รหัสบ้าน).
    before_row : list of str or None
        Legacy CSV data row. None if household not found in legacy data.
    after_row : dict or None
        After-survey CSV row (DictReader format). None if no after survey found.

    Returns
    -------
    HouseholdComparison with per-capital and summary results.
    """
    if before_row is not None:
        before_scores = extract_before_scores(before_row)
        before_source = "legacy_import"
    else:
        before_scores = {slug: None for slug in CAPITALS}
        before_source = "not_found"

    if after_row is not None:
        after_scores = extract_after_scores(after_row)
        after_found = True
    else:
        after_scores = {slug: None for slug in CAPITALS}
        after_found = False

    capitals = build_capital_results(before_scores, after_scores)
    summary  = build_summary(capitals)

    return HouseholdComparison(
        house_code=house_code,
        before_source=before_source,
        after_found=after_found,
        capitals=capitals,
        summary=summary,
    )


def load_before_csv(path: str) -> Dict[str, List[str]]:
    """Load legacy before CSV into a dict keyed by house_code (col 0)."""
    households: Dict[str, List[str]] = {}
    with open(path, encoding="utf-8-sig") as fh:
        reader = csv.reader(fh)
        next(reader, None)  # skip header
        for row in reader:
            if row and row[HOUSE_CODE_COL].strip():
                households[row[HOUSE_CODE_COL].strip()] = row
    return households


def load_after_csv(path: str) -> Dict[str, Dict[str, str]]:
    """Load after-survey CSV (with header row) into a dict keyed by house_code."""
    households: Dict[str, Dict[str, str]] = {}
    with open(path, encoding="utf-8-sig") as fh:
        reader = csv.DictReader(fh)
        for row in reader:
            # PENDING MAPPING MANUAL: after-survey export may use 'house_code' or 'รหัสบ้าน'
            code = (row.get("house_code") or row.get("รหัสบ้าน") or "").strip()
            if code:
                households[code] = dict(row)
    return households


def run_batch(
    before_path: str,
    after_path: str,
    output_path: Optional[str] = None,
) -> List[dict]:
    """
    Batch-compare all households found in either CSV file.

    Returns a list of comparison dicts (JSON-serializable).
    """
    before_data = load_before_csv(before_path)
    after_data  = load_after_csv(after_path)

    all_codes = sorted(set(before_data) | set(after_data))
    results = []

    for code in all_codes:
        comparison = compare_household(
            house_code=code,
            before_row=before_data.get(code),
            after_row=after_data.get(code),
        )
        # Convert dataclasses to plain dict for JSON serialisation
        d = asdict(comparison)
        results.append(d)

    if output_path:
        with open(output_path, "w", encoding="utf-8") as fh:
            json.dump(results, fh, ensure_ascii=False, indent=2)
        print(f"Wrote {len(results)} records to {output_path}", file=sys.stderr)

    return results


# ─── CLI entry-point ──────────────────────────────────────────────────────────

def main() -> None:
    parser = argparse.ArgumentParser(
        description="Compare Before (legacy CSV) vs After (survey export) capital scores."
    )
    parser.add_argument("--before", required=True, help="Path to legacy before CSV file")
    parser.add_argument("--after",  required=True, help="Path to after-survey CSV export")
    parser.add_argument("--out",    default=None,  help="Output JSON file path (default: stdout)")
    args = parser.parse_args()

    results = run_batch(args.before, args.after, args.out)

    if not args.out:
        print(json.dumps(results, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
