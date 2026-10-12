#!/usr/bin/env python3
"""
Filtre le dump SQL innovsh (11).sql pour :
1. Remplacer le schéma par celui aligné avec les entités Doctrine (innovsh (9).sql)
2. Ne garder que les données de l'école "GROUPE SCOLAIRE BILINGUE MERCURE" (id=2)

Usage: python data/filter_school.py
Output: data/innovsh (11).sql (modifié en place)
"""

import re
import sys
import os
from collections import defaultdict

SCHOOL_ID = 2
SCHEMA_FILE = "data/innovsh (9).sql"
DATA_FILE = "data/innovsh (11).sql"
OUTPUT_FILE = "data/innovsh (11).sql"
TEMP_OUTPUT = "data/innovsh_filtered_temp.sql"

# ── Tables avec school_id direct → filtrer WHERE school_id = SCHOOL_ID ──
DIRECT_SCHOOL_TABLES = {
    'admission_reductions': 'school_id',
    'app_license': 'school_id',
    'class_subject_module': 'school_id',
    'migration_log': 'school_id',
    'school': 'id',  # cas spécial: filtrer sur id
    'school_class_admission_payments': 'school_id',
    'school_class_numbering_type': 'school_id',
    'school_class_payment_modals': 'school_id',
    'school_class_period': 'school_id',
    'school_section': 'school_id',
    'school_study_type': 'school_id',
    'subject_group': 'school_id',
    'timetable': 'school_id',
    'user': 'school_id',
    'user_base_configuration': 'school_id',
}

# ── Tables globales (référentiel) → garder TOUTES les lignes ──
GLOBAL_TABLES = {
    'classe',
    'class_occurence',
    'core_update',
    'doctrine_migration_versions',
    'evaluation_appreciation_bareme',
    'evaluation_appreciation_template',
    'fees',
    'messenger_messages',
    'module',
    'registration_card_base_config',
    'report_card_template',
    'school_evaluation',
    'school_evaluation_frame',
    'school_evaluation_time',
    'school_evaluation_time_type',
    'school_period',
    'section_category_subject',
    'studies_type',
    'study_level',
    'study_subject',
    'subjects_modules',
    'school_license_payment',
}

# ── Tables à exclure (existent dans (11) mais pas dans le schéma du projet) ──
EXCLUDED_TABLES = {
    'school_class_grade',
    'school_class_subject_group',
    'section_category_subject_group',
    'student_note',
    'subject_grade',
    'teacher_class',
}

# ── Parsing ──────────────────────────────────────────────────────────────

def parse_value_rows(values_str):
    """
    Parse the VALUES (...) part of an INSERT.
    Returns list of lists of strings (each inner list = one row's values).
    Handles: NULL, numbers, 'strings' (with escaped quotes '' and backslash escapes),
    JSON arrays/objects, nested parens in JSON.
    """
    rows = []
    i = 0
    n = len(values_str)

    while i < n:
        # Skip whitespace
        while i < n and values_str[i] in (' ', '\t', '\n', '\r'):
            i += 1
        if i >= n:
            break

        if values_str[i] == '(':
            # Find matching closing paren
            depth = 1
            j = i + 1
            while j < n and depth > 0:
                c = values_str[j]
                if c == '(':
                    depth += 1
                elif c == ')':
                    depth -= 1
                elif c == "'":
                    # Skip string content
                    j += 1
                    while j < n:
                        if values_str[j] == '\\':
                            j += 2
                            continue
                        if values_str[j] == "'":
                            # Check for escaped quote ''
                            if j + 1 < n and values_str[j + 1] == "'":
                                j += 2
                                continue
                            break
                        j += 1
                j += 1

            if depth == 0:
                row_content = values_str[i + 1:j - 1]
                values = parse_one_row(row_content)
                if values is not None:
                    rows.append(values)
            i = j
        else:
            i += 1

    return rows


