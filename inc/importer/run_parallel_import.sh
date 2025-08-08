#!/bin/bash

# ============================================
# High-Speed Parallel Importer Script
# v1.1 - Fixed variable scope issue for sub-processes
# ============================================

# --- CONFIGURATION ---
# The full path to your WordPress installation
WP_PATH="/var/www/cob.com.eg/htdocs/"

# The full path to the CSV file you want to import
export CSV_FILE="/var/www/cob.com.eg/htdocs/wp-content/csv-imports/dido_data.nawy_properties_ar.csv"

# The language for the import ('ar' or 'en')
export LANGUAGE="ar"

# Number of parallel processes to run (should match your server cores)
CORES=8

# Extra flags for the command.
export EXTRA_FLAGS="--fast-images"
# --- END CONFIGURATION ---


# 1. Go to the WordPress directory
cd "$WP_PATH" || { echo "ERROR: WordPress path not found at $WP_PATH"; exit 1; }

# 2. Check if files exist
if [ ! -f "$CSV_FILE" ]; then
    echo "ERROR: CSV file not found at $CSV_FILE"
    exit 1
fi

# 3. Count total lines in the CSV file (excluding the header)
TOTAL_LINES=$(($(wc -l < "$CSV_FILE") - 1))

if [ "$TOTAL_LINES" -le 0 ]; then
    echo "ERROR: No data rows found in CSV file."
    exit 1
fi

echo "Total data rows to import: $TOTAL_LINES"
if [ -n "$EXTRA_FLAGS" ]; then
    echo "Running with extra flags: $EXTRA_FLAGS"
fi
echo "Starting parallel import across $CORES cores..."
echo "------------------------------------------------"

# 4. Calculate how many lines each core should process and export it
export LINES_PER_CORE=$(( (TOTAL_LINES + CORES - 1) / CORES ))

# 5. Generate and run the commands in parallel
# The single quotes around the bash command are crucial to prevent early variable expansion.
seq 0 $((CORES - 1)) | xargs -n1 -P$CORES -I{} bash -c '
    # This calculation now happens correctly inside each parallel process
    OFFSET=$(( {} * LINES_PER_CORE ))

    echo "Core {}: Starting import of $LINES_PER_CORE rows from offset $OFFSET..."
    
    # The variables are now correctly read from the exported environment
    wp cob property import "$CSV_FILE" --language="$LANGUAGE" --offset="$OFFSET" --limit="$LINES_PER_CORE" $EXTRA_FLAGS --allow-root
    
    echo "Core {}: Finished."
'

echo "------------------------------------------------"
echo "All parallel import processes have been completed."
echo "IMPORTANT: If you used --fast-images, you must now regenerate thumbnails using a plugin."

