#!/usr/bin/env python3
"""
Corrige les INSERT du fichier SQL pour matcher le schéma CREATE TABLE avant import.
Corrections:
1. school: supprime trial_start_at et trial_duration des colonnes et valeurs
2. module: supprime la colonne 'id' (module utilise 'name' comme PK)
3. school_evaluation: renomme created_at -> creted_at (typo Doctrine)
"""

import re
import os

INPUT_FILE = "data/innovsh (11).sql"
OUTPUT_FILE = "data/innovsh (11)_fixed.sql"


def fix_school_insert(content):
    """Fix the school INSERT: remove trial_start_at and trial_duration columns
    and their corresponding values."""
    # Remove columns from the INSERT header
    content = content.replace(
        "`trial_start_at`, `trial_duration`, ",
        ""
    )
    # Fix the VALUES: the school row has logo='69248874dc4ea.png',
    # followed by trial_start_at='2025-05-25', trial_duration=30
    # Replace: 'logo.png', 'date', 30, -> 'logo.png',
    content = content.replace(
        "'69248874dc4ea.png', '2025-05-25', 30, '2025-05-25 20:12:50'",
        "'69248874dc4ea.png', '2025-05-25 20:12:50'"
    )
    return content


def fix_module_insert(content):
    """Fix module INSERT: remove 'id' column and its value for each row."""
    content = content.replace(
        "INSERT INTO `module` (`id`, `name`, `enabled`) VALUES",
        "INSERT INTO `module` (`name`, `enabled`) VALUES"
    )
    # Fix the 3 rows
    content = content.replace("(1, 'timetable', 1)", "('timetable', 1)")
    content = content.replace("(2, 'registration_card', 1)", "('registration_card', 1)")
    content = content.replace("(3, 'admission', 1)", "('admission', 1)")
    return content


def fix_school_evaluation_insert(content):
    """Fix school_evaluation INSERT: rename created_at -> creted_at."""
    content = content.replace(
        "INSERT INTO `school_evaluation` (`id`, `frame_id`, `time_id`, `period_id`, `created_at`) VALUES",
        "INSERT INTO `school_evaluation` (`id`, `frame_id`, `time_id`, `period_id`, `creted_at`) VALUES"
    )
    return content


def main():
    print(f"Reading: {INPUT_FILE}")
    with open(INPUT_FILE, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()

    print("Applying fixes...")
    content = fix_school_insert(content)
    print("  [OK] school: removed trial_start_at, trial_duration")

    content = fix_module_insert(content)
    print("  [OK] module: removed id column")

    content = fix_school_evaluation_insert(content)
    print("  [OK] school_evaluation: renamed created_at -> creted_at")

    print(f"Writing: {OUTPUT_FILE}")
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write(content)

    size_mb = os.path.getsize(OUTPUT_FILE) / (1024 * 1024)
    print(f"Done! Output: {OUTPUT_FILE} ({size_mb:.2f} MB)")


if __name__ == '__main__':
    main()