def parse_one_row(row_str):
    """Parse a single row's comma-separated values."""
    values = []
    i = 0
    n = len(row_str)

    while i < n:
        # Skip whitespace
        while i < n and row_str[i] in (' ', '\t', '\n', '\r'):
            i += 1

        if i >= n:
            break

        c = row_str[i]

        if c == "'":
            # String value
            j = i + 1
            while j < n:
                if row_str[j] == '\\':
                    j += 2
                    continue
                if row_str[j] == "'":
                    if j + 1 < n and row_str[j + 1] == "'":
                        j += 2
                        continue
                    break
                j += 1
            values.append(row_str[i:j + 1])
            i = j + 1

        elif row_str[i:i + 4].upper() == 'NULL':
            values.append('NULL')
            i += 4

        elif c == ',':
            i += 1

        else:
            # Number or unquoted value
            j = i
            while j < n and row_str[j] not in (',', ' ', '\t', '\n', '\r', ')'):
                j += 1
            val = row_str[i:j].strip()
            if val:
                values.append(val)
            i = j

    return values


def parse_inserts(filepath):
    """
    Parse all INSERT statements from a SQL dump.
    Returns: dict table_name -> list of dicts (column_name -> value as string)
    """
    print(f"Parsing INSERT data from: {filepath}...")
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()

    # Pattern: INSERT INTO `table` (cols) VALUES (vals...);
    pattern = re.compile(
        r"INSERT INTO `(\w+)`\s*\((.*?)\)\s*VALUES\s*(.*?)\s*;",
        re.DOTALL | re.IGNORECASE
    )

    results = defaultdict(list)
    match_count = 0

    for match in pattern.finditer(content):
        table = match.group(1)
        columns_str = match.group(2)
        values_str = match.group(3)

        # Parse column names
        columns = [c.strip().strip('`') for c in columns_str.split(',')]

        # Parse value rows
        rows = parse_value_rows(values_str)

        for row in rows:
            if len(row) == len(columns):
                row_dict = dict(zip(columns, row))
                results[table].append(row_dict)
            else:
                # Try to recover - maybe the parser failed on complex values
                pass

        match_count += 1
        if match_count % 20 == 0:
            print(f"  Parsed {match_count} INSERT statements...")

    print(f"Parsed {match_count} INSERT statements across {len(results)} tables.")
    return results


# ── Filtrage ─────────────────────────────────────────────────────────────

