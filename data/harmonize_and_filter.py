#!/usr/bin/env python3
"""
Harmonise et filtre les données SQL splittées pour l'école GROUPE SCOLAIRE BILINGUE MERCURE.

Actions:
1. Utilise data/innovsh.sql comme schéma aligné avec les entités Doctrine
2. Parse tous les fichiers data/data_splited/innovsh (11)-XX-data.sql
3. Filtre pour ne garder que les données liées à l'école id=2 (GROUPE SCOLAIRE BILINGUE MERCURE)
4. Corrige les données pour correspondre au schéma des entités :
   - school: supprime trial_start_at, trial_duration
   - school_evaluation: renomme created_at → creted_at
   - module: supprime la colonne 'id' (name est la PK)
   - student_class_timetable_presence: convertit status tinyint → string
   - section_category_subject: supprime section_category_subject_group_id
   - Exclut les tables legacy (school_class_grade, school_class_subject_group, etc.)

Output: data/innovsh_mercure_clean.sql
"""

import re
import os
import sys
from collections import defaultdict

# ── Configuration ──────────────────────────────────────────────────────────
SCHOOL_ID = 2
SCHEMA_FILE = "data/innovsh.sql"
DATA_DIR = "data/data_splited"
OUTPUT_FILE = "data/innovsh_mercure_clean.sql"

