#!/usr/bin/env bash
# Bərpa yoxlaması: mənbə və bərpa olunmuş DB-də cədvəl üzrə sətir sayları, RLS policy-ləri, app rolunun hüquqları.
# İstifadə (superuser): SOURCE_DB=marketingschool TARGET_DB=ms_restore ./verify-restore.sh
set -euo pipefail
: "${SOURCE_DB:?}" "${TARGET_DB:?}"
q() { psql -At -d "$1" -c "$2"; }
counts() { for t in $(q "$1" "SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY 1"); do
  echo "$t $(psql -At -d "$1" -c "SET row_security = off; SELECT count(*) FROM \"$t\"" | tail -1)"; done; }
diff <(counts "$SOURCE_DB") <(counts "$TARGET_DB") && echo "✓ bütün cədvəllərdə sətir sayı eynidir ($(counts "$TARGET_DB" | wc -l) cədvəl, $(counts "$TARGET_DB" | awk '{s+=$2} END {print s}') sətir)"
src_pol=$(q "$SOURCE_DB" "SELECT count(*) FROM pg_policies"); dst_pol=$(q "$TARGET_DB" "SELECT count(*) FROM pg_policies")
[[ "$src_pol" == "$dst_pol" ]] && echo "✓ RLS policy sayı eynidir: $dst_pol"
echo "✓ FORCE RLS cədvəlləri: $(q "$TARGET_DB" "SELECT count(*) FROM pg_class WHERE relforcerowsecurity")"
[[ "$(q "$TARGET_DB" "SELECT count(*) FROM pg_trigger WHERE tgname IN ('audit_logs_no_update','grades_no_change','certificates_immutable')")" == "3" ]] && echo "✓ dəyişməzlik trigger-ləri yerindədir"
[[ "$(q "$TARGET_DB" "SELECT has_table_privilege('ms_app','audit_logs','UPDATE')")" == "f" ]] && echo "✓ ms_app audit_logs-u dəyişə bilmir"
