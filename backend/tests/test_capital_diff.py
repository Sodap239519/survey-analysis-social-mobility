"""
tests/test_capital_diff.py
===========================
Unit tests for capital_diff.py (Python reference implementation).

Run with:
    python -m pytest backend/tests/test_capital_diff.py -v
or:
    python -m unittest backend/tests/test_capital_diff.py
"""

import sys
import os
import unittest

# Allow importing capital_diff from the backend directory
sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", ".."))
from backend.capital_diff import (
    xscale_to_normalized,
    normalized_to_xscale,
    poverty_level,
    extract_before_scores,
    extract_after_scores,
    build_capital_results,
    build_summary,
    compare_household,
    CapitalResult,
)


class TestXScaleConversion(unittest.TestCase):
    def test_min_x_gives_zero(self):
        self.assertAlmostEqual(0.0, xscale_to_normalized(1.0), places=3)

    def test_max_x_gives_100(self):
        self.assertAlmostEqual(100.0, xscale_to_normalized(4.0), places=3)

    def test_mid_x_gives_50(self):
        self.assertAlmostEqual(50.0, xscale_to_normalized(2.5), places=3)

    def test_clamps_below_min(self):
        self.assertAlmostEqual(0.0, xscale_to_normalized(0.0), places=3)

    def test_clamps_above_max(self):
        self.assertAlmostEqual(100.0, xscale_to_normalized(5.0), places=3)

    def test_roundtrip(self):
        for x in [1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0]:
            normalized = xscale_to_normalized(x)
            self.assertAlmostEqual(x, normalized_to_xscale(normalized), places=3)


class TestPovertyLevel(unittest.TestCase):
    def test_level_1(self):
        self.assertEqual(1, poverty_level(1.00))
        self.assertEqual(1, poverty_level(1.749))

    def test_level_2(self):
        self.assertEqual(2, poverty_level(1.75))
        self.assertEqual(2, poverty_level(2.499))

    def test_level_3(self):
        self.assertEqual(3, poverty_level(2.50))
        self.assertEqual(3, poverty_level(3.249))

    def test_level_4(self):
        self.assertEqual(4, poverty_level(3.25))
        self.assertEqual(4, poverty_level(4.00))


class TestExtractBeforeScores(unittest.TestCase):
    def _make_row(self, col_vals: dict, length: int = 100) -> list:
        row = [""] * length
        for col, val in col_vals.items():
            row[col] = str(val)
        return row

    def test_normalizes_x_to_0_100(self):
        row = self._make_row({44: 2.5, 55: 1.0, 69: 4.0, 78: 1.75, 87: 3.25})
        scores = extract_before_scores(row)
        self.assertAlmostEqual(50.0,  scores["human"],    places=2)
        self.assertAlmostEqual(0.0,   scores["physical"], places=2)
        self.assertAlmostEqual(100.0, scores["financial"],places=2)
        self.assertAlmostEqual(25.0,  scores["natural"],  places=2)
        self.assertAlmostEqual(75.0,  scores["social"],   places=2)

    def test_returns_none_for_empty_column(self):
        row = [""] * 100
        scores = extract_before_scores(row)
        for slug in ["human", "physical", "financial", "natural", "social"]:
            self.assertIsNone(scores[slug])

    def test_clamps_out_of_range_values(self):
        row = self._make_row({44: 5.0, 55: 0.0, 69: 2.5, 78: 2.5, 87: 2.5})
        scores = extract_before_scores(row)
        self.assertAlmostEqual(100.0, scores["human"],    places=2)
        self.assertAlmostEqual(0.0,   scores["physical"], places=2)

    def test_handles_short_row(self):
        row = []  # empty row
        scores = extract_before_scores(row)
        for v in scores.values():
            self.assertIsNone(v)


class TestExtractAfterScores(unittest.TestCase):
    def test_reads_score_fields(self):
        row = {
            "score_human":     "70.0",
            "score_physical":  "60.0",
            "score_financial": "80.0",
            "score_natural":   "55.0",
            "score_social":    "75.0",
        }
        scores = extract_after_scores(row)
        self.assertAlmostEqual(70.0, scores["human"],    places=3)
        self.assertAlmostEqual(60.0, scores["physical"], places=3)
        self.assertAlmostEqual(80.0, scores["financial"],places=3)
        self.assertAlmostEqual(55.0, scores["natural"],  places=3)
        self.assertAlmostEqual(75.0, scores["social"],   places=3)

    def test_returns_none_for_missing_fields(self):
        scores = extract_after_scores({})
        for v in scores.values():
            self.assertIsNone(v)