def filter_data(all_data):
    """Filter all tables to only keep data related to SCHOOL_ID."""
    filtered = {}
    reachable = {}  # table -> set of reachable ids

    # Phase 1: Direct school_id tables
    print("\n" + "=" * 60)
    print("Phase 1: Tables with direct school_id")
    print("=" * 60)
    school_id_str = str(SCHOOL_ID)

    for table, col in DIRECT_SCHOOL_TABLES.items():
        if table not in all_data:
            print(f"  {table}: not found in data, skipping")
            continue
        rows = all_data[table]
        if table == 'school':
            filtered[table] = [r for r in rows if r.get('id') == school_id_str]
        else:
            filtered[table] = [r for r in rows if r.get(col) == school_id_str]
        ids = {r['id'] for r in filtered[table] if 'id' in r}
        reachable[table] = ids
        print(f"  {table}: {len(filtered[table])}/{len(rows)} rows kept")

    # Phase 2: Global tables
    print("\n" + "=" * 60)
    print("Phase 2: Global reference tables (keep all)")
    print("=" * 60)
    for table in GLOBAL_TABLES:
        if table not in all_data:
            print(f"  {table}: not found in data, skipping")
            continue
        filtered[table] = list(all_data[table])
        ids = {r['id'] for r in filtered[table] if 'id' in r}
        reachable[table] = ids
        print(f"  {table}: {len(filtered[table])} rows kept")

    # Phase 3: Excluded tables
    print("\n" + "=" * 60)
    print("Phase 3: Excluded legacy tables (not in project schema)")
    print("=" * 60)
    for table in EXCLUDED_TABLES:
        if table in all_data:
            print(f"  {table}: {len(all_data[table])} rows EXCLUDED")

    # Phase 4: Indirect tables
    print("\n" + "=" * 60)
    print("Phase 4: Indirect tables (via FK chains)")
    print("=" * 60)

    scp_ids = reachable.get('school_class_period', set())
    user_ids = reachable.get('user', set())
    timetable_ids = reachable.get('timetable', set())
    license_ids = reachable.get('app_license', set())
    scs_ids = set()  # school_class_subject
    sc_ids = set()  # student_class
    tt_day_ids = set()  # timetable_day
    csm_ids = reachable.get('class_subject_module', set())

    # student_class via school_class_period_id
    if 'student_class' in all_data:
        rows = all_data['student_class']
        filtered['student_class'] = [r for r in rows if r.get('school_class_period_id') in scp_ids]
        sc_ids = {r['id'] for r in filtered['student_class'] if 'id' in r}
        reachable['student_class'] = sc_ids
        print(f"  student_class: {len(filtered['student_class'])}/{len(rows)} rows (via school_class_period_id)")

    # school_class_subject via school_class_period_id
    if 'school_class_subject' in all_data:
        rows = all_data['school_class_subject']
        filtered['school_class_subject'] = [r for r in rows if r.get('school_class_period_id') in scp_ids]
        scs_ids = {r['id'] for r in filtered['school_class_subject'] if 'id' in r}
        reachable['school_class_subject'] = scs_ids
        print(f"  school_class_subject: {len(filtered['school_class_subject'])}/{len(rows)} rows (via school_class_period_id)")

    # school_class_subject_evaluation_time_not_applicable via school_class_subject_id
    if 'school_class_subject_evaluation_time_not_applicable' in all_data:
        rows = all_data['school_class_subject_evaluation_time_not_applicable']
        filtered['school_class_subject_evaluation_time_not_applicable'] = [
            r for r in rows if r.get('school_class_subject_id') in scs_ids
        ]
        na_ids = {r['id'] for r in filtered['school_class_subject_evaluation_time_not_applicable'] if 'id' in r}
        reachable['school_class_subject_evaluation_time_not_applicable'] = na_ids
        print(f"  school_class_subject_eval_time_na: {len(filtered['school_class_subject_evaluation_time_not_applicable'])}/{len(rows)} rows")

    # evaluation via student_id (FK to student_class) AND class_subject_module_id
    if 'evaluation' in all_data:
        rows = all_data['evaluation']
        filtered['evaluation'] = [
            r for r in rows
            if r.get('student_id') in sc_ids
            and r.get('class_subject_module_id') in csm_ids
        ]
        eval_ids = {r['id'] for r in filtered['evaluation'] if 'id' in r}
        reachable['evaluation'] = eval_ids
        print(f"  evaluation: {len(filtered['evaluation'])}/{len(rows)} rows (via student_class + class_subject_module)")

    # student_class_attendance via student_class_id
    if 'student_class_attendance' in all_data:
        rows = all_data['student_class_attendance']
        filtered['student_class_attendance'] = [
            r for r in rows if r.get('student_class_id') in sc_ids
        ]
        sca_ids = {r['id'] for r in filtered['student_class_attendance'] if 'id' in r}
        reachable['student_class_attendance'] = sca_ids
        print(f"  student_class_attendance: {len(filtered['student_class_attendance'])}/{len(rows)} rows (via student_class_id)")

    # student_class_timetable_presence via student_class_id
    if 'student_class_timetable_presence' in all_data:
        rows = all_data['student_class_timetable_presence']
        filtered['student_class_timetable_presence'] = [
            r for r in rows if r.get('student_class_id') in sc_ids
        ]
        sctp_ids = {r['id'] for r in filtered['student_class_timetable_presence'] if 'id' in r}
        reachable['student_class_timetable_presence'] = sctp_ids
        print(f"  student_class_timetable_presence: {len(filtered['student_class_timetable_presence'])}/{len(rows)} rows (via student_class_id)")

    # student_attendance via school_class_id (which is school_class_period_id)
    if 'student_attendance' in all_data:
        rows = all_data['student_attendance']
        filtered['student_attendance'] = [
            r for r in rows if r.get('school_class_id') in scp_ids
        ]
        sa_ids = {r['id'] for r in filtered['student_attendance'] if 'id' in r}
        reachable['student_attendance'] = sa_ids
        print(f"  student_attendance: {len(filtered['student_attendance'])}/{len(rows)} rows (via school_class_id)")

    # school_class_attendance via school_class_period_id OR evaluation_id
    if 'school_class_attendance' in all_data:
        rows = all_data['school_class_attendance']
        eval_ids = reachable.get('school_evaluation', set())
        filtered['school_class_attendance'] = [
            r for r in rows
            if r.get('school_class_period_id') in scp_ids
            or r.get('evaluation_id') in eval_ids
        ]
        scat_ids = {r['id'] for r in filtered['school_class_attendance'] if 'id' in r}
        reachable['school_class_attendance'] = scat_ids
        print(f"  school_class_attendance: {len(filtered['school_class_attendance'])}/{len(rows)} rows (via school_class_period_id or evaluation_id)")

    # timetable_day via timetable_id
    if 'timetable_day' in all_data:
        rows = all_data['timetable_day']
        filtered['timetable_day'] = [r for r in rows if r.get('timetable_id') in timetable_ids]
        tt_day_ids = {r['id'] for r in filtered['timetable_day'] if 'id' in r}
        reachable['timetable_day'] = tt_day_ids
        print(f"  timetable_day: {len(filtered['timetable_day'])}/{len(rows)} rows (via timetable_id)")

    # timetable_slot via school_class_period_id AND timetable_day_id
    if 'timetable_slot' in all_data:
        rows = all_data['timetable_slot']
        filtered['timetable_slot'] = [
            r for r in rows
            if r.get('school_class_period_id') in scp_ids
            and r.get('timetable_day_id', '0') in tt_day_ids
        ]
        ts_ids = {r['id'] for r in filtered['timetable_slot'] if 'id' in r}
        reachable['timetable_slot'] = ts_ids
        print(f"  timetable_slot: {len(filtered['timetable_slot'])}/{len(rows)} rows (via scp + timetable_day)")

    # modalities_subscriptions via school_class_period_id
    if 'modalities_subscriptions' in all_data:
        rows = all_data['modalities_subscriptions']
        filtered['modalities_subscriptions'] = [
            r for r in rows if r.get('school_class_period_id') in scp_ids
        ]
        ms_ids = {r['id'] for r in filtered['modalities_subscriptions'] if 'id' in r}
        reachable['modalities_subscriptions'] = ms_ids
        print(f"  modalities_subscriptions: {len(filtered['modalities_subscriptions'])}/{len(rows)} rows (via school_class_period_id)")

    # payment via author_id (user of school 2)
    if 'payment' in all_data:
        rows = all_data['payment']
        filtered['payment'] = [r for r in rows if r.get('author_id') in user_ids]
        pay_ids = {r['id'] for r in filtered['payment'] if 'id' in r}
        reachable['payment'] = pay_ids
        print(f"  payment: {len(filtered['payment'])}/{len(rows)} rows (via author_id in school users)")

    # school_license_payment via license_id
    if 'school_license_payment' in all_data:
        rows = all_data['school_license_payment']
        filtered['school_license_payment'] = [
            r for r in rows if r.get('license_id') in license_ids
        ]
        slp_ids = {r['id'] for r in filtered['school_license_payment'] if 'id' in r}
        reachable['school_license_payment'] = slp_ids
        print(f"  school_license_payment: {len(filtered['school_license_payment'])}/{len(rows)} rows (via license_id)")

    # operation_logs: garder logs liés à l'école 2
    if 'operation_logs' in all_data:
        rows = all_data['operation_logs']
        sid = school_id_str

        def keep_log(r):
            # Log sur l'entité School id=2
            if r.get('entity_name', '').strip("'") == 'School' and r.get('entity_id') == sid:
                return True
            # Log effectué par un utilisateur de l'école 2
            email = r.get('performed_by', '').strip("'")
            for u in filtered.get('user', []):
                if u.get('email', '').strip("'") == email:
                    return True
            return False

        filtered['operation_logs'] = [r for r in rows if keep_log(r)]
        print(f"  operation_logs: {len(filtered['operation_logs'])}/{len(rows)} rows (via school entity or school users)")

    # Phase 5: Vérification des tables non gérées
    print("\n" + "=" * 60)
    print("Phase 5: Checking for unhandled tables...")
    print("=" * 60)
    handled = (
        set(DIRECT_SCHOOL_TABLES.keys())
        | GLOBAL_TABLES
        | EXCLUDED_TABLES
        | {
            'student_class', 'school_class_subject',
            'school_class_subject_evaluation_time_not_applicable',
            'student_class_attendance', 'student_class_timetable_presence',
            'student_attendance', 'school_class_attendance',
            'timetable_day', 'timetable_slot',
            'modalities_subscriptions', 'payment',
            'operation_logs', 'school_license_payment',
            'evaluation',
        }
    )

    for table in all_data:
        if table not in handled:
            print(f"  ⚠ UNHANDLED: {table} ({len(all_data[table])} rows) — will EXCLUDE")
            # Don't include unhandled tables to avoid schema mismatch

    return filtered


