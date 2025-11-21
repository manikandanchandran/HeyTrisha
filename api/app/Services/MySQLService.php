<?php

// Fetching Working Code 02/01/2025 12:00 PM

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MySQLService
{
    // ✅ Fetch the full database schema dynamically
    public function getCompactSchema()
    {
        Log::info("Fetching database schema...");

        try {
            // Fetch all tables from the database
            $tables = DB::select('SHOW TABLES');
            $schema = [];

            foreach ($tables as $table) {
                $tableName = reset($table); // Get table name
                $columns = DB::select("SHOW COLUMNS FROM `$tableName`");

                // ✅ Store all column names for each table
                $schema[$tableName] = array_map(fn($column) => $column->Field, $columns);
            }

            Log::info("Schema Retrieved: " . json_encode($schema));
            return $schema;
        } catch (\Exception $e) {
            Log::error("Error fetching schema: " . $e->getMessage());
            return ["error" => "Failed to retrieve database schema", "details" => $e->getMessage()];
        }
    }

    // ✅ Execute the AI-generated SQL query safely
    public function executeSQLQuery($sqlQuery)
    {
        try {
            // ✅ Validate SQL query before execution
            if (!is_string($sqlQuery) || empty($sqlQuery)) {
                Log::error("Invalid SQL Query: " . json_encode($sqlQuery));
                return ["error" => "Generated SQL query is invalid"];
            }

            Log::info("Executing SQL Query: " . $sqlQuery);

            // ✅ Execute query using MySQL
            $result = DB::select($sqlQuery);

            // ✅ Return results or no-data message
            return empty($result) ? ["message" => "No matching records found"] : $result;
        } catch (\Exception $e) {
            Log::error("SQL Execution Error: " . $e->getMessage());
            return ["error" => "Database query error", "details" => $e->getMessage()];
        }
    }
}