# Tables avec school_id direct → filtrer
DIRECT_SCHOOL_TABLES = {
    'admission_reductions': 'school_id',
    'app_license': 'school_id',
    'class_subject_module': 'school_id',
    'migration_log': 'school_id',
    'school': 'id',
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

# Tables globales (référentiel partagé) → garder toutes les lignes
GLOBAL_TABLES = {
    'classe',
    'class_occurence',
    'core_update',
    'doctrine_migration_versions',
    'evaluation_appreciation_bareme',
    'evaluation_appreciation_template',
    'fees',
    'messenger_messages',
    'operation_logs',
    'registration_card_base_config',
    'report_card_template',
    'school_evaluation',
    'school_evaluation_frame',
    'school_evaluation_time',
    'school_evaluation_time_type',
    'school_period',
    'studies_type',
    'study_level',
    'study_subject',
    'subjects_modules',
    'school_license_payment',
    'module',
}

# Tables legacy à exclure (pas d'entité Doctrine correspondante)
EXCLUDED_TABLES = {
    'school_class_grade',
    'school_class_subject_group',
    'section_category_subject_group',
    'student_note',
    'subject_grade',
    'teacher_class',
}

# ── Harmonisation des colonnes ─────────────────────────────────────────────
# Colonnes à SUPPRIMER des INSERT (existent dans le dump mais pas dans les entités)
DROP_COLUMNS = {
    'school': {'trial_start_at', 'trial_duration'},
    'module': {'id'},
    'section_category_subject': {'section_category_subject_group_id'},
}

# Colonnes à RENOMMER dans les INSERT: ancien_nom → nouveau_nom
RENAME_COLUMNS = {
    'school_evaluation': {'created_at': 'creted_at'},
}

# Colonnes à CONVERTIR: nom_colonne → fonction_conversion
# La fonction reçoit la valeur string et retourne la valeur string SQL
def convert_timetable_presence_status(val):
    """Convertit tinyint status → string status."""
    if val is None or val == 'NULL':
        return "'absent'"
    v = str(val).strip("'")
    if v in ('1', '1.0'):
        return "'present'"
    else:
        return "'absent'"

CONVERT_COLUMNS = {
    'student_class_timetable_presence': {
        'status': convert_timetable_presence_status,
    },
}

# ── Parsing ─────────────────────────────────────────────────────────────────

def parse_value_rows(values_str):
    """
    Parse the VALUES (...) part of an INSERT.
    Returns list of lists of strings (each inner list = one row's values).
    Handles: NULL, numbers, 'strings' (with escaped quotes), JSON, nested parens.
    """
    rows = []
    i = 0
    n = len(values_str)

    while i < n:
        while i < n and values_str[i] in (' ', '\t', '\n', '\r'):
            i += 1
        if i >= n:
            break

        if values_str[i] == '(':
            depth = 1
            j = i + 1
            while j < n and depth > 0:
                c = values_str[j]
                if c == '(':
                    depth += 1
                elif c == ')':
                    depth -= 1
                elif c == "'":
                    j += 1
                    while j < n:
                        if values_str[j] == '\\':
                            j += 2
                            continue
                        if values_str[j] == "'":
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
        while i < n and row_str[i] in (' ', '\t', '\n', '\r'):
            i += 1
        if i >= n:
            break

        c = row_str[i]

        if c == "'":
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
            j = i
            while j < n and row_str[j] not in (',', ' ', '\t', '\n', '\r', ')'):
                j += 1
            val = row_str[i:j].strip()
            if val:
                values.append(val)
            i = j

    return values


def parse_inserts_from_files(data_dir):
    """
    Parse all INSERT statements from all data files in data_dir.
    Returns: dict table_name -> list of dicts (column_name -> value as string)
    """
    import glob
    pattern = os.path.join(data_dir, "innovsh (11)-*-data.sql")
    files = sorted(glob.glob(pattern),
                   key=lambda f: int(re.search(r'(\d+)-data', f).group(1)))

    results = defaultdict(list)
    total_matches = 0

    for filepath in files:
        fname = os.path.basename(filepath)
        print(f"  Parsing {fname}...")
        with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
            content = f.read()

        insert_pattern = re.compile(
            r"INSERT INTO `(\w+)`\s*\((.*?)\)\s*VALUES\s*(.*?)\s*;",
            re.DOTALL | re.IGNORECASE
        )

        for match in insert_pattern.finditer(content):
            table = match.group(1)
            columns_str = match.group(2)
            values_str = match.group(3)

            columns = [c.strip().strip('`') for c in columns_str.split(',')]
            rows = parse_value_rows(values_str)

            for row in rows:
                if len(row) == len(columns):
                    row_dict = dict(zip(columns, row))
                    results[table].append(row_dict)

            total_matches += 1

    print(f"  Total: {total_matches} INSERT statements across {len(results)} tables.")
    return results


# ── Harmonisation des données ───────────────────────────────────────────────

def harmonize_data(all_data):
    """Applique les corrections de colonnes aux données parsées."""

    # 1. Supprimer les colonnes obsolètes
    for table, cols in DROP_COLUMNS.items():
        if table in all_data:
            for row in all_data[table]:
                for col in cols:
                    row.pop(col, None)
            print(f"  [HARMONIZE] {table}: dropped columns {cols}")

    # 2. Renommer les colonnes
    for table, renames in RENAME_COLUMNS.items():
        if table in all_data:
            for row in all_data[table]:
                for old, new in renames.items():
                    if old in row:
                        row[new] = row.pop(old)
            print(f"  [HARMONIZE] {table}: renamed columns {renames}")

    # 3. Convertir les valeurs
    for table, conversions in CONVERT_COLUMNS.items():
        if table in all_data:
            for row in all_data[table]:
                for col, convert_func in conversions.items():
                    if col in row:
                        row[col] = convert_func(row[col])
            print(f"  [HARMONIZE] {table}: converted columns {list(conversions.keys())}")


# ── Déduplication de la table classe ────────────────────────────────────────

def deduplicate_classe(all_data):
    """
    L'entité Classe a un UNIQUE sur `name` seul, mais l'ancien dump autorisait
    des noms dupliqués avec des study_level_id différents (ex: 'Première' en
    Secondaire Général ET Secondaire Technique).

    On garde la première occurrence de chaque nom et on remappe toutes les FK
    dans les tables qui référencent classe.id.
    """
    if 'classe' not in all_data:
        return

    rows = all_data['classe']
    seen_names = {}
    id_remap = {}  # old_id -> new_id
    duplicates = []

    for row in rows:
        name = row.get('name', '').strip("'")
        row_id = row.get('id')
        if name in seen_names:
            # Doublon — remapper vers le premier ID trouvé
            id_remap[row_id] = seen_names[name]
            duplicates.append(row)
        else:
            seen_names[name] = row_id

    if not duplicates:
        print("  [DEDUP] classe: no duplicates found")
        return

    print(f"  [DEDUP] classe: {len(duplicates)} duplicate rows to remove")
    for d in duplicates:
        old_id = d.get('id')
        new_id = id_remap[old_id]
        name = d.get('name', '').strip("'")
        print(f"    - id={old_id} '{name}' -> id={new_id}")

    # Supprimer les doublons
    all_data['classe'] = [r for r in rows if r not in duplicates]

    # Remapper les FK dans les tables dépendantes
    fk_tables = {
        'class_occurence': 'classe_id',
        'school_class_numbering_type': 'classe_id',
        'section_category_subject': 'section_category_id',
    }

    for table, fk_col in fk_tables.items():
        if table not in all_data:
            continue
        remapped = 0
        for row in all_data[table]:
            val = row.get(fk_col)
            if val is not None and val in id_remap:
                row[fk_col] = id_remap[val]
                remapped += 1
        if remapped > 0:
            print(f"    - {table}.{fk_col}: {remapped} FK(s) remapped")


# ── Filtrage ────────────────────────────────────────────────────────────────

def filter_data(all_data):
    """Filter all tables to only keep data related to SCHOOL_ID."""
    filtered = {}
    reachable = {}
    school_id_str = str(SCHOOL_ID)

    # Phase 1: Direct school_id tables
    print("\nPhase 1: Tables with direct school_id")
    for table, col in DIRECT_SCHOOL_TABLES.items():
        if table not in all_data:
            print(f"  {table}: not found, skipping")
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
    print("\nPhase 2: Global reference tables (keep all)")
    for table in GLOBAL_TABLES:
        if table not in all_data:
            continue
        filtered[table] = list(all_data[table])
        ids = {r['id'] for r in filtered[table] if 'id' in r}
        reachable[table] = ids
        print(f"  {table}: {len(filtered[table])} rows kept")

    # Phase 3: Excluded tables
    print("\nPhase 3: Excluded legacy tables")
    for table in EXCLUDED_TABLES:
        if table in all_data:
            print(f"  {table}: {len(all_data[table])} rows EXCLUDED")

    # Phase 4: Indirect FK chain tables
    print("\nPhase 4: Indirect tables (via FK chains)")
    scp_ids = reachable.get('school_class_period', set())
    user_ids = reachable.get('user', set())
    timetable_ids = reachable.get('timetable', set())
    license_ids = reachable.get('app_license', set())
    eval_ids_global = reachable.get('school_evaluation', set())

    # school_class_subject via school_class_period_id
    if 'school_class_subject' in all_data:
        rows = all_data['school_class_subject']
        filtered['school_class_subject'] = [r for r in rows if r.get('school_class_period_id') in scp_ids]
        scs_ids = {r['id'] for r in filtered['school_class_subject'] if 'id' in r}
        reachable['school_class_subject'] = scs_ids
        print(f"  school_class_subject: {len(filtered['school_class_subject'])}/{len(rows)} rows")

    # class_subject_module via school_id AND period_id AND class_id (which is scp_id)
    if 'class_subject_module' in all_data:
        rows = all_data['class_subject_module']
        # Already filtered by school_id in Phase 1, but let's ensure scp matching
        csm_ids = {r['id'] for r in filtered.get('class_subject_module', []) if 'id' in r}
        reachable['class_subject_module'] = csm_ids

    # student_class via school_class_period_id
    if 'student_class' in all_data:
        rows = all_data['student_class']
        filtered['student_class'] = [r for r in rows if r.get('school_class_period_id') in scp_ids]
        sc_ids = {r['id'] for r in filtered['student_class'] if 'id' in r}
        reachable['student_class'] = sc_ids
        print(f"  student_class: {len(filtered['student_class'])}/{len(rows)} rows")

    # school_class_subject_evaluation_time_not_applicable via school_class_subject_id
    if 'school_class_subject_evaluation_time_not_applicable' in all_data:
        rows = all_data['school_class_subject_evaluation_time_not_applicable']
        filtered['school_class_subject_evaluation_time_not_applicable'] = [
            r for r in rows if r.get('school_class_subject_id') in scs_ids
        ]
        reachable['school_class_subject_evaluation_time_not_applicable'] = {
            r['id'] for r in filtered['school_class_subject_evaluation_time_not_applicable'] if 'id' in r
        }
        print(f"  school_class_subject_eval_time_na: {len(filtered['school_class_subject_evaluation_time_not_applicable'])}/{len(rows)} rows")

    # evaluation via student_id (FK to student_class) AND class_subject_module_id
    if 'evaluation' in all_data:
        rows = all_data['evaluation']
        filtered['evaluation'] = [
            r for r in rows
            if r.get('student_id') in sc_ids
            and r.get('class_subject_module_id') in csm_ids
        ]
        reachable['evaluation'] = {r['id'] for r in filtered['evaluation'] if 'id' in r}
        print(f"  evaluation: {len(filtered['evaluation'])}/{len(rows)} rows")

    # student_class_attendance via student_class_id
    if 'student_class_attendance' in all_data:
        rows = all_data['student_class_attendance']
        filtered['student_class_attendance'] = [r for r in rows if r.get('student_class_id') in sc_ids]
        reachable['student_class_attendance'] = {r['id'] for r in filtered['student_class_attendance'] if 'id' in r}
        print(f"  student_class_attendance: {len(filtered['student_class_attendance'])}/{len(rows)} rows")

    # student_class_timetable_presence via student_class_id
    if 'student_class_timetable_presence' in all_data:
        rows = all_data['student_class_timetable_presence']
        filtered['student_class_timetable_presence'] = [r for r in rows if r.get('student_class_id') in sc_ids]
        reachable['student_class_timetable_presence'] = {
            r['id'] for r in filtered['student_class_timetable_presence'] if 'id' in r
        }
        print(f"  student_class_timetable_presence: {len(filtered['student_class_timetable_presence'])}/{len(rows)} rows")

    # student_attendance via school_class_id (FK to school_class_period)
    if 'student_attendance' in all_data:
        rows = all_data['student_attendance']
        filtered['student_attendance'] = [r for r in rows if r.get('school_class_id') in scp_ids]
        reachable['student_attendance'] = {r['id'] for r in filtered['student_attendance'] if 'id' in r}
        print(f"  student_attendance: {len(filtered['student_attendance'])}/{len(rows)} rows")

    # school_class_attendance via school_class_period_id OR evaluation_id
    if 'school_class_attendance' in all_data:
        rows = all_data['school_class_attendance']
        filtered['school_class_attendance'] = [
            r for r in rows
            if r.get('school_class_id') in scp_ids  # colonne school_class_id = FK vers school_class_period
            or r.get('evaluation_id') in eval_ids_global
        ]
        reachable['school_class_attendance'] = {
            r['id'] for r in filtered['school_class_attendance'] if 'id' in r
        }
        print(f"  school_class_attendance: {len(filtered['school_class_attendance'])}/{len(rows)} rows")

    # timetable_day via timetable_id
    if 'timetable_day' in all_data:
        rows = all_data['timetable_day']
        filtered['timetable_day'] = [r for r in rows if r.get('timetable_id') in timetable_ids]
        tt_day_ids = {r['id'] for r in filtered['timetable_day'] if 'id' in r}
        reachable['timetable_day'] = tt_day_ids
        print(f"  timetable_day: {len(filtered['timetable_day'])}/{len(rows)} rows")

    # timetable_slot via school_class_period_id AND timetable_day_id
    if 'timetable_slot' in all_data:
        rows = all_data['timetable_slot']
        filtered['timetable_slot'] = [
            r for r in rows
            if r.get('school_class_period_id') in scp_ids
            and r.get('timetable_day_id', '0') in tt_day_ids
        ]
        reachable['timetable_slot'] = {r['id'] for r in filtered['timetable_slot'] if 'id' in r}
        print(f"  timetable_slot: {len(filtered['timetable_slot'])}/{len(rows)} rows")

    # modalities_subscriptions via school_class_period_id
    if 'modalities_subscriptions' in all_data:
        rows = all_data['modalities_subscriptions']
        filtered['modalities_subscriptions'] = [
            r for r in rows if r.get('school_class_period_id') in scp_ids
        ]
        reachable['modalities_subscriptions'] = {
            r['id'] for r in filtered['modalities_subscriptions'] if 'id' in r
        }
        print(f"  modalities_subscriptions: {len(filtered['modalities_subscriptions'])}/{len(rows)} rows")

    # payment via author_id (must be in school's users)
    if 'payment' in all_data:
        rows = all_data['payment']
        filtered['payment'] = [r for r in rows if r.get('author_id') in user_ids]
        reachable['payment'] = {r['id'] for r in filtered['payment'] if 'id' in r}
        print(f"  payment: {len(filtered['payment'])}/{len(rows)} rows")

    # school_license_payment via license_id
    if 'school_license_payment' in all_data:
        rows = all_data['school_license_payment']
        filtered['school_license_payment'] = [
            r for r in rows if r.get('license_id') in license_ids
        ]
        reachable['school_license_payment'] = {
            r['id'] for r in filtered['school_license_payment'] if 'id' in r
        }
        print(f"  school_license_payment: {len(filtered['school_license_payment'])}/{len(rows)} rows")

    # operation_logs: garder ceux liés à l'école 2 ou à ses utilisateurs
    if 'operation_logs' in all_data:
        rows = all_data['operation_logs']
        sid = school_id_str
        school_user_emails = {u.get('email', '').strip("'") for u in filtered.get('user', [])}

        def keep_log(r):
            if r.get('entity_name', '').strip("'") == 'School' and r.get('entity_id') == sid:
                return True
            email = r.get('performed_by', '').strip("'")
            return email in school_user_emails

        filtered['operation_logs'] = [r for r in rows if keep_log(r)]
        print(f"  operation_logs: {len(filtered['operation_logs'])}/{len(rows)} rows")

    # Vérification des tables non gérées
    print("\nPhase 5: Checking for unhandled tables...")
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
            print(f"  !! UNHANDLED: {table} ({len(all_data[table])} rows) -- will EXCLUDE")

    return filtered


# ── Génération de la sortie ─────────────────────────────────────────────────

def escape_sql_value(val):
    """Échappe une valeur pour la sortie SQL."""
    if val is None or val == 'NULL':
        return 'NULL'
    s = str(val)
    if s.upper() == 'NULL':
        return 'NULL'
    if s.startswith("'") and s.endswith("'"):
        inner = s[1:-1]
        inner = inner.replace("\\'", "'")
        inner = inner.replace("'", "''")
        return f"'{inner}'"
    try:
        float(s)
        return s
    except (ValueError, TypeError):
        return f"'{s}'"


def read_schema(schema_path):
    """Lit le fichier de schéma."""
    with open(schema_path, 'r', encoding='utf-8') as f:
        return f.read().strip()


def parse_schema_tables(schema_content):
    """Extrait la liste des tables du schéma (dans l'ordre)."""
    pattern = re.compile(r"CREATE TABLE `(\w+)`", re.IGNORECASE)
    return pattern.findall(schema_content)


def generate_output(schema_content, filtered_data, output_path):
    """Écrit le fichier SQL final."""
    print(f"\nWriting output to: {output_path}...")

    schema_tables = parse_schema_tables(schema_content)

    with open(output_path, 'w', encoding='utf-8') as f:
        # Écrire le schéma
        f.write(schema_content)
        f.write('\n\n')

        # Écrire les données dans l'ordre des tables du schéma
        total_rows = 0
        for table in schema_tables:
            rows = filtered_data.get(table, [])
            if not rows:
                continue

            # Obtenir les colonnes à partir des données
            columns = list(rows[0].keys())
            col_str = ', '.join(f'`{c}`' for c in columns)

            f.write(f'INSERT INTO `{table}` ({col_str}) VALUES\n')

            for i, row in enumerate(rows):
                values = [escape_sql_value(row.get(col)) for col in columns]
                val_str = ', '.join(values)
                suffix = ',\n' if i < len(rows) - 1 else ';\n'
                f.write(f'({val_str}){suffix}')

            total_rows += len(rows)

        # Aussi inclure les tables qui ont des données mais ne sont pas dans le schéma
        # (ne devrait pas arriver après filtrage)
        for table in sorted(filtered_data.keys()):
            if table not in schema_tables:
                rows = filtered_data[table]
                if not rows:
                    continue
                columns = list(rows[0].keys())
                col_str = ', '.join(f'`{c}`' for c in columns)
                f.write(f'\nINSERT INTO `{table}` ({col_str}) VALUES\n')
                for i, row in enumerate(rows):
                    values = [escape_sql_value(row.get(col)) for col in columns]
                    val_str = ', '.join(values)
                    suffix = ',\n' if i < len(rows) - 1 else ';\n'
                    f.write(f'({val_str}){suffix}')
                total_rows += len(rows)

    size_mb = os.path.getsize(output_path) / (1024 * 1024)
    print(f"Output written: {output_path} ({size_mb:.2f} MB)")
    print(f"Total data rows: {total_rows}")


# ── Main ────────────────────────────────────────────────────────────────────

def main():
    # 1. Lire le schéma aligné
    print("=" * 60)
    print("STEP 1: Reading aligned schema from", SCHEMA_FILE)
    print("=" * 60)
    if not os.path.exists(SCHEMA_FILE):
        print(f"ERROR: Schema file not found: {SCHEMA_FILE}")
        sys.exit(1)
    schema = read_schema(SCHEMA_FILE)
    schema_tables = parse_schema_tables(schema)
    print(f"Schema loaded: {len(schema)} chars, {len(schema_tables)} tables")

    # 2. Parser les données
    print("\n" + "=" * 60)
    print("STEP 2: Parsing INSERT data from", DATA_DIR)
    print("=" * 60)
    if not os.path.isdir(DATA_DIR):
        print(f"ERROR: Data directory not found: {DATA_DIR}")
        sys.exit(1)
    all_data = parse_inserts_from_files(DATA_DIR)

    print("\nTables found in data:")
    for table, rows in sorted(all_data.items()):
        status = ""
        if table in EXCLUDED_TABLES:
            status = " [EXCLUDED - legacy]"
        elif table in DIRECT_SCHOOL_TABLES:
            status = " [direct school_id filter]"
        elif table in GLOBAL_TABLES:
            status = " [global - keep all]"
        else:
            status = " [indirect filter]"
        print(f"  {table}: {len(rows)} rows{status}")

    # 3. Harmoniser les données
    print("\n" + "=" * 60)
    print("STEP 3: Harmonizing data structure with entities")
    print("=" * 60)
    harmonize_data(all_data)

    # 3b. Dédupliquer la table classe (unique sur name)
    print("\n" + "=" * 60)
    print("STEP 3b: Deduplicating classe table")
    print("=" * 60)
    deduplicate_classe(all_data)

    # 4. Filtrer
    print("\n" + "=" * 60)
    print("STEP 4: Filtering data for school ID =", SCHOOL_ID)
    print("=" * 60)
    filtered = filter_data(all_data)

    # Stats
    original_total = sum(len(v) for v in all_data.values())
    filtered_total = sum(len(v) for v in filtered.values())
    print(f"\n{'=' * 60}")
    print("SUMMARY:")
    print(f"  Original rows (all tables): {original_total}")
    print(f"  Filtered rows kept: {filtered_total}")
    print(f"  Tables with data in output: {len(filtered)}")
    print(f"{'=' * 60}")

    # 5. Écrire la sortie
    print("\n" + "=" * 60)
    print("STEP 5: Generating output file")
    print("=" * 60)
    generate_output(schema, filtered, OUTPUT_FILE)

    print(f"\n[DONE] Output: {OUTPUT_FILE}")


if __name__ == '__main__':
    main()