# ── Génération de la sortie ──────────────────────────────────────────────

def read_schema(filepath):
    """Read the aligned schema from (9).sql."""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read().strip()
    return content


def parse_schema_columns(schema_content):
    """
    Parse CREATE TABLE statements to extract column names per table.
    Returns dict: table_name -> list of column names in order.
    """
    pattern = re.compile(
        r"CREATE TABLE `(\w+)`\s*\((.*?)\)\s*ENGINE=",
        re.DOTALL | re.IGNORECASE
    )
    columns_map = {}
    for match in pattern.finditer(schema_content):
        table = match.group(1)
        body = match.group(2)
        # Extract column definitions: `col_name` type ...
        cols = []
        for line in body.split('\n'):
            line = line.strip()
            # Match column definitions: `col_name` TYPE ...
            col_match = re.match(r'^`(\w+)`\s+\w+', line)
            if col_match:
                cols.append(col_match.group(1))
        if cols:
            columns_map[table] = cols
    return columns_map


def escape_sql_string(s):
    """Escape a string for SQL output."""
    if s is None or s == 'NULL':
        return 'NULL'
    s = str(s)
    if s.startswith("'") and s.endswith("'"):
        # Already quoted - but ensure internal quotes are escaped
        inner = s[1:-1]
        inner = inner.replace("\\'", "'")  # Unescape first
        inner = inner.replace("'", "''")   # Re-escape for SQL
        return f"'{inner}'"
    # Check if numeric
    try:
        float(s)
        return s
    except (ValueError, TypeError):
        return f"'{s}'"