class TestBuildCapitalResults(unittest.TestCase):
    def test_diff_calculation(self):
        before = {"human": 50.0, "physical": 40.0, "financial": 30.0, "natural": 60.0, "social": 70.0}
        after  = {"human": 70.0, "physical": 60.0, "financial": 50.0, "natural": 80.0, "social": 90.0}
        results = build_capital_results(before, after)

        self.assertAlmostEqual(20.0, results["human"].diff,    places=3)
        self.assertAlmostEqual(20.0, results["physical"].diff, places=3)
        self.assertAlmostEqual(20.0, results["financial"].diff,places=3)

    def test_diff_is_none_when_before_is_none(self):
        before = {"human": None, "physical": 40.0, "financial": 30.0, "natural": 60.0, "social": 70.0}
        after  = {"human": 70.0, "physical": 60.0, "financial": 50.0, "natural": 80.0, "social": 90.0}
        results = build_capital_results(before, after)
        self.assertIsNone(results["human"].diff)
        self.assertIsNotNone(results["physical"].diff)

    def test_diff_is_none_when_after_is_none(self):
        before = {"human": 50.0, "physical": 40.0, "financial": 30.0, "natural": 60.0, "social": 70.0}
        after  = {"human": None, "physical": None, "financial": None, "natural": None, "social": None}
        results = build_capital_results(before, after)
        for cap in results.values():
            self.assertIsNone(cap.diff)

    def test_labels_are_thai(self):
        before = {slug: 50.0 for slug in ["human", "physical", "financial", "natural", "social"]}
        after  = {slug: 50.0 for slug in ["human", "physical", "financial", "natural", "social"]}
        results = build_capital_results(before, after)
        self.assertEqual("ทุนมนุษย์",    results["human"].label)
        self.assertEqual("ทุนกายภาพ",    results["physical"].label)
        self.assertEqual("ทุนการเงิน",    results["financial"].label)
        self.assertEqual("ทุนธรรมชาติ",   results["natural"].label)
        self.assertEqual("ทุนทางสังคม",   results["social"].label)


class TestBuildSummary(unittest.TestCase):
    def _make_capitals(self, before: float, after: float) -> dict:
        return {
            slug: CapitalResult(
                label=slug,
                before=before,
                after=after,
                diff=round(after - before, 4) if before is not None and after is not None else None,
            )
            for slug in ["human", "physical", "financial", "natural", "social"]
        }

    def test_all_50_before_all_70_after(self):
        caps = self._make_capitals(50.0, 70.0)
        s = build_summary(caps)
        self.assertAlmostEqual(50.0, s.avg_before, places=2)
        self.assertAlmostEqual(70.0, s.avg_after,  places=2)
        self.assertAlmostEqual(20.0, s.avg_diff,   places=2)
        # x_before = 1+(50/100)*3 = 2.5
        self.assertAlmostEqual(2.5, s.x_before, places=2)
        # x_after  = 1+(70/100)*3 = 3.1
        self.assertAlmostEqual(3.1, s.x_after,  places=2)
        self.assertEqual(3, s.poverty_level_before)  # 2.5 => level 3
        self.assertEqual(3, s.poverty_level_after)   # 3.1 => level 3
        self.assertEqual(0, s.poverty_level_diff)

    def test_level_improves_from_1_to_4(self):
        # before X=1.0 (level 1), after X=4.0 (level 4)
        caps = self._make_capitals(0.0, 100.0)
        s = build_summary(caps)
        self.assertEqual(1, s.poverty_level_before)
        self.assertEqual(4, s.poverty_level_after)
        self.assertEqual(3, s.poverty_level_diff)

    def test_null_after_propagates_to_summary(self):
        caps = {
            slug: CapitalResult(label=slug, before=50.0, after=None, diff=None)
            for slug in ["human", "physical", "financial", "natural", "social"]
        }
        s = build_summary(caps)
        self.assertEqual(50.0, s.avg_before)
        self.assertIsNone(s.avg_after)
        self.assertIsNone(s.avg_diff)
        self.assertIsNone(s.x_after)
        self.assertIsNone(s.poverty_level_after)
        self.assertIsNone(s.poverty_level_diff)


class TestCompareHousehold(unittest.TestCase):
    def _make_before_row(self, x: float = 2.5) -> list:
        row = [""] * 100
        row[0] = "12345678901"
        for col in [44, 55, 69, 78, 87]:
            row[col] = str(x)
        return row

    def _make_after_row(self, score: float = 70.0) -> dict:
        return {
            "house_code":      "12345678901",
            "score_human":     str(score),
            "score_physical":  str(score),
            "score_financial": str(score),
            "score_natural":   str(score),
            "score_social":    str(score),
        }

    def test_full_comparison(self):
        result = compare_household(
            house_code="12345678901",
            before_row=self._make_before_row(2.5),   # X=2.5 → 50.0
            after_row=self._make_after_row(70.0),
        )
        self.assertEqual("12345678901", result.house_code)
        self.assertEqual("legacy_import", result.before_source)
        self.assertTrue(result.after_found)
        self.assertAlmostEqual(50.0, result.capitals["human"].before, places=2)
        self.assertAlmostEqual(70.0, result.capitals["human"].after,  places=2)
        self.assertAlmostEqual(20.0, result.capitals["human"].diff,   places=2)
        self.assertAlmostEqual(50.0, result.summary.avg_before, places=2)
        self.assertAlmostEqual(70.0, result.summary.avg_after,  places=2)

    def test_no_after_survey(self):
        result = compare_household(
            house_code="12345678901",
            before_row=self._make_before_row(2.5),
            after_row=None,
        )
        self.assertFalse(result.after_found)
        for cap in result.capitals.values():
            self.assertIsNone(cap.after)
            self.assertIsNone(cap.diff)
        self.assertIsNone(result.summary.avg_after)

    def test_no_before_data(self):
        result = compare_household(
            house_code="99999999999",
            before_row=None,
            after_row=self._make_after_row(60.0),
        )
        self.assertEqual("not_found", result.before_source)
        self.assertTrue(result.after_found)
        for cap in result.capitals.values():
            self.assertIsNone(cap.before)
            self.assertIsNone(cap.diff)


if __name__ == "__main__":
    unittest.main()