def generate_output(schema_content, filtered_data, output_path):
    """Write the final SQL file with aligned schema + filtered data."""
    print(f"\nWriting output to: {output_path}...")

    # Parse schema columns for mapping
    schema_columns = parse_schema_columns(schema_content)
    print(f"Parsed schema columns for {len(schema_columns)} tables.")

    # Known column mappings: data_column -> schema_column
    # Use this when the data has a different column name than the schema
    COLUMN_RENAMES = {
        'school_evaluation': {
            'created_at': 'creted_at',  # Doctrine entity has typo
        },
    }

    # Columns to drop from data (exist in data but not in schema)
    COLUMNS_TO_DROP = {
        'school': {'trial_start_at', 'trial_duration'},
        'module': {'id'},  # module uses 'name' as PK, no 'id' column
    }

    with open(output_path, 'w', encoding='utf-8') as f:
        # Write the aligned schema
        f.write(schema_content)
        f.write('\n\n')

        # Write data in dependency order: school first, then others alphabetically
        table_order = ['school'] + sorted(filtered_data.keys() - {'school'})

        for table in table_order:
            rows = filtered_data.get(table, [])
            if not rows:
                continue

            # Get the schema columns for this table (target columns)
            target_columns = schema_columns.get(table, list(rows[0].keys()))
            renames = COLUMN_RENAMES.get(table, {})
            drops = COLUMNS_TO_DROP.get(table, set())

            # Build output columns: from schema, dropping any that don't exist in data
            # (a schema column missing from data will get NULL)
            data_columns = set(rows[0].keys())
            output_columns = []
            for col in target_columns:
                if col in drops:
                    continue
                # Check if this column exists in data (possibly via rename)
                data_col = col
                for data_name, schema_name in renames.items():
                    if schema_name == col:
                        data_col = data_name
                        break
                if data_col in data_columns or col in data_columns:
                    output_columns.append(col)

            # If target_columns is empty or doesn't cover data, fall back to data columns minus drops
            if not output_columns:
                output_columns = [c for c in rows[0].keys() if c not in drops]

            col_str = ', '.join(f'`{c}`' for c in output_columns)
            f.write(f'\nINSERT INTO `{table}` ({col_str}) VALUES\n')

            for i, row in enumerate(rows):
                values = []
                for col in output_columns:
                    # Map from data column to schema column (handle renames)
                    data_col = col
                    # Reverse lookup: which data column maps to this schema column?
                    for dcol, scol in renames.items():
                        if scol == col:
                            data_col = dcol
                            break

                    val = row.get(data_col)
                    if val is None or val == '':
                        values.append('NULL')
                    elif val == 'NULL':
                        values.append('NULL')
                    elif str(val).startswith("'") and str(val).endswith("'"):
                        inner = str(val)[1:-1]
                        inner = inner.replace("\\'", "'")
                        inner = inner.replace("'", "''")
                        values.append(f"'{inner}'")
                    else:
                        values.append(str(val))

                val_str = ', '.join(values)
                if i < len(rows) - 1:
                    f.write(f'({val_str}),\n')
                else:
                    f.write(f'({val_str});\n')

    # File size
    size_mb = os.path.getsize(output_path) / (1024 * 1024)
    print(f"Output written: {output_path} ({size_mb:.2f} MB)")


# ── Main ─────────────────────────────────────────────────────────────────

def main():
    # 1. Lire le schéma aligné
    print("=" * 60)
    print("STEP 1: Reading aligned schema")
    print("=" * 60)
    if not os.path.exists(SCHEMA_FILE):
        print(f"ERROR: Schema file not found: {SCHEMA_FILE}")
        sys.exit(1)
    schema = read_schema(SCHEMA_FILE)
    print(f"Schema loaded: {len(schema)} characters")

    # 2. Parser les données
    print("\n" + "=" * 60)
    print("STEP 2: Parsing INSERT data")
    print("=" * 60)
    if not os.path.exists(DATA_FILE):
        print(f"ERROR: Data file not found: {DATA_FILE}")
        sys.exit(1)
    all_data = parse_inserts(DATA_FILE)

    # Afficher résumé des tables trouvées
    print("\nTables found in data:")
    for table, rows in sorted(all_data.items()):
        status = ""
        if table in EXCLUDED_TABLES:
            status = " [EXCLUDED - legacy table]"
        elif table in DIRECT_SCHOOL_TABLES:
            status = " [direct school_id filter]"
        elif table in GLOBAL_TABLES:
            status = " [global - keep all]"
        else:
            status = " [indirect filter]"
        print(f"  {table}: {len(rows)} rows{status}")

    # 3. Filtrer
    print("\n" + "=" * 60)
    print("STEP 3: Filtering data for school ID =", SCHOOL_ID)
    print("=" * 60)
    filtered = filter_data(all_data)

    # Stats
    original_total = sum(len(v) for v in all_data.values() if v[0].get('__excluded__') != '1')
    filtered_total = sum(len(v) for v in filtered.values())
    print(f"\n{'=' * 60}")
    print(f"SUMMARY:")
    print(f"  Original rows (all tables): {sum(len(v) for v in all_data.values())}")
    print(f"  Filtered rows kept: {filtered_total}")
    print(f"  Tables with data in output: {len(filtered)}")
    print(f"{'=' * 60}")

    # 4. Écrire la sortie
    print("\n" + "=" * 60)
    print("STEP 4: Generating output file")
    print("=" * 60)

    # D'abord écrire dans un fichier temporaire
    generate_output(schema, filtered, TEMP_OUTPUT)

    # Puis remplacer l'original
    print(f"\nReplacing original file: {OUTPUT_FILE}")
    if os.path.exists(OUTPUT_FILE):
        backup = OUTPUT_FILE + ".bak"
        print(f"  Creating backup: {backup}")
        os.replace(OUTPUT_FILE, backup)
    os.replace(TEMP_OUTPUT, OUTPUT_FILE)
    print(f"Done! {OUTPUT_FILE} has been updated.")

    # Cleanup backup if everything succeeded
    backup = OUTPUT_FILE + ".bak"
    if os.path.exists(backup):
        os.remove(backup)
        print("Backup removed.")


if __name__ == '__main__':
    main()
